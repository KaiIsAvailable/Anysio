<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketMsg;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     * Route: customerService.index
     */
    public function index() {
        $userRole = Auth::user()->role;

        if (Gate::allows('super-admin')) {
        // Admin 等级 5：看所有人的 Ticket
        $tickets = Ticket::with(['sender', 'receiver'])
                         ->latest()
                         ->get();
        } else {
            // 其他所有角色（agentAdmin, ownerAdmin, tenant 等）：只看自己的
            // 因为你之前的逻辑里除了 admin，其他角色代码都是一模一样的
            $tickets = Ticket::where('sender_id', Auth::id())
                            ->with(['receiver'])
                            ->latest()
                            ->get();
        }

        return view('adminSide.customerService.index', compact('tickets'));
    }

    /**
     * Store a newly created resource in storage.
     * Route: customerService.store
     */
    public function store(Request $request) {
        $request->validate([
            'category' => 'required|string',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        DB::transaction(function() use ($request) {
            // 1. Create Ticket
            $ticket = Ticket::create([
                'sender_id' => Auth::id(),
                'receive_id' => null, // Will be set when admin grabs the ticket
                'category' => $request->category,
                'subject' => $request->subject,
                'status' => 'sent',
            ]);

            // 2. Create Initial Message
            TicketMsg::create([
                'ticket_id' => $ticket->id,
                'sender_type' => Auth::user()->role,
                'message' => $request->message,
            ]);
        });

        return redirect()->route('admin.customerService.index')->with('success', 'Ticket created successfully!');
    }

    /**
     * Display the specified resource.
     * Route: customerService.show
     */
    public function show($id) {
        // Find ticket or fail
        $ticket = Ticket::with(['messages', 'sender', 'receiver'])->findOrFail($id);

        // Security: Ensure only owner or assigned admin can view
        $userRole = Auth::user()->role;
        if ($userRole === 'ownerAdmin' && $ticket->sender_id !== Auth::id()) {
            abort(403, 'Unauthorized: You can only view your own tickets.');
        }
        // Admin can view all tickets

        return view('adminSide.customerService.show', compact('ticket'));
    }

    /**
     * Update the specified resource in storage.
     * We use this for the REPLY functionality.
     * Route: customerService.update
     */
    public function update(Request $request, $id) {
        $request->validate([
            'message' => 'required|string'
        ]);

        $ticket = Ticket::findOrFail($id);

        // Create the reply message
        TicketMsg::create([
            'ticket_id' => $ticket->id,
            'sender_type' => Auth::user()->role,
            'message' => $request->message
        ]);

        // Optional: Update status to indicate there is a new user reply
        $ticket->update(['status' => 'sent']);

        return redirect()->back()->with('success', 'Reply sent!');
    }

    /**
     * Grab a ticket (for admin role)
     * Route: customerService.grab
     */
    public function grab($customerService) {
        // Only admin can grab tickets
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $ticket = Ticket::findOrFail($customerService);

        // Assign the ticket to the current admin
        $ticket->update([
            'receive_id' => Auth::id(),
            'status' => 'in_progress'
        ]);

        return redirect()->back()->with('success', 'Ticket grabbed successfully!');
    }

    /**
     * Close a ticket (for admin role)
     * Route: customerService.close
     */
    public function close($customerService) {
        // Only admin can close tickets
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $ticket = Ticket::findOrFail($customerService);

        // Update ticket status to closed
        $ticket->update([
            'status' => 'closed'
        ]);

        return redirect()->back()->with('success', 'Ticket closed successfully!');
    }

    /**
     * Fetch new messages for a ticket (AJAX endpoint)
     * Route: customerService.newMessages
     */
    public function getNewMessages(Request $request, $ticket) {
        $ticketModel = Ticket::findOrFail($ticket);

        // Security check
        $userRole = Auth::user()->role;
        if ($userRole === 'ownerAdmin' && $ticketModel->sender_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Get last message timestamp from request
        $lastTimestamp = $request->query('last_timestamp');

        // Fetch messages created after the last timestamp
        $query = TicketMsg::where('ticket_id', $ticket)
                          ->orderBy('created_at', 'asc');

        if ($lastTimestamp) {
            // Convert ISO string to Carbon for proper comparison
            try {
                $lastTimestampCarbon = \Carbon\Carbon::parse($lastTimestamp);
                $query->where('created_at', '>', $lastTimestampCarbon);
            } catch (\Exception $e) {
                // If parsing fails, don't filter by timestamp
                Log::warning('Failed to parse timestamp: ' . $lastTimestamp);
            }
        }

        $newMessages = $query->get();

        // Format messages for JSON response
        $formattedMessages = $newMessages->map(function($msg) {
            return [
                'id' => $msg->id,
                'sender_type' => $msg->sender_type,
                'message' => $msg->message,
                'created_at' => $msg->created_at->format('d M Y, H:i'),
                'created_at_iso' => $msg->created_at->toIso8601String(),
            ];
        });

        // Debug logging
        Log::info('AJAX Poll - Ticket: ' . $ticket, [
            'last_timestamp' => $lastTimestamp,
            'new_messages_count' => $formattedMessages->count(),
            'message_ids' => $formattedMessages->pluck('id')->toArray()
        ]);

        return response()->json([
            'messages' => $formattedMessages,
            'count' => $formattedMessages->count()
        ]);
    }
}