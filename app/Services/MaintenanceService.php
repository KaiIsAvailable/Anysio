<?php
namespace App\Services;

use App\Models\{FeeType, Lease, Maintenance, MaintenanceAttachment, MaintenanceEvent, Refund, Staff, Ticket, TicketMsg, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Gate, Schema, Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Enums\RefundStatus;

class MaintenanceService
{
    public function __construct(private TenantCustomerService $chat) {}

    public function ready(): bool
    {
        return Schema::hasColumn('maintenance', 'source_type') && Schema::hasColumn('maintenance', 'qty')
            && Schema::hasColumn('maintenance', 'assigned_staff_id') && Schema::hasColumn('maintenance', 'leasable_id');
    }

    public function authorizeTicket(Ticket $ticket, string $action): void
    {
        Gate::authorize('maintenance.'.$action);
        abort_if(Gate::allows('is-tenant'), 403);
        abort_unless($this->chat->canAccess($ticket, Auth::user(), 'maintenance.tab'), 403);
    }

    public function authorize(Maintenance $order, string $action = 'view'): void
    {
        if (in_array($order->source_type, ['manual', 'refund'], true)) {
            Gate::authorize('maintenance.'.$action);
            abort_unless(!$order->ticket_id && $order->management_user_id === $this->managementAccount()->id, 403);
            return;
        }
        abort_unless($order->source_type === 'customer_service' && $order->ticket, 404);
        $this->authorizeTicket($order->ticket, $action);
    }

    public function location(Ticket $ticket): string
    {
        return $this->leaseLocation($ticket->lease);
    }

    private function leaseLocation(?Lease $lease): string
    {
        return $lease?->leasable ? app(MaintenanceLeasableService::class)->location($lease->leasable) : '';
    }

    public function creationReason(Ticket $ticket): ?string
    {
        if ($this->chat->isClosed($ticket)) {
            return 'This conversation is completed or closed and is read-only.';
        }
        if ($this->ready() && $ticket->maintenanceOrder()->exists()) {
            return 'This conversation already has a maintenance work order. Manage the existing work order.';
        }
        if (!$ticket->lease_id || !$ticket->lease || $ticket->lease->tenant?->user_id !== $ticket->sender_id
            || !$ticket->lease->leasable || !app(LeaseManagementResolver::class)->resolve($ticket->lease)) {
            return 'This conversation has no unambiguous recorded lease/management source. Confirm the historical source before creating maintenance.';
        }
        return null;
    }

    public function capabilities(Ticket $ticket): array
    {
        if (Gate::allows('is-tenant') || !Gate::allows('maintenance.tab') || !Gate::allows('maintenance.create') || !$this->ready() || $this->chat->isClosed($ticket)) return [];
        if ($ticket->maintenanceOrder()->exists()) return [];
        return ['create_url' => route('admin.maintenance.work-orders.store', $ticket),
            'context_url' => route('admin.maintenance.work-orders.context', $ticket),
            'reason' => $this->creationReason($ticket)];
    }

    public function visibleOrders()
    {
        Gate::authorize('maintenance.view');
        $manager = $this->managementAccount();
        $tickets = $this->chat->visibleTickets(Auth::user(), 'maintenance.tab')->select('tickets.id');
        return Maintenance::where(fn ($query) => $query
            ->where(fn ($query) => $query->where('source_type', 'customer_service')->whereIn('ticket_id', $tickets))
            ->orWhere(fn ($query) => $query->whereIn('source_type', ['manual', 'refund'])->where('management_user_id', $manager->id)));
    }

    public function createFromRefund(Refund $refund): void
    {
        abort_unless($refund->status === RefundStatus::PAID, 409, 'Process the refund before generating maintenance.');
        $items = $refund->items()->where('origin', 'manual')->whereIn('type', ['damage', 'cleaning', 'utility', 'adjustment'])
            ->where(fn ($query) => $query->where('type', '!=', 'utility')->orWhere('description', 'not like', 'Unpaid Invoice Auto-Deduction:%'))
            ->with('feeType')->get();
        if ($items->isEmpty()) return;
        // Payout owns the transaction, so a failed work order also rolls back settlement.
        abort_unless($this->ready() && Schema::hasColumn('maintenance', 'refund_item_id'), 503, 'Maintenance database upgrade is required.');
        $lease = $refund->lease;
        $manager = $lease ? app(LeaseManagementResolver::class)->resolve($lease) : null;
        abort_unless($manager && (Auth::user()?->role === User::ROLE_ADMIN || get_effective_user()?->id === $manager->id), 403);
        $orders = Maintenance::where('source_type', 'refund')->where('refund_id', $refund->id)
            ->where('management_user_id', $manager->id)->lockForUpdate()->get();
        $catalogOwners = collect([$manager->id]);
        if ($manager->role === User::ROLE_AGENT_ADMIN) {
            $catalogOwners = $catalogOwners->merge(\App\Models\Owners::where('agent_id', $manager->id)->pluck('user_id'));
        }
        foreach ($items as $item) {
            $order = $orders->firstWhere('refund_item_id', $item->id);
            if ($order) continue;
            do {
                $number = app(DocumentSequenceService::class)->generateMaintenanceNumber($manager);
            } while (Maintenance::withTrashed()->where('management_user_id', $manager->id)->where('maintenance_number', $number)->exists());
            if ($item->feeType && !$item->feeType->is_system && !$catalogOwners->contains($item->feeType->user_id)) {
                throw ValidationException::withMessages(['items' => 'Select deduction items under your management account.']);
            }
            $title = trim($item->description ?: ($item->feeType?->name ?? ''));
            if ($title === '') throw ValidationException::withMessages(['items' => 'Select an item or enter a description for each deduction.']);
            $order = Maintenance::create(['maintenance_number' => $number, 'source_type' => 'refund', 'refund_id' => $refund->id,
                'refund_item_id' => $item->id, 'lease_id' => $lease->id, 'management_user_id' => $manager->id,
                'leasable_type' => $lease->leasable_type, 'leasable_id' => $lease->leasable_id,
                'fee_type_id' => $item->fee_type_id, 'qty' => $item->qty, 'title' => $title,
                'desc' => $item->description, 'location_snapshot' => $this->leaseLocation($lease), 'location_detail' => null,
                'status' => 'pending', 'created_by' => Auth::id(), 'version' => 1, 'cost' => 0, 'paid_by' => 'N/A']);
            $changes = ['status' => ['from' => null, 'to' => 'pending']];
            $order->events()->create(['ticket_id' => null, 'request_key' => (string) Str::uuid(), 'payload_hash' => hash('sha256', $item->id),
                'action' => 'create', 'actor_id' => Auth::id(), 'changes' => $changes]);
        }
    }

    private function managementAccount(): User
    {
        $manager = get_effective_user();
        abort_unless($manager instanceof User && in_array($manager->role, [User::ROLE_ADMIN, User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN], true), 403);

        return $manager;
    }

    public function itemOptions(): array
    {
        $manager = $this->managementAccount();
        return app(SettingService::class)->filterActiveFeeTypes($this->itemQuery($manager)->orderBy('name')->get(), $manager->id)
            ->map(fn ($item) => ['value' => $item->id, 'label' => $item->name])->values()->all();
    }

    public function staffOptions(): array
    {
        Gate::authorize('maintenance.assign');

        return $this->assignableStaff($this->managementAccount())->with('user')->get()
            ->sortBy(fn ($staff) => $staff->user->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn ($staff) => ['value' => $staff->id, 'label' => $staff->user->name.' - '.$staff->user->email])->values()->all();
    }

    private function assignableStaff(User $manager)
    {
        return Staff::whereHas('user_management', fn ($query) => $query->where('user_id', $manager->id))
            ->whereIn('is_active', ['1', 'active'])
            ->whereHas('user', fn ($query) => $query->where('role', User::ROLE_STAFF)->where('status', 'active'));
    }

    private function itemQuery(User $manager)
    {
        // Match the lease refund catalog: shared items plus this management account's items.
        return FeeType::visibleToActor()->where('category', 'item')->active()->where(function ($query) use ($manager) {
            $query->where('is_system', true)->orWhere('user_id', $manager->id);
            if ($manager->role === User::ROLE_AGENT_ADMIN) {
                $query->orWhereIn('user_id', \App\Models\Owners::where('agent_id', $manager->id)->select('user_id'));
            }
        });
    }

    public function manualLeasables(): array
    {
        Gate::authorize('maintenance.create');
        return app(MaintenanceLeasableService::class)->options($this->managementAccount());
    }

    public function execute(Request $request, ?Ticket $ticket, string $action, ?Maintenance $order = null): Maintenance
    {
        abort_unless($this->ready(), 503, 'Maintenance database upgrade is required.');
        if ($ticket) $this->authorizeTicket($ticket, $action);
        elseif ($order) $this->authorize($order, $action);
        else Gate::authorize('maintenance.'.$action);
        $manager = $this->managementAccount();
        $lockManagerId = $order?->management_user_id
            ?? ($ticket?->lease ? app(LeaseManagementResolver::class)->resolve($ticket->lease)?->id : null)
            ?? $manager->id;
        $rules = ['request_key' => ['required', 'uuid']];
        if (in_array($action, ['create', 'edit'], true)) {
            $rules += ['title' => ['required_without:fee_type_id', 'nullable', 'string', 'max:255'], 'desc' => ['required', 'string', 'max:20000'],
                'location_detail' => ['required', 'string', 'max:255'],
                'fee_type_id' => ['nullable', 'string', Rule::in($action === 'create' && $request->filled('fee_type_id') ? array_column($this->itemOptions(), 'value') : [])],
                'qty' => ['sometimes', 'integer', 'min:1', 'max:4294967295']];
        }
        if ($action === 'create') {
            $rules += ['source_message_ids' => [$ticket ? 'nullable' : 'prohibited', 'array', 'max:10'], 'source_message_ids.*' => ['string', 'size:26', 'distinct']];
            if (!$ticket) $rules += ['leasable_type' => ['required', Rule::in(array_keys(MaintenanceLeasableService::TYPES))],
                'leasable_id' => ['required', 'ulid'], 'lease_id' => ['prohibited'], 'ticket_id' => ['prohibited']];
            if (!$ticket) { $rules['fee_type_id'][0] = 'required'; $rules['qty'][0] = 'required'; }
        }
        if ($action === 'edit') {
            $rules += ['remove_attachment_ids' => ['nullable', 'array', 'max:'.config('customer_service.max_files')],
                'remove_attachment_ids.*' => ['required', 'ulid', 'distinct']];
            $rules['title'] = ['sometimes', 'string', Rule::in([$order->title])];
            $rules['fee_type_id'] = ['sometimes', Rule::in([$order->fee_type_id])];
            if ($order->source_type === 'refund') $rules['location_detail'] = ['nullable', 'string', 'max:255'];
        }
        if ($action !== 'create') $rules += ['version' => ['required', 'integer', 'min:0']];
        if ($action === 'assign') $rules += ['assigned_staff_id' => ['required', 'ulid']];
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
            // Serialize availability updates for the owning account, including platform-admin actions.
            return DB::transaction(function () use ($ticket, $order, $action, $data, $hash, $items, $manager, $lockManagerId, &$uploaded) {
                User::whereKey($lockManagerId)->lockForUpdate()->firstOrFail();
                if ($ticket) {
                    $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                    $this->authorizeTicket($ticket, $action);
                    $previous = MaintenanceEvent::where('ticket_id', $ticket->id)->where('request_key', $data['request_key'])->first();
                } else {
                    User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
                    abort_unless($this->managementAccount()->id === $manager->id, 403);
                    if ($action === 'create') {
                        $previousOrder = Maintenance::where('management_user_id', $manager->id)->where('manual_request_key', $data['request_key'])->first();
                        if ($previousOrder) {
                            abort_unless($previousOrder->manual_payload_hash === $hash && $previousOrder->created_by === Auth::id(), 409, 'This request key was used for different input. Refresh and confirm again.');
                            return $previousOrder;
                        }
                        $previous = null;
                    } else {
                        $previous = MaintenanceEvent::where('maintenance_id', $order->id)->where('request_key', $data['request_key'])->first();
                    }
                }
                if ($previous) {
                    abort_unless($previous->payload_hash === $hash && $previous->actor_id === Auth::id(), 409, 'This request key was used for different input. Refresh and confirm again.');
                    return Maintenance::findOrFail($previous->maintenance_id);
                }
                $feeType = null;
                if ($action === 'create' && !empty($data['fee_type_id'])) {
                    $feeType = $this->itemQuery($manager)->whereKey($data['fee_type_id'])->lockForUpdate()->first();
                    if (!$feeType) throw ValidationException::withMessages(['fee_type_id' => 'The selected maintenance item is no longer available.']);
                    $data['title'] = $feeType->name;
                }
                if ($action === 'create') {
                    if ($ticket) {
                        if ($reason = $this->creationReason($ticket)) throw ValidationException::withMessages(['ticket' => $reason]);
                        $lease = $ticket->lease;
                        $orderManager = app(LeaseManagementResolver::class)->resolve($lease);
                        $leasable = $lease->leasable;
                    } else {
                        $lease = null;
                        $leasable = app(MaintenanceLeasableService::class)->selected($data['leasable_type'], $data['leasable_id'], $manager);
                        $orderManager = $manager;
                    }
                    $id = (string) Str::ulid();
                    do {
                        $number = app(DocumentSequenceService::class)->generateMaintenanceNumber($orderManager);
                    } while (Maintenance::withTrashed()->where('management_user_id', $orderManager->id)->where('maintenance_number', $number)->exists());
                    $order = Maintenance::create([
                        'id' => $id, 'maintenance_number' => $number, 'source_type' => $ticket ? 'customer_service' : 'manual',
                        'ticket_id' => $ticket?->id, 'lease_id' => $lease?->id, 'management_user_id' => $orderManager->id,
                        'leasable_type' => $leasable->getMorphClass(), 'leasable_id' => $leasable->id,
                        'fee_type_id' => $feeType?->id, 'qty' => $data['qty'] ?? 1,
                        'manual_request_key' => $ticket ? null : $data['request_key'], 'manual_payload_hash' => $ticket ? null : $hash,
                        'location_snapshot' => app(MaintenanceLeasableService::class)->location($leasable), 'location_detail' => trim($data['location_detail']),
                        'title' => trim($data['title']), 'desc' => trim($data['desc']), 'status' => 'pending',
                        'created_by' => Auth::id(), 'version' => 1,
                        // Legacy schema requires these; no quotation/payment workflow is introduced.
                        'cost' => 0, 'paid_by' => 'N/A',
                    ]);
                    if ($ticket) $ticket->update(['category' => 'maintenance']);
                    $changes = ['status' => ['from' => null, 'to' => 'pending']];
                    $this->saveEvidence($order, $ticket, 'before', $items, $data['source_message_ids'] ?? [], $uploaded);
                } else {
                    $order = Maintenance::whereKey($order->id)->lockForUpdate()->firstOrFail();
                    $this->authorize($order, $action);
                    abort_unless($order->ticket_id === $ticket?->id, 404);
                    abort_unless($order->version === (int) $data['version'], 409, 'This work order changed. Refresh its details before confirming.');
                    $from = $order->status;
                    if ($action === 'assign') {
                        abort_unless(in_array($from, ['pending', 'maintaining'], true), 409, 'Completed work orders cannot be reassigned.');
                        $staff = $this->assignableStaff($manager)->with('user')->whereKey($data['assigned_staff_id'])->lockForUpdate()->first();
                        if (!$staff) throw ValidationException::withMessages(['assigned_staff_id' => 'Select an active staff member under your management account.']);
                        $changes = ['assigned_staff_id' => ['from' => $order->assigned_staff_id, 'to' => $staff->id],
                            'contractor_name' => ['from' => $order->contractor_name, 'to' => $staff->user->name],
                            'contractor_phone' => ['from' => $order->contractor_phone, 'to' => $staff->phone]];
                        $order->fill(['assigned_staff_id' => $staff->id, 'contractor_name' => $staff->user->name, 'contractor_phone' => $staff->phone,
                            'status' => 'maintaining', 'assigned_by' => Auth::id(), 'assigned_at' => now()]);
                    } elseif ($action === 'edit') {
                        abort_if($from === 'completed' || ($ticket && $this->chat->isClosed($ticket)), 409, 'Completed or closed maintenance cannot be edited.');
                        $changes = [];
                        foreach (['desc', 'location_detail'] as $field) {
                            $value = trim((string) ($data[$field] ?? ''));
                            if ($order->$field !== $value) $changes[$field] = ['from' => $order->$field, 'to' => $value];
                            $order->$field = $value;
                        }
                        if (isset($data['qty']) && $order->qty !== (int) $data['qty']) {
                            $changes['qty'] = ['from' => $order->qty, 'to' => (int) $data['qty']];
                            $order->qty = $data['qty'];
                        }
                        $changes += $this->editEvidence($order, $ticket, $items, $data['remove_attachment_ids'] ?? [], $uploaded);
                    } else {
                        abort_unless($from === 'maintaining', 409, 'Only a maintaining work order can be completed.');
                        $this->saveEvidence($order, $ticket, 'completion', $items, [], $uploaded);
                        $changes = [];
                        $order->fill(['status' => 'completed', 'completed_by' => Auth::id(), 'completed_at' => now()]);
                    }
                    if ($action !== 'edit') $changes['status'] = ['from' => $from, 'to' => $order->status];
                    $order->version++;
                    $order->save();
                }
                if (in_array($action, ['create', 'complete'], true) && $order->leasable) {
                    app(MaintenanceLeasableService::class)->sync($order->leasable);
                }
                if ($ticket) $this->syncConversationStatus($ticket);
                MaintenanceEvent::create(['maintenance_id' => $order->id, 'ticket_id' => $ticket?->id,
                    'request_key' => $data['request_key'], 'payload_hash' => $hash, 'action' => $action,
                    'actor_id' => Auth::id(), 'changes' => $changes]);
                // Reassignment is audited internally, without another public assignment notice.
                if ($ticket && (in_array($action, ['create', 'complete'], true) || ($action === 'assign' && ($changes['status']['from'] ?? null) === 'pending'))) {
                    $ticket->messages()->create(['sender_type' => 'system', 'maintenance_id' => $order->id,
                        'message' => $this->systemMessageText($order, $action)]);
                }
                return $order;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($uploaded);
            throw $e;
        }
    }

    private function systemMessageText(Maintenance $order, string $action): string
    {
        $heading = match ($action) {
            'create' => 'Maintenance created',
            'assign' => 'Maintenance assigned',
            'complete' => 'Maintenance completed',
        };
        $status = match ($action) { 'create' => 'Pending', 'assign' => 'Maintaining', 'complete' => 'Completed' };
        $details = [
            $heading,
            'Reference: '.$order->maintenance_number,
            'Issue: '.$order->title,
            'Quantity: '.($order->qty ?? 1),
            'Status: '.$status,
        ];
        if ($action === 'create') $details[] = 'Description: '.$order->desc;
        $details[] = match ($action) {
            'create' => 'Your reported issue has been recorded. Management will arrange the maintenance work and share updates in this conversation.',
            'assign' => 'A staff member has been assigned. Maintenance work is now in progress. Any further updates can be shared in this conversation.',
            'complete' => 'Management has marked the maintenance work as completed. This conversation is now read-only. Please create a new Help & Support request if you need further assistance.',
        };

        return implode("\n", $details);
    }

    public function conversationNotices(Ticket $ticket, $messages): array
    {
        $systemMessages = $messages->filter(fn ($message) => $message->sender_type === 'system' && $message->maintenance_id);
        if ($systemMessages->isEmpty() || !$this->ready()) return [];
        $orders = Maintenance::where('ticket_id', $ticket->id)->where('source_type', 'customer_service')
            ->whereIn('id', $systemMessages->pluck('maintenance_id')->unique())
            ->with(['attachments' => fn ($query) => $query->where('purpose', 'completion')])->get()->keyBy('id');

        return $systemMessages->mapWithKeys(function (TicketMsg $message) use ($orders) {
            $order = $orders->get($message->maintenance_id);
            $action = match (true) {
                str_starts_with($message->message, 'Maintenance created') => 'create',
                str_starts_with($message->message, 'Maintenance assigned') => 'assign',
                str_starts_with($message->message, 'Maintenance completed') => 'complete',
                default => null,
            };
            if (!$order || !$action) return [];

            $text = str_contains($message->message, "\n") ? $message->message : $this->systemMessageText($order, $action);
            $text = preg_replace('/^(?:Location|Lease):[^\r\n]*\r?\n(?=Status:)/m', '', $text, 1);

            return [$message->id => [
                // Keep saved detailed notices immutable; enrich older one-line notices at read time.
                'text' => $text,
                'status' => match ($action) { 'create' => 'pending', 'assign' => 'maintaining', 'complete' => 'completed' },
                'evidence' => $action === 'complete' && $order->status === 'completed'
                    ? $order->attachments->map(fn ($file) => ['filename' => $file->filename, 'url' => route('maintenance.evidence', $file)])->all() : [],
            ]];
        })->all();
    }

    public function syncConversationStatus(Ticket $ticket): void
    {
        // Call while holding the ticket lock shared by maintenance and chat submissions.
        if ($this->chat->isClosed($ticket)) return;
        $status = $ticket->maintenanceOrder()->value('status');
        if (in_array($status, ['pending', 'maintaining', 'completed'], true) && $ticket->status !== $status) {
            $ticket->update(['status' => $status]);
        }
    }

    private function editEvidence(Maintenance $order, ?Ticket $ticket, array $items, array $removeIds, array &$uploaded): array
    {
        $files = $order->attachments()->where('purpose', 'before')->lockForUpdate()->get();
        $removed = $files->whereIn('id', $removeIds);
        if ($removed->count() !== count($removeIds)) {
            throw ValidationException::withMessages(['remove_attachment_ids' => 'Select saved evidence belonging to this maintenance record.']);
        }
        $kept = $files->whereNotIn('id', $removeIds);
        if ($kept->count() + count($items) > config('customer_service.max_files')) {
            throw ValidationException::withMessages(['attachments' => 'Keep at most 10 evidence files, including saved files.']);
        }
        if ($kept->sum('size') + array_sum(array_map(fn ($item) => $item['file']->getSize(), $items)) > config('customer_service.max_total_kb') * 1024) {
            throw ValidationException::withMessages(['attachments' => 'The combined evidence exceeds 100 MB.']);
        }
        $this->saveEvidence($order, $ticket, 'before', $items, [], $uploaded);
        foreach ($removed as $file) $file->delete();
        if ($removed->isNotEmpty()) {
            $paths = $removed->pluck('path')->all();
            DB::afterCommit(function () use ($paths) {
                try {
                    if (!Storage::disk('local')->delete($paths)) report(new \RuntimeException('Unable to clean up removed maintenance evidence.'));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        }
        $changes = [];
        if ($removed->isNotEmpty()) $changes['evidence_removed'] = ['from' => $removed->pluck('filename')->implode(', '), 'to' => null];
        if ($items) $changes['evidence_added'] = ['from' => null, 'to' => implode(', ', array_map(fn ($item) => $item['file']->getClientOriginalName(), $items))];
        return $changes;
    }

    private function saveEvidence(Maintenance $order, ?Ticket $ticket, string $purpose, array $items, array $sourceIds, array &$uploaded): void
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
        if ($order && in_array($order->source_type, ['manual', 'refund'], true)) {
            $this->authorize($order);
            return;
        }
        abort_unless($order && $order->source_type === 'customer_service' && $order->ticket, 404);
        if (Gate::allows('is-tenant')) {
            $this->chat->authorize($order->ticket, Auth::user());
            abort_unless($file->purpose === 'completion' && $order->status === 'completed', 403);
        } else $this->authorize($order);
    }
}
