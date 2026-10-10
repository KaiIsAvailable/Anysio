<?php

namespace App\Http\Controllers;

use App\Models\{Ticket, TicketMsg};
use App\Services\TenantCustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketMsgController extends Controller
{
    public function attachment(Request $request, Ticket $ticket, TicketMsg $message, TenantCustomerService $service)
    {
        $service->authorize($ticket, $request->user());
        abort_unless($message->ticket_id === $ticket->id, 404);
        $path = $service->attachmentPath($message);
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $mime = Storage::disk('local')->mimeType($path);
        $inline = str_starts_with($mime ?: '', 'image/') || str_starts_with($mime ?: '', 'video/');
        $headers = [
            'Content-Type' => $mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
        return Storage::disk('local')->response($path, basename($path), $headers,
            $inline && !$request->boolean('download') ? 'inline' : 'attachment');
    }
}
