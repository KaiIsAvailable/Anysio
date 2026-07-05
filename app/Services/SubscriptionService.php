<?php
namespace App\Services;

use App\Models\{User, UserManagement, UserPayment, RefCodePackage};
use App\Events\SubscriptionInvoiceCreated;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SubscriptionService
{
    /**
     * Called after a new ownerAdmin/agentAdmin user is created.
     * Creates UserManagement record and generates invoice if package has a price.
     */
    public function setupSubscription(User $user, RefCodePackage $package, string $role): void
    {
        DB::transaction(function () use ($user, $package, $role) {

            [$startDate, $endDate] = $this->resolveDates($package);

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

    public function generateInvoice(User $user, RefCodePackage $package): UserPayment
    {
        $invoice = UserPayment::create([
            'user_id'      => $user->id,
            'ref_code'     => $package->ref_code,
            'invoice_no'   => $this->generateInvoiceNo(),
            'payment_type' => 'subscription',
            'amount_due'   => $package->price,   // cents
            'amount_paid'  => 0,
            'status'       => 'unpaid',
        ]);
        
        return $invoice;
    }

    private function resolveDates(RefCodePackage $package): array
    {
        if (($package->commission_rate ?? 0) <= 0) {
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
        $prefix = 'SUB-' . now()->format('Y');
        $last = UserPayment::where('invoice_no', 'like', $prefix . '%')
            ->orderByDesc('invoice_no')
            ->value('invoice_no');

        $next = $last ? (int) substr($last, -5) + 1 : 1;
        return $prefix . now()->format('md') . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}