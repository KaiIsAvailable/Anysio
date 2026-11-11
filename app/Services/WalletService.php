<?php
namespace App\Services;

use App\Models\{Wallet, WalletTransaction, Refund};
use App\Enums\RefundStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    /** The caller holds the wallet lock; an unresolved reservation stays fully protected. */
    public function automaticSpendableBalance(Wallet $wallet): int
    {
        $balance = max(0, (int) $wallet->balance);
        if (!$wallet->reserved_refund_id) return $balance;
        $refund = Refund::find($wallet->reserved_refund_id);
        if (!$refund || $refund->status !== RefundStatus::APPROVED) return 0;
        return max(0, $balance - max(0, (int) $refund->wallet_refund_cents));
    }

    public function refundableBalance(string $userId, ?string $refundId = null, bool $lock = false): int
    {
        $query = Wallet::where('user_id', $userId);
        $wallet = ($lock ? $query->lockForUpdate() : $query)->first();
        if (!$wallet || ($wallet->reserved_refund_id && $wallet->reserved_refund_id !== $refundId)) {
            return 0;
        }
        return max(0, (int) $wallet->balance);
    }

    public function reserveForRefund(string $userId, string $refundId, int $amountCents): void
    {
        $this->getOrCreateWallet($userId);
        $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
        if (($wallet->reserved_refund_id && $wallet->reserved_refund_id !== $refundId)
            || (int) $wallet->balance !== $amountCents) {
            throw ValidationException::withMessages(['status' => 'The wallet balance changed or is reserved for another refund. Review the settlement again.']);
        }
        $wallet->update(['reserved_refund_id' => $refundId]);
    }

    public function releaseRefund(string $userId, string $refundId): void
    {
        Wallet::where('user_id', $userId)->where('reserved_refund_id', $refundId)
            ->update(['reserved_refund_id' => null]);
    }

    public function settleRefund(string $userId, string $refundId, int $amountCents): void
    {
        $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
        if ($wallet->reserved_refund_id !== $refundId || (int) $wallet->balance !== $amountCents) {
            throw ValidationException::withMessages(['status' => 'The approved wallet refund no longer matches the available balance.']);
        }
        $wallet->decrement('balance', $amountCents);
        $wallet->update(['reserved_refund_id' => null]);
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'amount' => -$amountCents,
            'type' => 'checkout_refund',
            'reference_id' => $refundId,
            'remarks' => 'Wallet balance included in checkout settlement '.$refundId,
        ]);
    }

    public function getOrCreateWallet(string $userId): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $userId], ['balance' => 0]);
    }

    public function credit(string $userId, int $amountCents, string $type, string $refId, string $remarks = ''): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amountCents, $type, $refId, $remarks) {
            $this->getOrCreateWallet($userId);
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $wallet->increment('balance', $amountCents);

            return WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'amount'       => $amountCents,  // positive
                'type'         => $type,
                'reference_id' => $refId,
                'remarks'      => $remarks,
            ]);
        });
    }

    public function debit(string $userId, int $amountCents, string $type, string $refId, string $remarks = '', bool $automatic = false): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amountCents, $type, $refId, $remarks, $automatic) {
            $this->getOrCreateWallet($userId);
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
            if ($wallet->reserved_refund_id && (!$automatic || $amountCents > $this->automaticSpendableBalance($wallet))) {
                throw ValidationException::withMessages(['use_wallet' => 'This wallet balance is reserved for an approved checkout refund and cannot be spent.']);
            }
            abort_if($wallet->balance < $amountCents, 422, 'Insufficient wallet balance.');

            $wallet->decrement('balance', $amountCents);

            return WalletTransaction::create([
                'wallet_id'    => $wallet->id,
                'amount'       => -$amountCents, // negative = debit
                'type'         => $type,
                'reference_id' => $refId,
                'remarks'      => $remarks,
            ]);
        });
    }

    public function getBalance(string $userId): string
    {
        return Wallet::where('user_id', $userId)->value('balance') ?? 0;
    }
}
