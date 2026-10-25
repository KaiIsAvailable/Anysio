<?php
namespace App\Services;

use App\Models\{Lease, Refund, RefundItem, RefundDisbursement, FeeType};
use App\Enums\{RefundStatus, DisbursementStatus, RefundItemType};
use Illuminate\Support\Facades\{DB, Log, Auth};
use Illuminate\Validation\ValidationException;

class RefundService {
    public function __construct(
        protected RefundCalculationService $calcService
    ) {}

    public function saveAsDraft(Lease $lease, array $data, array $items): Refund {
        $actualUser = Auth::user();                  
        $effectiveUser = get_effective_user();        

        return DB::transaction(function () use ($lease, $data, $items, $actualUser, $effectiveUser) {
            
            $grossCents = 0;
            $deductionCents = 0;

            // 1. Calculate header totals from the items array
            foreach ($items as $item) {
                $amount = (int) ($item['amount_cents'] ?? 0);

                if (in_array($item['type'] ?? '', [RefundItemType::DAMAGE->value, RefundItemType::UTILITY->value, RefundItemType::CLEANING->value, RefundItemType::ADJUSTMENT->value])) {
                    $deductionCents += $amount;
                } else {
                    $grossCents += $amount;
                }
            }

            $netCents = $grossCents - $deductionCents;

            // 2. Find existing draft or create new header...
            $refund = Refund::where('lease_id', $lease->id)
                ->where('status', RefundStatus::DRAFT)
                ->first();

            if ($refund) {
                $refund->update([
                    'type' => $data['type'] ?? $refund->type,
                    'gross_amount_cents' => $grossCents,
                    'deduction_amount_cents' => $deductionCents,
                    'net_amount_cents' => $netCents,
                    'reason' => $data['reason'] ?? $refund->reason,
                    'internal_notes' => $data['internal_notes'] ?? $refund->internal_notes,
                ]);

                $refund->items()->delete();
            } else {
                $refundNumber = "REF-" . strtoupper(now()->format('Ym')) . "-" . str_pad(Refund::count() + 1, 4, '0', STR_PAD_LEFT);

                $refund = Refund::create([
                    'refund_number' => $refundNumber,
                    'lease_id' => $lease->id,
                    'tenant_id' => $lease->tenant_id,
                    'type' => $data['type'] ?? 'deposit',
                    'gross_amount_cents' => $grossCents,
                    'deduction_amount_cents' => $deductionCents,
                    'net_amount_cents' => $netCents,
                    'status' => RefundStatus::DRAFT,
                    'reason' => $data['reason'] ?? 'Draft refund settlement',
                    'internal_notes' => $data['internal_notes'] ?? null,
                    'requested_by' => $actualUser?->id, 
                    'requested_at' => now(),
                ]);
            }

            // 3. Save each item row into refund_items table
            foreach ($items as $item) {
                $feeTypeId = $item['fee_type_id'] ?? null;
                $description = $item['description'] ?? '';
                
                if ($feeTypeId && empty($description)) {
                    $feeType = FeeType::find($feeTypeId);
                    $description = $feeType ? $feeType->name : 'Custom Deduction';
                }

                $refund->items()->create([
                    'fee_type_id' => $feeTypeId,
                    'type' => $item['type'] ?? RefundItemType::ADJUSTMENT->value,
                    'description' => $description,
                    'qty' => (int) ($item['qty'] ?? 1),
                    'amount_cents' => (int) ($item['amount_cents'] ?? 0),
                    'lease_charge_id' => $item['lease_charge_id'] ?? null,
                    'invoice_item_id' => $item['invoice_item_id'] ?? null,
                ]);
            }

            return $refund;
        });
    }

    public function approveRefund(Refund $refund, $user): Refund {
        return DB::transaction(function () use ($refund, $user) {
            $lockedRefund = Refund::where('id', $refund->id)->lockForUpdate()->first();

            if ($lockedRefund->status !== RefundStatus::PENDING) {
                throw ValidationException::withMessages(['status' => 'Only pending refunds can be approved.']);
            }

            $lockedRefund->update([
                'status' => RefundStatus::APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            return $lockedRefund;
        });
    }

    public function processDisbursement(Refund $refund, array $disbursementData, $user): RefundDisbursement {
        return DB::transaction(function () use ($refund, $disbursementData, $user) {
            $lockedRefund = Refund::where('id', $refund->id)->lockForUpdate()->first();

            if ($lockedRefund->status !== RefundStatus::APPROVED) {
                throw ValidationException::withMessages(['status' => 'Refund must be approved prior to disbursement payout.']);
            }

            if ($lockedRefund->approved_by === $user->id && !$user->can('refunds.override-dual-control')) {
                throw ValidationException::withMessages([
                    'authorization' => 'The user who approved this refund cannot perform the payout step.',
                ]);
            }

            $disbursement = $lockedRefund->disbursements()->create([
                'amount_cents' => $disbursementData['amount_cents'],
                'method' => $disbursementData['method'],
                'reference' => $disbursementData['reference'] ?? null,
                'status' => $disbursementData['status'] ?? DisbursementStatus::SUCCESSFUL->value,
                'processed_at' => now(),
            ]);

            if ($disbursement->status === DisbursementStatus::SUCCESSFUL->value) {
                $totalPaid = $lockedRefund->disbursements()
                    ->where('status', DisbursementStatus::SUCCESSFUL->value)
                    ->sum('amount_cents');

                if ($totalPaid >= $lockedRefund->net_amount_cents) {
                    $lockedRefund->update([
                        'status' => RefundStatus::PAID,
                        'paid_by' => $user->id,
                        'paid_at' => now(),
                    ]);
                }
            }

            return $disbursement;
        });
    }
}