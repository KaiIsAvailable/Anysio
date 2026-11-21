<?php
namespace App\Services;

use App\Models\{User, UserManagement, Invoice, RefCodePackage, DocumentTemplate};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class SubscriptionService
{
    /** Resolve renewals on page access as well as login, serialized per account. */
    public function ensureRenewalInvoice(User $user): ?Invoice
    {
        if (!in_array($user->role, [User::ROLE_OWNER_ADMIN, User::ROLE_AGENT_ADMIN], true)) {
            return null;
        }
        $notice = app(SettingService::class)->getSystemEarlyPaymentSettings();

        return DB::transaction(function () use ($user, $notice) {
            $management = UserManagement::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$management || !$management->end_date || $management->subscription_status !== 'active') {
                return null;
            }
            $expired = $management->end_date->lte(today());
            if (!$expired && (!$notice['is_active'] || today()->lt($management->end_date->copy()->subDays($notice['value']['days'])))) {
                return null;
            }
            $package = $management->package;
            if (!$package || $package->price <= 0) {
                return null;
            }
            $invoice = $this->generateInvoice($user, $package);
            if ($expired) {
                $management->update(['subscription_status' => 'pending']);
            }

            return $invoice;
        });
    }

    public function requiresPayment(?UserManagement $management, ?Invoice $invoice): bool
    {
        // An administrator can extend access without settling or voiding the invoice.
        return $invoice !== null && (!$management || !$management->end_date
            || $management->end_date->lte(today()));
    }

    /**
     * Called after a new ownerAdmin/agentAdmin user is created.
     * Creates UserManagement record and generates invoice if package has a price.
     */
    public function setupSubscription(User $user, RefCodePackage $package, string $role): void
    {
        DB::transaction(function () use ($user, $package, $role) {

            [$startDate, $endDate] = $this->resolveDates($package);

            Log::channel('testing')->info('', [
                'start_date' => $startDate?->toDateTimeString(),
                'end_date' => $endDate?->toDateTimeString(),
            ]);

            $subscriptionStatus = $package->price > 0 ? 'pending' : 'active';

            UserManagement::create([
                'user_id'             => $user->id,
                'package_id'          => $package->id,
                'role'                => $role,
                'start_date'          => $startDate,
                'end_date'            => $endDate,
                'extra_lease'         => 0,
                'tot_price'           => $package->price,
                'subscription_status' => $subscriptionStatus,
            ]);

            if ($package->price > 0) {
                $this->generateInvoice($user, $package);
            }
        });
    }

    public function generateInvoice(User $user, RefCodePackage $package): ?Invoice
    {
        return DB::transaction(function () use ($user, $package) {
            $management = UserManagement::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $existing = Invoice::where('user_id', $user->id)->where('context', 'subscription')
                ->whereIn('status', ['unpaid', 'partial', 'overdue'])->latest()->first();
            if ($existing) {
                return $existing;
            }
            $user->setRelation('user_management', $management);

            return $this->createInvoice($user, $package);
        });
    }

    private function createInvoice(User $user, RefCodePackage $package): ?Invoice
    {
        if ($package->price <= 0) {
            return null; 
        }

        $template = DocumentTemplate::where('category', 'invoice')
            ->where('status', 'active')
            ->whereHas('user', function ($query) {
                $query->where('email', 'admin@anysio.com');
            })
            ->first();

        $invoice = Invoice::create([
            'context'        => 'subscription',
            'billable_type'  => UserManagement::class,
            'billable_id'    => $user->user_management->id,
            'user_id'        => $user->id,
            'invoice_no'     => $this->generateInvoiceNo(),
            'document_template_id' => $template?->id,
            'type'           => 'subscription',
            'period'         => now()->startOfMonth()->toDateString(),
            'due_date'       => $user->user_management->subscription_status === 'active'
                ? $user->user_management->end_date?->toDateString() ?? now()->addDays(7)->toDateString()
                : now()->addDays(7)->toDateString(),
            'total_amount'   => $package->price,
            'amount_paid'    => 0,
            'amount_balance' => $package->price,
            'status'         => 'unpaid',
        ]);

        $invoice->items()->create([
            'fee_type_id' => null,
            'description' => "{$package->name} Subscription ({$package->price_mode})",
            'amount'      => $package->price,
        ]);

        return $invoice;
    }

    private function resolveDates(RefCodePackage $package): array
    {
        Log::channel('testing')->info('Package Debug:', $package->toArray());

        if (($package->commission_rate ?? 0) != 0) {
            return [null, null];
        }

        $start = now();
        $end = strtolower($package->price_mode) === 'monthly'
            ? $start->copy()->addMonth()
            : $start->copy()->addYear();

        return [$start, $end];
    }

    private function generateInvoiceNo(): string
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        return app(DocumentSequenceService::class)->generateInvoiceNumber($admin);
    }
}
