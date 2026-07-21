<?php

namespace App\Services;

use App\Models\{
    Lease,
    LeaseCharge,
    Property,
    Unit,
    Room,
    User
};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LeaseService
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {
    }

    /**
     * Process New, Renew, Check Out and End Agreement.
     */
    public function process(User $user, array $data): Lease
    {
        return DB::transaction(function () use ($user, $data) {

            $status = $data['status'];

            // Get the previous lease when required
            $oldLease = $this->getOldLease($data);

            // Only a NEW lease consumes the subscription limit
            if ($status === 'New') {
                $this->checkLeaseLimit($user);
            }

            // Resolve property / unit / room and tenant
            $context = $this->resolveLeaseContext($data, $oldLease);

            // For Renew / Check Out / End Agreement,
            // the previous lease is no longer current
            if ($oldLease) {
                $oldLease->update([
                    'is_current' => false,
                ]);
            }

            // Update property / unit / room status
            $this->updateLeasableStatus(
                $context['leasable'],
                $status
            );

            // Create the new lease
            $newLease = $this->createLease(
                $data,
                $oldLease,
                $context
            );

            // New and Renew require financial charges
            if (in_array($status, ['New', 'Renew'])) {

                $this->createLeaseCharges(
                    $newLease,
                    $data
                );

                // Generate first rent invoice
                $this->invoiceService->createInvoice(
                    $newLease,
                    Carbon::parse($newLease->start_date)
                );
            }

            Log::info('Lease processed successfully.', [
                'status' => $status,
                'lease_id' => $newLease->id,
                'old_lease_id' => $oldLease?->id,
            ]);

            return $newLease;
        });
    }

    /**
     * Get the old lease for Renew, Check Out and End Agreement.
     */
    protected function getOldLease(array $data): ?Lease
    {
        if ($data['status'] === 'New') {
            return null;
        }

        return Lease::with('leasable')
            ->findOrFail($data['lease_id']);
    }

    /**
     * Check whether the user can create another lease.
     */
    protected function checkLeaseLimit(User $user): void
    {
        // Admin has no subscription lease limit
        if ($user->role === 'admin') {
            return;
        }

        $management = $user->user_management;

        if (!$management || !$management->package) {
            throw ValidationException::withMessages([
                'error' => 'You do not have an active subscription package.',
            ]);
        }

        $package = $management->package;

        $baseLeaseLimit = (int) $package->base_lease;
        $extraLeaseLimit = (int) $management->extra_lease;

        $totalLeaseLimit = $baseLeaseLimit + $extraLeaseLimit;

        $currentLeaseCount = Lease::where('is_current', true)
            ->whereIn('status', [
                'New',
                'Renew',
                'Check Out',
            ])
            ->whereHas('tenant', function ($query) use ($user) {
                $query->where('created_by', $user->id);
            })
            ->count();

        if ($currentLeaseCount >= $totalLeaseLimit) {
            throw ValidationException::withMessages([
                'error' => "Limit Reached: Your current package ({$package->name}) only allows {$totalLeaseLimit} leases.",
            ]);
        }
    }

    /**
     * Resolve the lease property / unit / room and tenant.
     */
    protected function resolveLeaseContext(
        array $data,
        ?Lease $oldLease
    ): array {
        // Renew / Check Out / End Agreement
        if ($data['status'] !== 'New') {
            return [
                'leasable' => $oldLease->leasable,
                'leasable_type' => $oldLease->leasable_type,
                'leasable_id' => $oldLease->leasable_id,
                'tenant_id' => $oldLease->tenant_id,
            ];
        }

        $selection = $data['lease_selection'];

        $leasableType = match ($selection) {
            'property' => Property::class,
            'unit' => Unit::class,
            'room' => Room::class,
        };

        $leasableId = match ($selection) {
            'property' => $data['property_id'] ?? null,
            'unit' => $data['unit_id'] ?? null,
            'room' => $data['room_id'] ?? null,
        };

        if (!$leasableId) {
            throw ValidationException::withMessages([
                'lease_selection' => 'Please select a valid property, unit or room.',
            ]);
        }

        $leasable = $leasableType::find($leasableId);

        if (!$leasable) {
            throw ValidationException::withMessages([
                'lease_selection' => 'Property, unit or room not found.',
            ]);
        }

        return [
            'leasable' => $leasable,
            'leasable_type' => $leasableType,
            'leasable_id' => $leasableId,
            'tenant_id' => $data['tenant_id'],
        ];
    }

    /**
     * Update property / unit / room status.
     */
    protected function updateLeasableStatus(
        $leasable,
        string $status
    ): void {
        $targetStatus = match ($status) {
            'Check Out' => 'Cleaning',
            'End Agreement' => 'Vacant',
            default => 'Occupied',
        };

        $leasable->propagateStatus($targetStatus);

        $leasable->update([
            'status' => $targetStatus,
        ]);

        if (in_array($status, ['Check Out', 'End Agreement'])) {
            $leasable->syncStatus();
        }

        Log::info('Leasable status updated.', [
            'type' => get_class($leasable),
            'id' => $leasable->id,
            'status' => $targetStatus,
        ]);
    }

    /**
     * Create the Lease record.
     */
    protected function createLease(
        array $data,
        ?Lease $oldLease,
        array $context
    ): Lease {
        $status = $data['status'];

        $startDate = in_array($status, ['New', 'Renew'])
            ? $this->parseDate($data['start_date'] ?? null)
            : $oldLease?->start_date;

        $endDate = in_array($status, ['New', 'Renew'])
            ? $this->parseDate($data['end_date'] ?? null)
            : $oldLease?->end_date;

        $checkedOutAt = $status === 'Check Out'
            ? $this->parseDate($data['checked_out_at'] ?? null)
            : null;

        $agreementEndedAt = $status === 'End Agreement'
            ? $this->parseDate($data['agreement_ended_at'] ?? null)
            : null;

        $securityDeposit = (float) ($data['security_deposit'] ?? 0);
        $utilitiesDeposit = (float) ($data['utilities_deposit'] ?? 0);

        return Lease::create([
            'parent_lease_id' => $oldLease?->id,

            // Database column from your Lease model
            'document_id' => in_array($status, ['New', 'Renew'])
                ? ($data['document_id'] ?? null)
                : $oldLease?->agreement_id,

            'is_current' => true,

            'leasable_type' => $context['leasable_type'],
            'leasable_id' => $context['leasable_id'],
            'tenant_id' => $context['tenant_id'],

            'start_date' => $startDate,
            'end_date' => $endDate,
            'checked_out_at' => $checkedOutAt,
            'agreement_ended_at' => $agreementEndedAt,

            'term_type' => in_array($status, ['New', 'Renew'])
                ? ($data['term_type'] ?? null)
                : $oldLease?->term_type,

            'deposit_mode' => $this->resolveDepositMode(
                $securityDeposit,
                $utilitiesDeposit
            ),

            'security_deposit' => $securityDeposit,
            'utilities_deposit' => $utilitiesDeposit,

            'status' => $status,
        ]);
    }

    /**
     * Create rent and deposit charges.
     */
    protected function createLeaseCharges(
        Lease $lease,
        array $data
    ): void {
        // Recurring Rent
        $rentPrice = (float) ($data['rent_price'] ?? 0);

        if ($rentPrice > 0) {
            LeaseCharge::create([
                'lease_id' => $lease->id,
                'description' => 'Rent',
                'amount' => (int) round($rentPrice * 100),
                'charge_type' => LeaseCharge::TYPE_RECURRING,
                'is_active' => true,
                'sort_order' => 1,
            ]);
        }

        // Security Deposit
        $securityDeposit = (float) ($data['security_deposit'] ?? 0);

        if ($securityDeposit > 0) {
            LeaseCharge::create([
                'lease_id' => $lease->id,
                'description' => 'Security Deposit',
                'amount' => (int) round($securityDeposit * 100),
                'charge_type' => LeaseCharge::TYPE_REFUNDABLE,
                'is_active' => true,
                'sort_order' => 2,
            ]);
        }

        // Utilities Deposit
        $utilitiesDeposit = (float) ($data['utilities_deposit'] ?? 0);

        if ($utilitiesDeposit > 0) {
            LeaseCharge::create([
                'lease_id' => $lease->id,
                'description' => 'Utilities Deposit',
                'amount' => (int) round($utilitiesDeposit * 100),
                'charge_type' => LeaseCharge::TYPE_REFUNDABLE,
                'is_active' => true,
                'sort_order' => 3,
            ]);
        }
    }

    /**
     * Resolve deposit mode.
     */
    protected function resolveDepositMode(
        float $securityDeposit,
        float $utilitiesDeposit
    ): string {
        if ($securityDeposit > 0 && $utilitiesDeposit > 0) {
            return 'both';
        }

        if ($securityDeposit > 0) {
            return 'security_only';
        }

        if ($utilitiesDeposit > 0) {
            return 'utilities_only';
        }

        return 'none';
    }

    /**
     * Convert date to database format.
     */
    protected function parseDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date)->format('Y-m-d');
    }
}
