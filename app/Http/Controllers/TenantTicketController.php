<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, User};
use App\Services\TenantCustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TenantTicketController extends Controller
{
    public function index(Request $request, TenantCustomerService $service)
    {
        abort_unless(Gate::allows('is-tenant') || $service->canUseMaintenance($this->actor()), 403);
        if ($request->boolean('sync')) return $this->sync($request, $service);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'ticket' => ['nullable', 'string', 'max:26']]);
        $selectedTicket = isset($data['ticket']) ? Ticket::findOrFail($data['ticket']) : null;
        if ($selectedTicket) $service->authorize($selectedTicket, $this->actor());
        $canCreate = Gate::allows('is-tenant');
        $routePrefix = $canCreate ? 'tenants.customerService' : 'admin.maintenance';
        $search = trim($data['search'] ?? '');
        $tickets = $service->visibleTickets($this->actor())->with(['receiver', 'sender', 'lease.leasable'])
            ->withMax('messages', 'created_at')
            ->when($search !== '', fn ($q) => $q->where('subject', 'like', '%'.$search.'%'))
            ->orderByRaw('COALESCE(messages_max_created_at, tickets.created_at) DESC')
            ->orderByDesc('tickets.id')->paginate(15)->withQueryString();
        $tickets->each(fn ($ticket) => $ticket->setAttribute('unread_count', $service->unreadCount($ticket, $this->actor())));
        $complaintLeases = $canCreate ? $service->validLeases($this->actor())->with('leasable')->orderBy('end_date')->orderBy('id')->get() : collect();
        $recipient = $service->recipient($complaintLeases);
        $creationReason = $recipient['reason'];
        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('tenantSide.customerService.table', compact('tickets', 'canCreate', 'routePrefix'))->render(),
                'creation_reason' => $creationReason,
            ]);
        }
        $chatHtml = $selectedTicket ? $this->chatHtml($selectedTicket, $request, $service) : '';
        return view('tenantSide.customerService.index', compact('tickets', 'creationReason', 'search', 'selectedTicket', 'chatHtml', 'complaintLeases', 'canCreate', 'routePrefix'));
    }

    public function store(Request $request, TenantCustomerService $service)
    {
        abort_unless(Gate::allows('is-tenant'), 403);
        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'lease_id' => ['nullable', 'string', 'max:26']]);
        if (trim($data['subject']) === '') {
            throw ValidationException::withMessages(['subject' => 'Please enter a subject.']);
        }
        $items = $service->validateCreate($request);
        $uploaded = [];
        try {
            $ticket = DB::transaction(function () use ($request, $service, $data, $items, &$uploaded) {
                $lease = $service->selectLease($this->actor(), $data['lease_id'] ?? null);
                $recipient = $service->recipient(collect([$lease]));
                if ($recipient['reason']) {
                    throw ValidationException::withMessages(['subject' => $recipient['reason']]);
                }
                $ticket = Ticket::create([
                    'sender_id' => Auth::id(),
                    'receive_id' => $recipient['owner_id'],
                    'lease_id' => $lease->id,
                    'category' => 'complain',
                    'subject' => $data['subject'],
                    'status' => 'pending',
                ]);
                $service->append($ticket, $items, $uploaded, $this->actor());
                return $ticket;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($uploaded);
            throw $e;
        }
        return response()->json($this->conversation($ticket, $service), 201);
    }

    public function show(Request $request, Ticket $ticket, TenantCustomerService $service)
    {
        $service->authorize($ticket, $this->actor());
        $conversation = $this->conversation($ticket, $service);
        if ($request->expectsJson()) {
            if ($request->boolean('fragment')) $conversation['html'] = $this->chatHtml($ticket, $request, $service, $conversation);
            return response()->json($conversation);
        }
        return redirect()->route($this->routePrefix().'.index', ['ticket' => $ticket->id]);
    }

    private function chatHtml(Ticket $ticket, Request $request, TenantCustomerService $service, ?array $conversation = null): string
    {
        $conversation ??= $this->conversation($ticket, $service);
        $messages = $conversation['messages'];
        $syncCursor = $conversation['cursor'];
        $currentRole = $this->actor()->role;
        $routePrefix = $this->routePrefix();
        $canSend = $service->canReply($ticket, $this->actor());
        $canAttach = Gate::any(['is-tenant', 'owner-admin', 'is-owner']);
        return view('tenantSide.customerService.conversation', compact('ticket', 'messages', 'currentRole', 'canSend', 'routePrefix', 'canAttach', 'syncCursor'))->render();
    }

    public function reply(Request $request, Ticket $ticket, TenantCustomerService $service)
    {
        $service->authorize($ticket, $this->actor());
        abort_if($service->isClosed($ticket), 403, 'This ticket is closed and cannot receive messages.');
        abort_unless($service->canReply($ticket, $this->actor()), 403, 'You do not have permission to reply or upload attachments.');
        $items = $service->validateBatch($request);
        $uploaded = [];
        try {
            DB::transaction(function () use ($request, $ticket, $service, $items, &$uploaded) {
                $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                $service->authorize($locked, $this->actor());
                abort_if($service->isClosed($locked), 403, 'This ticket is closed and cannot receive messages.');
                abort_unless($service->canReply($locked, $this->actor()), 403, 'You do not have permission to reply or upload attachments.');
                $service->append($locked, $items, $uploaded, $this->actor());
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($uploaded);
            throw $e;
        }
        return response()->json($this->conversation($ticket->fresh(), $service, null, false));
    }

    public function markRead(Request $request, Ticket $ticket, TenantCustomerService $service)
    {
        $service->authorize($ticket, $this->actor());
        $data = $request->validate([
            'message_ids' => ['required', 'array', 'min:1', 'max:500'],
            'message_ids.*' => ['required', 'string', 'size:26', 'distinct'],
        ]);
        $receipts = DB::transaction(function () use ($ticket, $request, $service, $data) {
            // Serialize readers and recheck access inside the transaction.
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $service->authorize($locked, $this->actor());
            $messages = $locked->messages()->whereIn('id', $data['message_ids'])
                ->with('creationAudits')->orderBy('id')->lockForUpdate()->get();
            $receipts = [];
            foreach ($messages as $message) {
                if (!$service->isIncoming($message, $locked, $this->actor())) continue;
                if ($message->status === 'unread' && !$message->read_at && !$message->read_by) {
                    $message->status = 'read';
                    $message->read_by = Auth::id();
                    $message->read_at = now();
                    $message->save();
                }
                if ($message->status === 'read') $receipts[] = ['id' => $message->id, 'status' => 'read'];
            }
            return $receipts;
        });
        return response()->json([
            'messages' => $receipts,
            'ticket_id' => $ticket->id,
            'unread_count' => $service->unreadCount($ticket, $this->actor()),
        ]);
    }

    private function saveSnapshot(?string $ticket, array $messages, array $list = []): string
    {
        $token = (string) \Illuminate\Support\Str::uuid();
        // Immutable snapshots tolerate lost responses and commits out of ULID order.
        \Illuminate\Support\Facades\Cache::put('ticket-sync:'.$this->actor()->id.':'.$token,
            compact('ticket', 'messages', 'list'), now()->addMinutes(2));
        return $token;
    }

    private function sync(Request $request, TenantCustomerService $service)
    {
        $data = $request->validate([
            'ticket' => ['nullable', 'string', 'max:26'], 'cursor' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $ticket = !empty($data['ticket']) ? Ticket::findOrFail($data['ticket']) : null;
        if ($ticket) $service->authorize($ticket, $this->actor());
        $previous = empty($data['cursor']) ? [] : \Illuminate\Support\Facades\Cache::get(
            'ticket-sync:'.$this->actor()->id.':'.$data['cursor'], []);
        $known = ($previous['ticket'] ?? null) === $ticket?->id ? ($previous['messages'] ?? []) : [];
        // History metadata only; bodies are fetched only for unseen IDs.
        $states = $ticket ? $ticket->messages()->pluck('status', 'id')->all() : [];
        $newIds = array_keys(array_diff_key($states, $known));
        $messages = $ticket && $newIds ? $ticket->messages()->whereIn('id', $newIds)
            ->with('creationAudits')->orderBy('created_at')->orderBy('id')->get() : collect();
        $conversation = $ticket ? $this->conversation($ticket, $service, $messages, false) : ['messages' => []];
        $receipts = [];
        foreach ($states as $id => $status) {
            if (array_key_exists($id, $known) && $known[$id] !== $status) $receipts[] = ['id' => $id, 'status' => $status];
        }
        $search = trim($data['search'] ?? '');
        $tickets = $service->visibleTickets($this->actor())->with(['receiver', 'sender', 'lease.leasable'])
            ->withMax('messages', 'created_at')
            ->when($search !== '', fn ($q) => $q->where('subject', 'like', '%'.$search.'%'))
            ->orderByRaw('COALESCE(messages_max_created_at, tickets.created_at) DESC')->orderByDesc('tickets.id')
            ->paginate(15)->appends($request->except(['sync', 'cursor']));
        $service->loadUnreadCounts($tickets->getCollection(), $this->actor());
        $canCreate = Gate::allows('is-tenant');
        $routePrefix = $this->routePrefix();
        $list = ['search' => $search, 'page' => $tickets->currentPage(), 'total' => $tickets->total(),
            'rows' => $tickets->map(fn ($row) => [$row->id, $row->subject, $row->status, $row->unread_count,
                $row->messages_max_created_at, $canCreate ? '' : $row->sourceLabel()])->all()];
        $conversation['receipts'] = $receipts;
        $conversation['list_html'] = $list === ($previous['list'] ?? []) ? null
            : view('tenantSide.customerService.table', compact('tickets', 'canCreate', 'routePrefix'))->render();
        $conversation['cursor'] = $this->saveSnapshot($ticket?->id, $states, $list);
        // Bound cache growth even for database cache stores which retain expired
        // rows. A lost response falls back to a deduplicated resync, never a gap.
        if (!empty($data['cursor'])) \Illuminate\Support\Facades\Cache::forget('ticket-sync:'.$this->actor()->id.':'.$data['cursor']);
        return response()->json($conversation)->header('Cache-Control', 'no-store');
    }

    private function actor(): User
    {
        $user = Auth::user();
        if (!$user instanceof User) abort(403);
        return $user;
    }

    private function routePrefix(): string
    {
        return Gate::allows('is-tenant') ? 'tenants.customerService' : 'admin.maintenance';
    }

    private function conversation(Ticket $ticket, TenantCustomerService $service, $messages = null, bool $snapshot = true): array
    {
        $messages ??= $ticket->messages()->with('creationAudits')->orderBy('created_at')->orderBy('id')->get();
        $messages->each(fn ($message) => $message->setRelation('ticket', $ticket));
        $routePrefix = $this->routePrefix();
        $incomingIds = array_fill_keys($service->incomingIds($messages, $ticket, $this->actor()), true);
        return [
            'cursor' => $snapshot ? $this->saveSnapshot($ticket->id, $messages->pluck('status', 'id')->all()) : null,
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'closed' => $service->isClosed($ticket),
                'can_send' => $service->canReply($ticket, $this->actor()),
                'context' => $routePrefix === 'tenants.customerService' ? '' : $ticket->sourceLabel(),
                'reply_url' => route($routePrefix.'.reply', $ticket),
                'show_url' => route($routePrefix.'.show', $ticket),
                'mark_read_url' => route($routePrefix.'.markRead', $ticket),
                'index_url' => route($routePrefix.'.index', ['ticket' => $ticket->id]),
            ],
            'messages' => $messages->map(function ($message) use ($ticket, $service, $routePrefix, $incomingIds) {
                $attachment = $service->attachmentPath($message);
                $local = $message->created_at?->copy()->setTimezone('Asia/Kuala_Lumpur');
                $today = now('Asia/Kuala_Lumpur')->startOfDay();
                $dateLabel = !$local ? null : ($local->isSameDay($today) ? 'Today' : ($local->isSameDay($today->copy()->subDay()) ? 'Yesterday' : $local->format('d M Y')));
                return [
                    'id' => $message->id,
                    'own' => $message->authorId() === Auth::id(),
                    'incoming' => isset($incomingIds[$message->id]),
                    'status' => $message->status,
                    'read_at' => $message->read_at?->toIso8601String(),
                    'read_by' => $message->read_by,
                    'sender_type' => $message->sender_type,
                    'sender_label' => $message->authorId() ? $message->sender_type : 'Unverified sender',
                    'text' => $attachment ? null : $message->message,
                    'attachment_url' => $attachment ? route($routePrefix.'.attachment', [$ticket, $message]) : null,
                    'extension' => $attachment ? strtolower(pathinfo($attachment, PATHINFO_EXTENSION)) : null,
                    'filename' => $attachment ? basename($attachment) : null,
                    'sent_at' => $local?->toIso8601String(),
                    'date_key' => $local?->format('Y-m-d'),
                    'date_label' => $dateLabel,
                    'created_at' => $local?->format('H:i'),
                ];
            })->values()->all(),
        ];
    }
}
