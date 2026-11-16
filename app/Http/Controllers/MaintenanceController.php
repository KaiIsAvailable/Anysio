<?php
namespace App\Http\Controllers;

use App\Models\{Maintenance, MaintenanceAttachment, Ticket};
use App\Services\{MaintenanceService, TenantCustomerService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Storage};

class MaintenanceController extends Controller
{
    public function index(Request $request, MaintenanceService $service)
    {
        Gate::authorize('maintenance.view');
        $ready = $service->ready();
        $filters = $request->validate(['search' => ['nullable','string','max:255'], 'status' => ['nullable','in:pending,maintaining,completed']]);
        $orders = $ready ? $service->visibleOrders()
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('title','like','%'.$s.'%')->orWhere('maintenance_number','like','%'.$s.'%')->orWhere('location_snapshot','like','%'.$s.'%')))
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(15)->withQueryString() : null;
        return view('adminSide.maintenance.work-orders', compact('orders', 'ready'));
    }

    public function context(Ticket $ticket, MaintenanceService $service, TenantCustomerService $chat)
    {
        $service->authorizeTicket($ticket, 'create');
        return response()->json(['reason' => $service->creationReason($ticket), 'location' => $service->location($ticket),
            'attachments' => $ticket->messages()->get()->filter(fn ($m) => $chat->attachmentPath($m))->map(fn ($m) => [
                'id' => $m->id, 'name' => basename($m->message), 'url' => route('admin.maintenance.attachment', [$ticket, $m]),
            ])->values(), 'cards' => $service->cards($ticket)]);
    }

    public function store(Request $request, Ticket $ticket, MaintenanceService $service)
    {
        $order = $service->execute($request, $ticket, 'create');
        return response()->json(['message' => 'Maintenance created: '.$order->maintenance_number, 'id' => $order->id]);
    }

    public function show(Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance);
        $maintenance->load(['attachments', 'events']);
        return view('adminSide.maintenance.work-order', compact('maintenance'));
    }

    public function assign(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance, 'assign');
        $order = $service->execute($request, $maintenance->ticket, 'assign', $maintenance);
        return response()->json(['message' => 'Contractor assigned.', 'version' => $order->version]);
    }

    public function complete(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance, 'complete');
        $order = $service->execute($request, $maintenance->ticket, 'complete', $maintenance);
        return response()->json(['message' => 'Maintenance completed.', 'version' => $order->version]);
    }

    public function attachment(Request $request, MaintenanceAttachment $attachment, MaintenanceService $service)
    {
        $service->authorizeAttachment($attachment);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);
        $mime = $disk->mimeType($attachment->path);
        $inline = $mime === 'application/pdf' || str_starts_with($mime ?: '', 'image/') || str_starts_with($mime ?: '', 'video/');
        return $disk->response($attachment->path, $attachment->filename, ['Content-Type' => $mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
            $inline && !$request->boolean('download') ? 'inline' : 'attachment');
    }
}
