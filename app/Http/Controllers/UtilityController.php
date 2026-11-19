<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, Lease, Property, Room, Unit, User, Utility, UtilityService};
use App\Services\{UtilitySetupService, UtilityReadingService, UtilityInvoiceService};
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class UtilityController extends Controller
{
    private function canAccess(string $permission): bool
    {
        return Gate::any(['super-admin', 'utilities.tab'])
            && Gate::any(['super-admin', $permission])
            && Gate::none(['is-tenant', 'is-owner'])
            && !in_array(Auth::user()?->role, [User::ROLE_TENANT, User::ROLE_OWNER], true);
    }

    private function managementAccount(Request $request): ?User
    {
        if (Gate::allows('super-admin')) {
            $id = $request->validate(['management_user_id' => ['nullable', 'ulid']])['management_user_id'] ?? null;
            return $id ? User::whereIn('role', [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN])->findOrFail($id) : null;
        }
        $user = get_effective_user();
        abort_unless($user && in_array($user->role, [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN]), 403);
        return $user;
    }

    public function index(Request $request, UtilitySetupService $setup)
    {
        if (!$this->canAccess('utilities.tab')) return view('errors.403');
        $manager = $this->managementAccount($request);
        $filters = $request->validate(array_merge([
            'type' => ['nullable', Rule::in(UtilityService::TYPES)],
            'search' => ['nullable', 'string', 'max:255'],
        ], UtilityService::filterRules($request->all())));
        $utilityFilterDefaults = array_fill_keys(array_keys(UtilityService::filterRules()), '');
        $type = $filters['type'] ?? 'water';
        $managers = Gate::allows('super-admin')
            ? User::whereIn('role', [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN])->orderBy('name')->get(['id', 'name']) : collect();
        $services = UtilityService::forManagement($manager?->id)->where('type', $type)
            ->search($filters['search'] ?? null)
            ->applyFilters($filters)
            ->with(['parentService', 'latestRecord.invoiceItem.invoice.documentTemplate', 'latestRecord.allocations', 'location' => function (MorphTo $morphTo) {
                $morphTo->morphWith([Unit::class => ['property'], Room::class => ['unit.property']]);
            }])->orderBy('location_type')->orderBy('location_id')->paginate(10)->withQueryString();

        $locationIds = $services->pluck('location_id');
        $leases = $manager && $locationIds->isNotEmpty()
            ? Lease::with('tenant.user')->whereIn('leasable_id', $locationIds)->where('is_current', true)
                ->whereIn('status', ['New', 'Renew'])->whereNull('cancelled_at')->whereNull('checked_out_at')
                ->whereNull('agreement_ended_at')->whereDate('start_date', '<=', today())
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()))->get()
            : collect();
        $tenants = [];
        foreach ($services as $service) {
            // Transfers or ambiguous location ownership require review before exposing tenants.
            $tenants[$service->id] = $service->location && $setup->managerFor($service->location)?->id === $manager?->id
                ? $leases->where('leasable_type', $service->location_type)->where('leasable_id', $service->location_id)
                    ->map(fn ($lease) => $lease->tenant)->filter(fn ($tenant) => $tenant?->user?->name)->unique('id')->values()
                : collect();
        }
        $hasAdvancedFilters = collect(array_intersect_key($filters, $utilityFilterDefaults))->contains(fn ($value) => filled($value));
        return view('adminSide.utilities.index', compact('services', 'manager', 'managers', 'type', 'tenants', 'utilityFilterDefaults', 'hasAdvancedFilters'));
    }

    public function edit(Request $request, UtilityService $utilityService, UtilitySetupService $setup)
    {
        if (!$this->canAccess('utilities.manage meters')) return view('errors.403');
        $manager = $this->managementAccount($request);
        abort_unless($manager && $utilityService->management_user_id === $manager->id, 404);
        $navigation = $request->validate([
            'return_to' => ['nullable', Rule::in(['index', 'readings'])],
            'month' => ['required_if:return_to,readings', 'nullable', 'date_format:Y-m'],
        ]);
        $parents = UtilityService::forManagement($manager->id)->where('type', $utilityService->type)
            ->where('billing_method', 'metered')->where('id', '!=', $utilityService->id)
            ->with('location')->get()->filter(fn ($parent) => $setup->isAncestor($parent, $utilityService));
        $readingRow = ($navigation['return_to'] ?? '') === 'readings'
            ? collect(app(UtilityReadingService::class)->rows($manager, $navigation['month']))->firstWhere('id', $utilityService->id) : null;
        return view('adminSide.utilities.edit', ['service' => $utilityService, 'manager' => $manager, 'parents' => $parents,
            'returnTo' => $navigation['return_to'] ?? 'index', 'returnMonth' => $navigation['month'] ?? null, 'readingRow' => $readingRow]);
    }

    public function update(Request $request, UtilityService $utilityService, UtilitySetupService $setup)
    {
        if (!$this->canAccess('utilities.manage meters')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->managementAccount($request);
        abort_unless($manager && $utilityService->management_user_id === $manager->id, 404);
        $navigation = $request->validate([
            'return_to' => ['nullable', Rule::in(['index', 'readings'])],
            'month' => ['required_if:return_to,readings', 'nullable', 'date_format:Y-m'],
        ]);
        $dateInput = $request->validate([
            'previous_reading_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reading_revision' => ['required_with:previous_reading_date', 'string', 'size:64'],
        ]);
        DB::transaction(function () use ($setup, $utilityService, $request, $navigation, $manager, $dateInput) {
            if (($navigation['return_to'] ?? '') === 'readings' && !empty($dateInput['previous_reading_date'])) {
                app(UtilityReadingService::class)->updatePreviousDate($manager, $utilityService, $navigation['month'], $dateInput['previous_reading_date'], $dateInput['reading_revision']);
            }
            $setup->update($utilityService, $request->all());
        });
        if (($navigation['return_to'] ?? '') === 'readings') {
            return redirect()->route('admin.utilities.readings', ['management_user_id' => $manager->id, 'month' => $navigation['month']])
                ->with('success', 'Utility service updated.');
        }
        return redirect()->route('admin.utilities.index', ['management_user_id' => $manager->id, 'type' => $utilityService->type])
            ->with('success', 'Utility service updated.');
    }

    public function history(Request $request)
    {
        if (!$this->canAccess('utilities.reading history')) return view('errors.403');
        $manager = $this->managementAccount($request);

        $visibleLeases = Invoice::statementLeases($manager?->id, $manager?->id);
        $utilities = Utility::query()
            ->where(fn ($q) => $q->whereHas('utilityService', fn ($s) => $s->forManagement($manager?->id))
                ->orWhere(fn ($legacy) => $legacy->whereNull('utility_service_id')->whereIn('lease_id', $visibleLeases->select('leases.id'))))
            ->with([
                'invoiceItem.invoice.documentTemplate', 'allocations',
                'utilityService.location',
                'lease.tenant.user',
                'lease.leasable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        Property::class => ['owner'],
                        Unit::class => ['property', 'owner'],
                        Room::class => ['unit.property', 'unit.owner'],
                    ]);
                },
            ])
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('adminSide.utilities.history', compact('utilities', 'manager'));
    }

    public function destroy(Request $request, UtilityService $utilityService, UtilitySetupService $setup)
    {
        if (!$this->canAccess('utilities.manage meters')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->managementAccount($request);
        abort_unless($manager && $utilityService->management_user_id === $manager->id, 404);
        $navigation = $request->validate([
            'return_to' => ['nullable', Rule::in(['index', 'readings'])],
            'month' => ['required_if:return_to,readings', 'nullable', 'date_format:Y-m'],
            'readings' => ['nullable', 'json', 'max:1000000'],
            'reading_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $setup->delete($utilityService);
        if (($navigation['return_to'] ?? '') === 'readings') {
            return redirect()->route('admin.utilities.readings', ['management_user_id' => $manager->id, 'month' => $navigation['month']])
                ->withInput($request->only(['readings', 'reading_date']))
                ->with('success', 'Water meter setup deleted. Existing history has been preserved.');
        }
        return redirect()->route('admin.utilities.index', ['management_user_id' => $manager->id, 'type' => $utilityService->type])
            ->with('success', 'Utility service deleted. Reading and billing history has been preserved.');
    }

    public function deleteReadingMeters(Request $request, UtilitySetupService $setup)
    {
        if (!$this->canAccess('utilities.manage meters')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->managementAccount($request);
        if (!$manager) return redirect()->route('admin.utilities.index')->with('error', 'Select a management account first.');
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'selected_services' => ['required', 'json', 'max:100000'],
            'readings' => ['nullable', 'json', 'max:1000000'],
            'reading_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $ids = json_decode($data['selected_services'], true);
        $request->validate(['selected_services' => [function ($attribute, $value, $fail) use ($ids) {
            if (!is_array($ids)) $fail('Select the meters to delete.');
        }]]);
        $count = $setup->deleteWaterMeters($manager, $ids);
        return redirect()->route('admin.utilities.readings', ['management_user_id' => $manager->id, 'month' => $data['month']])
            ->withInput($request->only(['readings', 'reading_date']))
            ->with('success', "Deleted {$count} water meter setup(s). Existing history has been preserved.");
    }

    public function readings(Request $request, UtilityReadingService $readingService)
    {
        if (!$this->canAccess('utilities.enter readings')) return view('errors.403');
        $manager = $this->managementAccount($request);
        if (!$manager) return redirect()->route('admin.utilities.index')->with('error', 'Select a management account first.');
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'preview_invoices' => ['nullable', 'boolean'],
            'building' => ['nullable', 'ulid']]);
        $month = $filters['month'] ?? now(config('app.timezone'))->format('Y-m');
        $readingRows = $readingService->rows($manager, $month);
        foreach ($readingRows as &$row) {
            $row['editUrl'] = route('admin.utilities.edit', ['utilityService' => $row['id'], 'management_user_id' => $manager->id, 'return_to' => 'readings', 'month' => $month]);
            $row['deleteUrl'] = route('admin.utilities.destroy', $row['id']);
        }
        unset($row);
        $readingHierarchy = $readingService->hierarchy($manager, $readingRows);
        $readingDate = today(config('app.timezone'))->toDateString();
        $tariffPlan = $readingService->pricingPlan($manager);
        $invoicePreview = null; $invoicePreviewToken = null;
        if ($request->boolean('preview_invoices')) {
            if (!Gate::any(['super-admin', 'utilities.generate invoices'])) return view('errors.403');
            $invoicePreview = app(UtilityInvoiceService::class)->preview($manager, $month);
            if ($invoicePreview['entries']) {
                $invoicePreviewToken = (string) Str::ulid();
                $plans = $request->session()->get('utility_invoice_previews', []);
                $plans[$invoicePreviewToken] = ['manager' => $manager->id, 'month' => $month,
                    'digest' => $invoicePreview['digest'], 'expires' => now()->addMinutes(30)->timestamp];
                $request->session()->put('utility_invoice_previews', array_slice($plans, -5, null, true));
            }
        }
        return view('adminSide.utilities.readings', compact('manager', 'month', 'readingRows', 'readingHierarchy', 'readingDate', 'tariffPlan', 'invoicePreview', 'invoicePreviewToken'));
    }

    public function generateReadingInvoices(Request $request, UtilityInvoiceService $invoices)
    {
        if (!$this->canAccess('utilities.generate invoices')) return redirect()->back()->with('error', 'You have no permission.');
        if (!Gate::any(['super-admin', 'utilities.generate invoices'])) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->managementAccount($request);
        if (!$manager) return redirect()->route('admin.utilities.index')->with('error', 'Select a management account first.');
        $data = $request->validate(['preview_token' => ['required', 'ulid']]);
        $plan = $request->session()->get('utility_invoice_previews.'.$data['preview_token']);
        if (!$plan || $plan['manager'] !== $manager->id || $plan['expires'] < now()->timestamp) {
            throw ValidationException::withMessages(['invoices' => 'The invoice preview expired or is unavailable. Open a fresh preview.']);
        }
        $walletSummary = null;
        try {
            $count = $invoices->generate($manager, $plan['month'], $plan['digest'], $walletSummary);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['invoices' => 'Invoice numbering changed during generation. No invoices were saved. Open a fresh preview and try again.']);
        }
        $request->session()->forget('utility_invoice_previews.'.$data['preview_token']);
        $response = redirect()->route('admin.utilities.readings', ['management_user_id' => $manager->id, 'month' => $plan['month']])
            ->with('success', "Generated {$count} water invoice(s). Invoiced readings are marked in Utilities and Reading History.");
        if ($walletSummary) $response->with('wallet_auto_payment_summary', $walletSummary);
        return $response;
    }

    public function saveReadings(Request $request, UtilityReadingService $readingService)
    {
        if (!$this->canAccess('utilities.enter readings')) return redirect()->back()->with('error', 'You have no permission.');
        $manager = $this->managementAccount($request);
        if (!$manager) return redirect()->route('admin.utilities.index')->with('error', 'Select a management account first.');
        $context = $request->validate(['building' => ['nullable', 'ulid']]);
        $result = $readingService->save($manager, $request->all());
        return redirect()->route('admin.utilities.readings', ['management_user_id' => $manager->id, 'month' => $request->input('month'),
            'building' => $context['building'] ?? null])
            ->with('utility_reading_draft_saved', ['scope' => Auth::id().':'.$manager->id.':'.$request->input('month'),
                'readingDate' => $request->input('reading_date'), 'rows' => json_decode($request->input('readings'), true)])
            ->with('success', "Saved {$result['count']} water reading(s). No invoices were generated.");
    }
}
