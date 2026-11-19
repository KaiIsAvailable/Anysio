<?php
namespace App\Http\Controllers;

use App\Models\{Maintenance, MaintenanceAttachment, Role, Ticket, UserManagement};
use App\Services\{MaintenanceService, TenantCustomerService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Gate, Storage};
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MaintenanceController extends Controller
{
    public function history(Request $request, MaintenanceService $service, ?string $type = null, ?string $id = null)
    {
        Gate::authorize('maintenance.view');
        abort_unless($service->ready(), 503, 'Maintenance database upgrade is required.');
        $leasable = null;
        if ($type !== null) {
            $class = \App\Services\MaintenanceLeasableService::TYPES[$type];
            $leasable = $class::findOrFail($id);
            abort_unless(app(\App\Services\LeaseManagementResolver::class)->resolveLeasable($leasable)?->id === get_effective_user()?->id, 403);
        }
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $orders = $service->visibleOrders()->when($leasable, fn ($query) => $query->whereMorphedTo('leasable', $leasable))
            ->with(['leasable', 'events.actor', 'attachments'])->orderByDesc('created_at')->orderByDesc('id')->paginate(10);

        return response()->json(['html' => view('components.maintenance.history-content', compact('leasable', 'orders'))->render()]);
    }

    public function index(Request $request, MaintenanceService $service)
    {
        Gate::authorize('maintenance.view');
        $ready = $service->ready();
        $filters = $request->validate(['search' => ['nullable','string','max:255'], 'status' => ['nullable','in:pending,maintaining,completed']]);
        $orders = $ready ? $service->visibleOrders()->with(['leasable', 'lease.leasable', 'lease.tenant.user'])->filter($filters)->latest()->paginate(15)->withQueryString() : null;
        $canCreate = $ready && Gate::allows('maintenance.create');
        $leasableOptions = $canCreate ? $service->manualLeasables() : [];
        $itemOptions = $canCreate ? $service->itemOptions() : [];
        return view('adminSide.maintenance.index', compact('orders', 'ready', 'leasableOptions', 'itemOptions'));
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

    public function storeFromList(Request $request, MaintenanceService $service)
    {
        Gate::authorize('maintenance.create');
        $order = $service->execute($request, null, 'create');
        $request->session()->flash('success', 'Maintenance created successfully.');

        return response()->json(['message' => 'Maintenance created successfully.', 'id' => $order->id]);
    }

    public function show(Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance);
        $maintenance->load(['attachments', 'events.actor', 'leasable', 'lease.leasable',
            'assignedStaff' => fn ($query) => $query->whereHas('user_management',
                fn ($management) => $management->where('user_id', get_effective_user()->id))]);
        $staffPhone = $maintenance->assignedStaff ? $maintenance->assignedStaff->phone : $maintenance->contractor_phone;
        return view('adminSide.maintenance.show', compact('maintenance', 'staffPhone') + $this->assignmentData($maintenance, $service));
    }

    private function assignmentData(Maintenance $maintenance, MaintenanceService $service): array
    {
        $staffOptions = Gate::allows('maintenance.assign') && in_array($maintenance->status, ['pending', 'maintaining'], true)
            ? $service->staffOptions() : [];
        $staffCreateData = null;
        if (Gate::allows('staff.create') && Gate::allows('maintenance.assign') && in_array($maintenance->status, ['pending', 'maintaining'], true)) {
            $manager = get_effective_user();
            $staffCreateData = ['managementId' => UserManagement::where('user_id', $manager->id)->value('id'),
                'managementUserId' => $manager->id,
                'roles' => Role::where('team_id', $manager->id)->where('guard_name', 'web')->get()];
        }
        return compact('staffOptions', 'staffCreateData');
    }

    public function assignment(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance, 'assign');
        abort_unless(in_array($maintenance->status, ['pending', 'maintaining'], true), 409, 'Completed work orders cannot be reassigned.');
        if (!$request->expectsJson()) return redirect()->route('admin.maintenance.work-orders.show', $maintenance);

        return response()->json(['html' => view('components.maintenance.assign-modal', ['maintenance' => $maintenance]
            + $this->assignmentData($maintenance, $service))->render()]);
    }

    public function completion(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance, 'complete');
        abort_unless($maintenance->status === 'maintaining', 409, 'Only a maintaining work order can be completed.');
        if (!$request->expectsJson()) return redirect()->route('admin.maintenance.work-orders.show', $maintenance);

        return response()->json(['html' => view('components.maintenance.complete-modal', compact('maintenance'))->render()]);
    }

    public function edit(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance, 'edit');
        if ($maintenance->status === 'completed' || ($maintenance->ticket && app(TenantCustomerService::class)->isClosed($maintenance->ticket))) {
            if ($request->expectsJson()) return response()->json(['message' => 'Completed or closed maintenance cannot be edited.'], 409);
            return redirect()->route('admin.maintenance.work-orders.show', $maintenance)->with('error', 'Completed or closed maintenance cannot be edited.');
        }

        if (!$request->expectsJson()) return redirect()->route('admin.maintenance.work-orders.show', $maintenance);

        $maintenance->loadMissing(['leasable', 'lease.leasable', 'lease.tenant.user', 'attachments']);
        return response()->json(['html' => view('components.maintenance.edit-modal', compact('maintenance'))->render()]);
    }

    public function update(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance, 'edit');
        try {
            $service->execute($request, $maintenance->ticket, 'edit', $maintenance);
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 409) throw $exception;
            if ($request->expectsJson()) return response()->json(['message' => $exception->getMessage()], 409);

            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            $request->session()->flash('success', 'Maintenance updated successfully.');
            return response()->json(['message' => 'Maintenance updated successfully.']);
        }
        return redirect()->route('admin.maintenance.work-orders.show', $maintenance)->with('success', 'Maintenance updated successfully.');
    }

    public function assign(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance, 'assign');
        $order = $service->execute($request, $maintenance->ticket, 'assign', $maintenance);
        $request->session()->flash('success', 'Contractor assigned successfully.');
        return response()->json(['message' => 'Staff assigned.', 'version' => $order->version]);
    }

    public function complete(Request $request, Maintenance $maintenance, MaintenanceService $service)
    {
        $service->authorize($maintenance, 'complete');
        $order = $service->execute($request, $maintenance->ticket, 'complete', $maintenance);
        $request->session()->flash('success', 'Maintenance completed successfully.');
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
