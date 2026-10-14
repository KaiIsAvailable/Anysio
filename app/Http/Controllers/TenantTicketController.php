<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TenantCustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TenantTicketController extends Controller
{
    public function index(Request $request, TenantCustomerService $service)
    {
        abort_unless($request->user()->isTenant() || $service->isManager($request->user()), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'ticket' => ['nullable', 'string', 'max:26']]);
        $selectedTicket = isset($data['ticket']) ? Ticket::findOrFail($data['ticket']) : null;
        if ($selectedTicket) $service->authorize($selectedTicket, $request->user());
        $canCreate = $request->user()->isTenant();
        $routePrefix = $canCreate ? 'tenants.customerService' : 'admin.maintenance';
        $search = trim($data['search'] ?? '');
        $tickets = $service->visibleTickets($request->user())->with(['receiver', 'sender', 'lease.leasable'])
            ->when($search !== '', fn ($q) => $q->where('subject', 'like', '%'.$search.'%'))
            ->latest()->orderByDesc('id')->paginate(15)->withQueryString();
        $complaintLeases = $canCreate ? $service->validLeases($request->user())->with('leasable')->orderBy('end_date')->orderBy('id')->get() : collect();
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
        abort_unless($request->user()->isTenant(), 403);
        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'lease_id' => ['nullable', 'string', 'max:26']]);
        if (trim($data['subject']) === '') {
            throw ValidationException::withMessages(['subject' => 'Please enter a subject.']);
        }
        $items = $service->validateCreate($request);
        $uploaded = [];
        try {
            $ticket = DB::transaction(function () use ($request, $service, $data, $items, &$uploaded) {
                $lease = $service->selectLease($request->user(), $data['lease_id'] ?? null);
                $recipient = $service->recipient(collect([$lease]));
                if ($recipient['reason']) {
                    throw ValidationException::withMessages(['subject' => $recipient['reason']]);
                }
                $ticket = Ticket::create([
                    'sender_id' => $request->user()->id,
                    'receive_id' => $recipient['owner_id'],
                    'lease_id' => $lease->id,
                    'category' => 'complain',
                    'subject' => $data['subject'],
                    'status' => 'pending',
                ]);
                $service->append($ticket, $items, $uploaded, $request->user());
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
        $service->authorize($ticket, $request->user());
        $conversation = $this->conversation($ticket, $service);
        if ($request->expectsJson()) {
            if ($request->boolean('fragment')) $conversation['html'] = $this->chatHtml($ticket, $request, $service, $conversation);
            return response()->json($conversation);
        }
        return redirect()->route($this->routePrefix().'.index', ['ticket' => $ticket->id]);
    }

    private function chatHtml(Ticket $ticket, Request $request, TenantCustomerService $service, ?array $conversation = null): string
    {
        $messages = ($conversation ?? $this->conversation($ticket, $service))['messages'];
        $currentRole = $request->user()->role;
        $routePrefix = $this->routePrefix();
        $canAttach = $request->user()->isTenant() || $service->isManager($request->user());
        $canSend = !$service->isClosed($ticket);
        return view('tenantSide.customerService.conversation', compact('ticket', 'messages', 'currentRole', 'canSend', 'routePrefix', 'canAttach'))->render();
    }

    public function reply(Request $request, Ticket $ticket, TenantCustomerService $service)
    {
        $service->authorize($ticket, $request->user());
        abort_if($service->isClosed($ticket), 403, 'This ticket is closed and cannot receive messages.');
        $items = $service->validateBatch($request);
        $uploaded = [];
        try {
            DB::transaction(function () use ($request, $ticket, $service, $items, &$uploaded) {
                $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
                $service->authorize($locked, $request->user());
                abort_if($service->isClosed($locked), 403, 'This ticket is closed and cannot receive messages.');
                $service->append($locked, $items, $uploaded, $request->user());
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($uploaded);
            throw $e;
        }
        return response()->json($this->conversation($ticket->fresh(), $service));
    }

    private function routePrefix(): string
    {
        return auth()->user()->isTenant() ? 'tenants.customerService' : 'admin.maintenance';
    }

    private function conversation(Ticket $ticket, TenantCustomerService $service): array
    {
        $messages = $ticket->messages()->with('creationAudits')->orderBy('created_at')->orderBy('id')->get();
        return [
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'closed' => $service->isClosed($ticket),
                'context' => auth()->user()->isTenant() ? '' : $ticket->sourceLabel(),
                'reply_url' => route($this->routePrefix().'.reply', $ticket),
                'show_url' => route($this->routePrefix().'.show', $ticket),
                'index_url' => route($this->routePrefix().'.index', ['ticket' => $ticket->id]),
            ],
            'messages' => $messages->map(function ($message) use ($ticket, $service) {
                $attachment = $service->attachmentPath($message);
                $local = $message->created_at?->copy()->setTimezone('Asia/Kuala_Lumpur');
                $today = now('Asia/Kuala_Lumpur')->startOfDay();
                $dateLabel = !$local ? null : ($local->isSameDay($today) ? 'Today' : ($local->isSameDay($today->copy()->subDay()) ? 'Yesterday' : $local->format('d M Y')));
                return [
                    'id' => $message->id,
                    'own' => $message->authorId() === auth()->id(),
                    'sender_type' => $message->sender_type,
                    'sender_label' => $message->authorId() ? $message->sender_type : 'Unverified sender',
                    'text' => $attachment ? null : $message->message,
                    'attachment_url' => $attachment ? route($this->routePrefix().'.attachment', [$ticket, $message]) : null,
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
