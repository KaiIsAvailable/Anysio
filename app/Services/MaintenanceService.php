<?php
namespace App\Services;

use App\Models\{Maintenance, MaintenanceAttachment, MaintenanceEvent, Ticket, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Gate, Schema, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public function __construct(private TenantCustomerService $chat) {}

    public function ready(): bool
    {
        return Schema::hasColumn('maintenance', 'source_type');
    }

    public function authorizeTicket(Ticket $ticket, string $action): void
    {
        Gate::authorize('maintenance.'.$action);
        abort_if(Gate::allows('is-tenant'), 403);
        $this->chat->authorize($ticket, Auth::user());
    }

    public function authorize(Maintenance $order, string $action = 'view'): void
    {
        abort_unless($order->source_type === 'customer_service' && $order->ticket, 404);
        $this->authorizeTicket($order->ticket, $action);
    }

    public function location(Ticket $ticket): string
    {
        $asset = $ticket->lease?->leasable;
        return match (true) {
            $asset instanceof \App\Models\Room => implode(' / ', array_filter([$asset->unit?->property?->name, $asset->unit?->unit_no, $asset->room_no])),
            $asset instanceof \App\Models\Unit => implode(' / ', array_filter([$asset->property?->name, $asset->unit_no])),
            $asset instanceof \App\Models\Property => $asset->name,
            default => '',
        };
    }

    public function creationReason(Ticket $ticket): ?string
    {
        if (!$ticket->lease_id || !$ticket->lease || $ticket->lease->tenant?->user_id !== $ticket->sender_id
            || !$ticket->lease->leasable || !app(LeaseManagementResolver::class)->resolve($ticket->lease)) {
            return 'This conversation has no unambiguous recorded lease/management source. Confirm the historical source before creating maintenance.';
        }
        return null;
    }

    public function capabilities(Ticket $ticket): array
    {
        if (Gate::allows('is-tenant') || !Gate::allows('maintenance.create') || !$this->ready()) return [];
        return ['create_url' => route('admin.maintenance.work-orders.store', $ticket),
            'context_url' => route('admin.maintenance.work-orders.context', $ticket),
            'reason' => $this->creationReason($ticket)];
    }

    public function visibleOrders()
    {
        Gate::authorize('maintenance.view');
        return Maintenance::where('source_type', 'customer_service')
            ->whereIn('ticket_id', $this->chat->visibleTickets(Auth::user())->select('tickets.id'));
    }

    public function execute(Request $request, Ticket $ticket, string $action, ?Maintenance $order = null): Maintenance
    {
        abort_unless($this->ready(), 503, 'Maintenance database upgrade is required.');
        $this->authorizeTicket($ticket, $action);
        $rules = ['request_key' => ['required', 'uuid']];
        if ($action === 'create') {
            $rules += ['title' => ['required', 'string', 'max:255'], 'desc' => ['required', 'string', 'max:20000'],
                'location_detail' => ['required', 'string', 'max:255'],
                'source_message_ids' => ['nullable', 'array', 'max:10'],
                'source_message_ids.*' => ['string', 'size:26', 'distinct']];
        } else {
            $rules += ['version' => ['required', 'integer', 'min:0']];
        }
        if ($action === 'assign') $rules += ['contractor_name' => ['required', 'string', 'max:255'], 'contractor_phone' => ['nullable', 'string', 'max:80']];
        $data = $request->validate($rules);
        foreach (['title', 'desc', 'location_detail', 'contractor_name'] as $field) {
            if (isset($data[$field]) && trim($data[$field]) === '') throw ValidationException::withMessages([$field => 'Enter a non-empty value.']);
        }
        $items = $action === 'assign' ? [] : $this->chat->validateEvidence($request);
        if (count($items) + count($data['source_message_ids'] ?? []) > config('customer_service.max_files')) {
            throw ValidationException::withMessages(['attachments' => 'Select at most 10 evidence files in total.']);
        }
        $hash = hash('sha256', json_encode([$action, $order?->id, $data,
            array_map(fn ($i) => [$i['file']->getClientOriginalName(), hash_file('sha256', $i['file']->getRealPath())], $items)]));
        $uploaded = [];
        try {
            // All work-order operations lock ticket first, matching chat read/reply lock order.
            return DB::transaction(function () use ($ticket, $order, $action, $data, $hash, $items, &$uploaded) {
                $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                $this->authorizeTicket($ticket, $action);
                $previous = MaintenanceEvent::where('ticket_id', $ticket->id)->where('request_key', $data['request_key'])->first();
                if ($previous) {
                    abort_unless($previous->payload_hash === $hash && $previous->actor_id === Auth::id(), 409, 'This request key was used for different input. Refresh and confirm again.');
                    return Maintenance::findOrFail($previous->maintenance_id);
                }
                if ($action === 'create') {
                    if ($reason = $this->creationReason($ticket)) throw ValidationException::withMessages(['ticket' => $reason]);
                    $id = (string) Str::ulid();
                    $manager = app(LeaseManagementResolver::class)->resolve($ticket->lease);
                    $order = Maintenance::create([
                        'id' => $id, 'maintenance_number' => 'MNT-'.$id, 'source_type' => 'customer_service',
                        'ticket_id' => $ticket->id, 'lease_id' => $ticket->lease_id, 'management_user_id' => $manager->id,
                        'location_snapshot' => $this->location($ticket), 'location_detail' => trim($data['location_detail']),
                        'title' => trim($data['title']), 'desc' => trim($data['desc']), 'status' => 'pending',
                        'created_by' => Auth::id(), 'version' => 1,
                        // Legacy schema requires these; no quotation/payment workflow is introduced.
                        'cost' => 0, 'paid_by' => 'N/A',
                    ]);
                    $ticket->update(['category' => 'maintenance']);
                    $changes = ['status' => ['from' => null, 'to' => 'pending']];
                    $this->saveEvidence($order, $ticket, 'before', $items, $data['source_message_ids'] ?? [], $uploaded);
                } else {
                    $order = Maintenance::whereKey($order->id)->lockForUpdate()->firstOrFail();
                    $this->authorize($order, $action);
                    abort_unless($order->ticket_id === $ticket->id, 404);
                    abort_unless($order->version === (int) $data['version'], 409, 'This work order changed. Refresh its details before confirming.');
                    $from = $order->status;
                    if ($action === 'assign') {
                        abort_unless(in_array($from, ['pending', 'maintaining'], true), 409, 'Completed work orders cannot be reassigned.');
                        $changes = ['contractor_name' => ['from' => $order->contractor_name, 'to' => trim($data['contractor_name'])],
                            'contractor_phone' => ['from' => $order->contractor_phone, 'to' => $data['contractor_phone'] ?? null]];
                        $order->fill(['contractor_name' => trim($data['contractor_name']), 'contractor_phone' => $data['contractor_phone'] ?? null,
                            'status' => 'maintaining', 'assigned_by' => Auth::id(), 'assigned_at' => now()]);
                    } else {
                        abort_unless($from === 'maintaining', 409, 'Only a maintaining work order can be completed.');
                        $this->saveEvidence($order, $ticket, 'completion', $items, [], $uploaded);
                        $changes = [];
                        $order->fill(['status' => 'completed', 'completed_by' => Auth::id(), 'completed_at' => now()]);
                    }
                    $changes['status'] = ['from' => $from, 'to' => $order->status];
                    $order->version++;
                    $order->save();
                }
                MaintenanceEvent::create(['maintenance_id' => $order->id, 'ticket_id' => $ticket->id,
                    'request_key' => $data['request_key'], 'payload_hash' => $hash, 'action' => $action,
                    'actor_id' => Auth::id(), 'changes' => $changes]);
                // Reassignment is audited internally, without another public assignment notice.
                if ($action !== 'assign' || ($changes['status']['from'] ?? null) === 'pending') {
                    $label = match ($action) { 'create' => 'Maintenance created', 'assign' => 'Maintenance assigned', default => 'Maintenance completed' };
                    $ticket->messages()->create(['sender_type' => 'system', 'maintenance_id' => $order->id,
                        'message' => $label.' · '.$order->maintenance_number.' · '.$order->title]);
                }
                return $order;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($uploaded);
            throw $e;
        }
    }

    private function saveEvidence(Maintenance $order, Ticket $ticket, string $purpose, array $items, array $sourceIds, array &$uploaded): void
    {
        $disk = Storage::disk('local');
        $total = array_sum(array_map(fn ($i) => $i['file']->getSize(), $items));
        foreach ($sourceIds as $id) {
            $message = $ticket->messages()->whereKey($id)->firstOrFail();
            $source = $this->chat->attachmentPath($message);
            abort_unless($source && $disk->exists($source), 422, 'Selected chat evidence is unavailable.');
            $total += $disk->size($source);
            if ($total > config('customer_service.max_total_kb') * 1024) throw ValidationException::withMessages(['attachments' => 'The combined evidence exceeds 100 MB.']);
            $path = app(FileService::class)->getPath('maintenance', $order->management_user_id).'/'.Str::uuid().'.'.pathinfo($source, PATHINFO_EXTENSION);
            $uploaded[] = $path;
            if (!$disk->copy($source, $path)) throw new \RuntimeException('Unable to preserve chat evidence.');
            $this->recordAttachment($order, $purpose, $path, basename($source), $id);
        }
        foreach ($items as $item) {
            $path = in_array($item['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)
                ? app(TicketImageProcessor::class)->upload($item['file'], $order->management_user_id, 'maintenance')
                : app(FileService::class)->upload($item['file'], $order->management_user_id, 'maintenance');
            if (!$path) throw new \RuntimeException('Unable to save evidence. No completion was recorded.');
            $uploaded[] = $path;
            $this->recordAttachment($order, $purpose, $path, $item['file']->getClientOriginalName());
        }
    }

    private function recordAttachment(Maintenance $order, string $purpose, string $path, string $filename, ?string $source = null): void
    {
        $disk = Storage::disk('local');
        $order->attachments()->create(['purpose' => $purpose, 'path' => $path, 'filename' => $filename,
            'mime' => $disk->mimeType($path), 'size' => $disk->size($path), 'sha256' => hash_file('sha256', $disk->path($path)),
            'source_ticket_msg_id' => $source, 'uploaded_by' => Auth::id()]);
    }

    public function cards(Ticket $ticket): array
    {
        if (!$this->ready()) return [];
        $this->chat->authorize($ticket, Auth::user());
        return Maintenance::where('ticket_id', $ticket->id)->where('source_type', 'customer_service')
            ->with(['attachments' => fn ($q) => $q->where('purpose', 'completion')])->orderBy('created_at')->orderBy('id')->get()
            ->map(fn ($order) => ['id' => $order->id, 'number' => $order->maintenance_number, 'title' => $order->title,
                'status' => $order->status, 'version' => $order->version,
                'attachments' => $order->status === 'completed' ? $order->attachments->map(fn ($file) => [
                    'id' => $file->id, 'filename' => $file->filename, 'mime' => $file->mime,
                    'url' => route('maintenance.evidence', $file),
                ])->all() : []])->all();
    }

    public function authorizeAttachment(MaintenanceAttachment $file): void
    {
        $order = $file->maintenance;
        abort_unless($order && $order->source_type === 'customer_service' && $order->ticket, 404);
        if (Gate::allows('is-tenant')) {
            $this->chat->authorize($order->ticket, Auth::user());
            abort_unless($file->purpose === 'completion' && $order->status === 'completed', 403);
        } else $this->authorize($order);
    }
}
