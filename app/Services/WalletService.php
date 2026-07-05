<?php
namespace App\Services;

use App\Models\{Wallet, WalletTransaction};
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getOrCreateWallet(int $userId): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $userId], ['balance' => 0]);
    }

    public function credit(int $userId, int $amountCents, string $type, string $refId, string $remarks = ''): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amountCents, $type, $refId, $remarks) {
            $wallet = $this->getOrCreateWallet($userId);
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

    public function debit(int $userId, int $amountCents, string $type, string $refId, string $remarks = ''): WalletTransaction
    {
        return DB::transaction(function () use ($userId, $amountCents, $type, $refId, $remarks) {
            $wallet = $this->getOrCreateWallet($userId);
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

    public function getBalance(int $userId): int
    {
        return Wallet::where('user_id', $userId)->value('balance') ?? 0;
    }
}