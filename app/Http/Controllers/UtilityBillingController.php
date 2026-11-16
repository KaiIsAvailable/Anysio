<?php
namespace App\Http\Controllers;

use App\Models\{User, Utility, UtilityService, UtilityAllocation, Lease};
use App\Services\{UtilityBillingAccess, UtilityTariffManagementService, UtilityMeterEventService, UtilityAllocationService, UtilityReadingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};

class UtilityBillingController extends Controller
{
    private function canAccess(string $permission): bool
    {
        return Gate::any(['super-admin', 'utilities.tab']) && Gate::any(['super-admin', $permission])
            && Gate::none(['is-tenant', 'is-owner'])
            && !in_array(Auth::user()?->role, [User::ROLE_TENANT, User::ROLE_OWNER], true);
    }

    private function manager(Request $request, Utility $reading): User
    {
        $manager = app(UtilityBillingAccess::class)->manager($request);
        abort_unless($manager, 403);
        app(UtilityBillingAccess::class)->meter($manager, $reading->utilityService);
        return $manager;
    }

    public function show(Request $request, Utility $utility)
    {
        if (Auth::user()?->role !== User::ROLE_TENANT && !$this->canAccess('utilities.reading history')) return view('errors.403');
        $reading = $utility->load(['utilityService.location', 'allocations.lease.tenant.user', 'allocations.invoiceItem.invoice',
            'invoiceItem.invoice', 'corrections.originalItem.invoice', 'corrections.replacementItem.invoice']);
        $isTenant = Auth::user()?->role === User::ROLE_TENANT;
        if ($isTenant) {
            $leaseIds = Lease::whereHas('tenant', fn ($q) => $q->where('user_id', Auth::id()))->pluck('id');
            $allocations = $reading->allocations->whereIn('lease_id', $leaseIds)->values();
            abort_unless(($reading->utilityService->billing_scope !== 'shared' && $reading->lease_id && $leaseIds->contains($reading->lease_id)) || $allocations->isNotEmpty(), 404);
            $manager = null; $canManage = false; $leaseOptions = []; $submeterOptions = []; $leaseData = []; $submeterUsage = []; $allocationHistory = collect();
            $corrections = $reading->corrections->filter(fn ($c) => $c->originalItem?->invoice?->lease_id && $leaseIds->contains($c->originalItem->invoice->lease_id));
        } else {
            $manager = $this->manager($request, $reading);
            $allocations = $reading->allocations; $corrections = $reading->corrections;
            $canManage = Gate::any(['super-admin', 'utilities.manage charges']);
            [$from, $to] = UtilityAllocationService::interval($reading);
            $eligible = $from && $to ? app(UtilityAllocationService::class)->leases($manager, $reading) : collect();
            $leaseOptions = $eligible->mapWithKeys(fn ($l) => [$l->id => ($l->tenant?->user?->name ?? 'Tenant').' - '.$l->id])->all();
            $leaseData = $eligible->map(fn ($l) => ['id' => $l->id, 'from' => max($from, $l->start_date->toDateString()),
                'to' => min(array_filter([$to, $l->end_date?->toDateString(), $l->checked_out_at?->toDateString(), $l->agreement_ended_at?->toDateString()]))])->values()->all();
            $submeters = Utility::whereHas('utilityService', fn ($q) => $q->forManagement($manager->id)->where('parent_service_id', $reading->utility_service_id))
                ->whereDate('period', $reading->period)->whereNotNull('consumption')->with('utilityService.location')->get()
                ->filter(fn ($r) => $r->utilityService->location && app(\App\Services\UtilitySetupService::class)->managerFor($r->utilityService->location)?->id === $manager->id);
            $submeterOptions = $submeters->mapWithKeys(fn ($r) => [$r->id => $r->utilityService->location_label.' - '.$r->consumption.' m3'])->all();
            $submeterUsage = $submeters->mapWithKeys(fn ($r) => [$r->id => $r->consumption])->all();
            $allocationHistory = UtilityAllocation::where('utility_id', $reading->id)->with('lease.tenant.user')->orderByDesc('revision')->get();

        }
        $proposal = !$isTenant && $request->query('proposal') ? $reading->corrections()->where('status', 'proposed')->findOrFail($request->query('proposal')) : null;
        return view('adminSide.utilities.charge', compact('reading', 'isTenant', 'manager', 'canManage', 'allocations', 'corrections', 'leaseOptions', 'submeterOptions', 'leaseData', 'submeterUsage', 'allocationHistory', 'proposal'));
    }

