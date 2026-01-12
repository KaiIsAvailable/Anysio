<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketMsg;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     * Route: customerService.index
     */
    public function index() {
        $userRole = Auth::user()->role;

        if ($userRole === 'ownerAdmin') {
            // OwnerAdmin sees their own created tickets
            $tickets = Ticket::where('sender_id', Auth::id())
                             ->with(['receiver'])
                             ->orderBy('created_at', 'desc')
                             ->get();
        } elseif ($userRole === 'admin') {
            // Admin sees all tickets to grab and resolve
            $tickets = Ticket::with(['sender', 'receiver'])
                             ->orderBy('created_at', 'desc')
                             ->get();
        } else {
            // Other roles see their own tickets
            $tickets = Ticket::where('sender_id', Auth::id())
                             ->with(['receiver'])
                             ->orderBy('created_at', 'desc')
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
    public function grab($id) {
        // Only admin can grab tickets
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $ticket = Ticket::findOrFail($id);

        // Assign the ticket to the current admin
        $ticket->update([
            'receive_id' => Auth::id(),
            'status' => 'in_progress'
        ]);

        return redirect()->back()->with('success', 'Ticket grabbed successfully!');
    }
}