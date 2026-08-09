<?php

namespace App\Services;

use App\Models\{
    Lease,
    LeaseCharge,
    FeeType,
    Property,
    Unit,
    Room,
    User
};
use App\FeeTypeCategory;
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
        try {
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

                    // 1. 建立租約的基準費用配置 (Charges)
                    $this->createLeaseCharges(
                        $newLease,
                        $data
                    );

                    // 🌟 2. 完美接合：利用 InvoiceService 自動生成首期帳單！
                    $this->invoiceService->createInitialInvoiceForLease(
                        $newLease, 
                        $user // Auth::user() 從 controller 傳進來的
                    );
                }

                Log::info('Lease processed successfully.', [
                    'status' => $status,
                    'lease_id' => $newLease->id,
                    'old_lease_id' => $oldLease?->id,
                ]);

                return $newLease;
            });
        } catch (\Throwable $e) {

            Log::error('Lease creation failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'data'    => $data,
            ]);

            throw $e;
        }
    }

    protected function getOldLease(array $data): ?Lease
    {
        if ($data['status'] === 'New') {
            return null;
        }

        return Lease::with('leasable')->findOrFail($data['lease_id']);
    }

    protected function checkLeaseLimit(User $user): void
    {
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
            ->whereIn('status', ['New', 'Renew', 'Check Out'])
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

    protected function resolveLeaseContext(array $data, ?Lease $oldLease): array
    {
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

    protected function updateLeasableStatus($leasable, string $status): void
    {
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
    }

    protected function createLease(array $data, ?Lease $oldLease, array $context): Lease
    {
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

        // Find if any charge fee type name contains daily, weekly, monthly, or yearly
        $termType = 'monthly'; // default fallback
        if (!empty($data['charges'])) {
            foreach ($data['charges'] as $charge) {
                $feeType = FeeType::find($charge['fee_type_id'] ?? null);
                if ($feeType) {
                    $name = strtolower($feeType->name);
                    if (str_contains($name, 'daily')) { $termType = 'daily'; break; }
                    if (str_contains($name, 'weekly')) { $termType = 'weekly'; break; }
                    if (str_contains($name, 'monthly')) { $termType = 'monthly'; break; }
                    if (str_contains($name, 'yearly')) { $termType = 'yearly'; break; }
                }
            }
        }

        return Lease::create([
            'parent_lease_id' => $oldLease?->id,
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
                ? $termType
                : $oldLease?->term_type,
            'status' => $status,
        ]);
    }

    /**
     * Create multiple dynamic charges and deposits from the form array.
     */
    protected function createLeaseCharges(Lease $lease, array $data): void
    {
        if (empty($data['charges']) || !is_array($data['charges'])) {
            return;
        }

        foreach ($data['charges'] as $index => $chargeData) {
            $amount = (float) ($chargeData['amount'] ?? 0);

            if ($amount <= 0) {
                continue;
            }

            $feeType = FeeType::find($chargeData['fee_type_id']);
            $description = $feeType ? $feeType->name : 'Charge';

            // Determine if it's refundable (deposit) or recurring/other fee
            $chargeType = match ($feeType?->category) {
                FeeTypeCategory::DEPOSIT => LeaseCharge::TYPE_REFUNDABLE,
                default => LeaseCharge::TYPE_RECURRING,
            };

            LeaseCharge::create([
                'lease_id' => $lease->id,
                'fee_type_id' => $chargeData['fee_type_id'],
                'description' => $description,
                'amount' => (int) round($amount * 100), // Stored in cents
                'charge_type' => $chargeType,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }

    protected function parseDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date)->format('Y-m-d');
    }
}