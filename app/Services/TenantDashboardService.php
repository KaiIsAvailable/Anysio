<?php

namespace App\Services;

use App\Models\{Invoice, Lease, User};
use Carbon\Carbon;

class TenantDashboardService
{
    public function __construct(private SettingService $settings, private TenantCustomerService $customerService) {}

    /** Same unfiltered scope as the tenant invoice index, including non-current leases. */
    public function invoices(User $user)
    {
        abort_unless($user->isTenant(), 403);
        abort_unless($user->tenant, 404, 'Tenant profile not found.');
        return Invoice::query()->whereHas('lease', fn ($q) => $q->where('tenant_id', $user->tenant->id));
    }

    public function data(User $user): array
    {
        // Dashboard totals/reminders exclude void invoices; the historical index keeps its scope.
        // Invoice::isVoid() uses this same status constant. SoftDeletes remains applied by Eloquent.
        $query = $this->invoices($user)->where('status', '!=', Invoice::STATUS_VOID);
        $invoiceTotal = (clone $query)->count();
        // Payment approval updates amount_balance; pending uploads do not reduce it.
        $unpaidCount = (clone $query)->where('amount_balance', '>', 0)->count();
        $activeLeases = $this->customerService->validLeases($user)->with('leasable')->orderBy('end_date')->orderBy('id')->get();
        $creationReason = $this->customerService->recipient($activeLeases)['reason'];
        $today = Carbon::today(config('app.timezone'));
        $banners = [];
        $unresolved = [];
        $settingsByManager = [];
        $resolve = function (Lease $lease) use (&$settingsByManager, &$unresolved) {
            $manager = $this->manager($lease);
            if (!$manager) {
                $unresolved[$lease->id] = true;
                return null;
            }
            return $settingsByManager[$manager->id] ??= $this->settings->getEffectiveSettings($manager);
        };

        foreach ((clone $query)->where('amount_balance', '>', 0)
            ->whereNotNull('due_date')->whereDate('due_date', '<=', $today->copy()->addDays(5))
            ->with(['lease.leasable', 'items.feeType'])->orderBy('due_date')->get() as $invoice) {
            $banners[] = $this->invoiceReminder($invoice, $resolve($invoice->lease), $today);
        }
        foreach ($activeLeases as $lease) {
            $settings = $resolve($lease);
            if (!$settings || !filter_var(data_get($settings, 'pending_renewal_config.is_active', false), FILTER_VALIDATE_BOOLEAN)) continue;
            // Settings UI persists months * 30 / years * 365 in days; cron reads this same value.
            $threshold = (int) data_get($settings, 'pending_renewal_config.value.days', 90);
            $remaining = (int) $today->diffInDays($this->day($lease->end_date), false);
            if ($remaining >= 0 && $remaining <= $threshold) {
                $banners[] = [
                    'type' => 'warning',
                    'message' => "Lease {$lease->leasable_name} ends on {$lease->end_date->format('d M Y')} ({$remaining} days remaining).",
                    'actionLabel' => 'View Lease', 'actionUrl' => route('tenants.leases.show', $lease),
                ];
            }
        }
        if ($unresolved) {
            $banners[] = ['type' => 'info', 'message' => 'Management settings are unavailable or ambiguous for one or more leases. Please contact your property manager about renewal and penalty dates.'];
        }
        return compact('activeLeases', 'creationReason', 'invoiceTotal', 'unpaidCount', 'banners');
    }

    /** Resolve recorded property management and agent-owner relationships, never tenant settings. */
    public function manager(Lease $lease): ?User
    {
        return app(LeaseManagementResolver::class)->resolve($lease);
    }

    public function invoiceReminder(Invoice $invoice, ?array $settings, Carbon $today): array
    {
        $due = $this->day($invoice->due_date);
        $remaining = (int) $today->diffInDays($due, false);
        $name = $invoice->invoice_no ?: $invoice->id;
        $dayLabel = abs($remaining) === 1 ? 'day' : 'days';
        $message = $remaining < 0 ? "Invoice {$name} is overdue by ".abs($remaining)." {$dayLabel}."
            : ($remaining === 0 ? "Invoice {$name} is due today." : "Invoice {$name} is due in {$remaining} {$dayLabel}.");
        $calculator = app(LatePenaltyCalculator::class);
        $config = $calculator->applicableConfig($settings ?? [], $invoice->items);
        if ($config && $invoice->type !== 'penalty') {
            $start = $calculator->startDate($invoice->due_date, $config);
            $days = (int) $calculator->day($today)->diffInDays($start, false);
            $penaltyDayLabel = $days === 1 ? 'day' : 'days';
            $message .= match (true) {
                $days > 0 => " Late payment penalty starts in {$days} {$penaltyDayLabel} (".$start->format('d M Y').').',
                $days === 0 => ' Late payment penalty starts today ('.$start->format('d M Y').').',
                default => ' Late payment penalty applies from '.$start->format('d M Y').'.',
            };
        }
        return ['type' => $remaining < 0 ? 'danger' : 'warning', 'message' => $message,
            'actionLabel' => 'View Invoices', 'actionUrl' => route('tenants.invoices.index')];
    }

    private function day($date): Carbon
    {
        return Carbon::parse($date->format('Y-m-d'), config('app.timezone'))->startOfDay();
    }
}