    public function event(Request $request, UtilityService $utilityService, UtilityBillingAccess $access, UtilityMeterEventService $events)
    {
        if (!$this->canAccess('utilities.manage meters')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $access->manager($request); abort_unless($manager, 403);
        $events->create($manager, $utilityService, $request->all());
        return back()->with('success', 'Meter event recorded. Select it when saving the next affected reading.');
    }

    public function allocate(Request $request, Utility $utility, UtilityAllocationService $allocation)
    {
        if (!$this->canAccess('utilities.manage charges')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->manager($request, $utility);
        $data = $request->validate(['allocations' => ['required', 'json']]);
        $input = $request->all(); $input['rows'] = json_decode($data['allocations'], true);
        $allocation->allocate($manager, $utility, $input);
        return back()->with('success', 'Shared charge allocated. The shares total the source charge exactly. Review the invoice preview before generation.');
    }

    public function revoke(Request $request, Utility $utility, UtilityAllocationService $allocation)
    {
        if (!$this->canAccess('utilities.manage charges')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->manager($request, $utility);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $allocation->revoke($manager, $utility, $data['reason']);
        return back()->with('success', 'Unbilled allocations revoked and retained in history. The source charge can now be edited.');
    }

    public function adjust(Request $request, Utility $utility, UtilityReadingService $readings)
    {
        if (!$this->canAccess('utilities.manage charges')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->manager($request, $utility);
        app(UtilityTariffManagementService::class)->authorizeCustom($manager, 'utilities.manage charges');
        $data = $request->validate(['amount' => ['required', 'numeric', 'between:-21474836.47,21474836.47', 'decimal:0,2'],
            'reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $row = collect($readings->rows($manager, $utility->period->format('Y-m')))->firstWhere('id', $utility->utility_service_id);
        abort_unless($row, 404);
        $adjustments = array_map(fn ($a) => ['reason' => $a['reason'], 'amount' => \App\Services\UtilityDecimal::format($a['amount'], 2)], data_get($utility->calculation_details, 'adjustments', []));
        $adjustments[] = $data;
        $entry = ['id' => $row['id'], 'previous' => $row['previous'], 'current' => $row['current'], 'previousDate' => $row['previousDate'],
            'rate' => $row['rate'], 'revision' => $row['revision'], 'eventId' => $row['eventId'],
            'billAmount' => $row['billAmount'], 'billReference' => $row['billReference'], 'adjustments' => $adjustments];
        $readings->save($manager, ['month' => $utility->period->format('Y-m'), 'reading_date' => $utility->curr_reading_date?->toDateString() ?? today()->toDateString(), 'readings' => json_encode([$entry])]);
        return back()->with('success', 'Authorized adjustment recorded in the charge snapshot.');
    }

    public function proposeCorrection(Request $request, Utility $utility, \App\Services\UtilityCorrectionService $service)
    {
        if (!$this->canAccess('utilities.correct invoices')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->manager($request, $utility);
        $proposal = $service->propose($manager, $utility, $request->all());
        return redirect()->route('admin.utilities.charges.show', ['utility' => $utility, 'management_user_id' => $manager->id, 'proposal' => $proposal->id]);
    }

    public function confirmCorrection(Request $request, Utility $utility, \App\Models\UtilityCorrection $correction, \App\Services\UtilityCorrectionService $service)
    {
        if (!$this->canAccess('utilities.correct invoices')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->manager($request, $utility);
        $invoice = $service->confirm($manager, $utility, $correction, $request->all());
        return redirect()->route('admin.utilities.charges.show', ['utility' => $utility, 'management_user_id' => $manager->id])
            ->with('success', 'Correction issued as '.$invoice->invoice_no.'. The original invoice and charge snapshot are preserved.');
    }
}
