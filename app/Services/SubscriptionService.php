<?php
namespace App\Services;

use App\Models\{User, UserManagement, Invoice, RefCodePackage, DocumentSequence};
use Illuminate\Support\Facades\DB;

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

    public function generateInvoice(User $user, RefCodePackage $package): Invoice
    {
        $invoice = Invoice::create([
            'context'        => 'subscription',
            'billable_type'  => UserManagement::class,
            'billable_id'    => $user->user_management->id,
            'user_id'        => $user->id,
            'invoice_no'     => $this->generateInvoiceNo(),
            'type'           => 'subscription',
            'period'         => now()->startOfMonth()->toDateString(),
            'due_date'       => now()->addDays(7)->toDateString(),
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
        $admin = User::where('role', 'admin')->firstOrFail();
        $sequence = DocumentSequence::firstOrCreate(
            [
                'user_id' => $admin->id,
                'category' => 'invoice',
            ],
            [
                'sequence' => [
                    'prefix' => 'INV',
                    'next_number' => 1,
                    'padding' => 5,
                ],
            ]
        );
        $config = $sequence->sequence;
        $invoiceNo = $config['prefix'] . str_pad($config['next_number'], $config['padding'], '0', STR_PAD_LEFT);
        $config['next_number']++;
        $sequence->sequence = $config;
        $sequence->save();

        return $invoiceNo;
    }
}