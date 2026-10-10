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
        abort_unless($request->user()->isTenant(), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim($data['search'] ?? '');
        $tickets = Ticket::where('sender_id', $request->user()->id)->with('receiver')
            ->when($search !== '', fn ($q) => $q->where('subject', 'like', '%'.$search.'%'))
            ->latest()->orderByDesc('id')->paginate(15)->withQueryString();
        $recipient = $service->recipient($service->validLeases($request->user())->with('leasable')->get());
        $creationReason = $recipient['reason'];
        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('tenantSide.customerService.table', compact('tickets'))->render(),
                'creation_reason' => $creationReason,
            ]);
        }
        return view('tenantSide.customerService.index', compact('tickets', 'creationReason', 'search'));
    }

    public function store(Request $request, TenantCustomerService $service)
    {
        abort_unless($request->user()->isTenant(), 403);
        $data = $request->validate(['subject' => ['required', 'string', 'max:255']]);
        if (trim($data['subject']) === '') {
            throw ValidationException::withMessages(['subject' => 'Please enter a subject.']);
        }
        $ticket = DB::transaction(function () use ($request, $service, $data) {
            $recipient = $service->recipient($service->validLeases($request->user())->lockForUpdate()->get());
            if ($recipient['reason']) {
                throw ValidationException::withMessages(['subject' => $recipient['reason']]);
            }
            return Ticket::create([
                'sender_id' => $request->user()->id,
                'receive_id' => $recipient['owner_id'],
                'category' => 'complain',
                'subject' => $data['subject'],
                'status' => 'pending',
            ]);
        });
        return response()->json($this->conversation($ticket, $service), 201);
    }

    public function show(Request $request, Ticket $ticket, TenantCustomerService $service)
    {
        $service->authorize($ticket, $request->user());
        $conversation = $this->conversation($ticket, $service);
        if ($request->expectsJson()) {
            return response()->json($conversation);
        }
        $messages = $conversation['messages'];
        $currentRole = $request->user()->role;
        $canSend = !$service->isClosed($ticket);
        return view('tenantSide.customerService.show', compact('ticket', 'messages', 'currentRole', 'canSend'));
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
                $service->append($locked, $items, $uploaded);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($uploaded);
            throw $e;
        }
        return response()->json($this->conversation($ticket->fresh(), $service));
    }

    private function conversation(Ticket $ticket, TenantCustomerService $service): array
    {
        $messages = $ticket->messages()->orderBy('created_at')->orderBy('id')->get();
        return [
            'ticket' => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'closed' => $service->isClosed($ticket),
                'reply_url' => route('tenants.customerService.reply', $ticket),
                'show_url' => route('tenants.customerService.show', $ticket),
            ],
            'messages' => $messages->map(function ($message) use ($ticket, $service) {
                $attachment = $service->attachmentPath($message);
                return [
                    'id' => $message->id,
                    'own' => $message->sender_type === 'tenant',
                    'sender_type' => $message->sender_type,
                    'text' => $attachment ? null : $message->message,
                    'attachment_url' => $attachment ? route('tenants.customerService.attachment', [$ticket, $message]) : null,
                    'extension' => $attachment ? pathinfo($attachment, PATHINFO_EXTENSION) : null,
                    'created_at' => $message->created_at?->format('d M Y H:i'),
                ];
            })->values()->all(),
        ];
    }
}
