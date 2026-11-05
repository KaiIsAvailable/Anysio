<?php

namespace App\Services;

use App\Models\{BankDetail, BankUser, User};
use Illuminate\Support\Facades\DB;

class BankAccountService
{
    public function getUserBankDetails(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return $user->bankUsers
            ->map(function (BankUser $bankUser): array {
                $bank = $bankUser->bankDetail;
                $accountNumber = (string) ($bank?->account_number ?? '');

                return [
                    'id' => $bank?->id,
                    'bank_name' => $bank?->bank_name,
                    'account_holder_name' => $bank?->account_holder_name,
                    'account_last_four' => $accountNumber !== ''
                        ? substr($accountNumber, -4)
                        : null,
                    'is_default' => (bool) $bankUser->is_default,
                ];
            })
            ->filter(fn (array $bank): bool => !empty($bank['id']))
            ->sortByDesc('is_default')
            ->values()
            ->all();
    }

    public function getDefaultForUser(User $user): ?BankDetail
    {
        return $user->bankDetails()
            ->wherePivot('is_default', true)
            ->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveForUser(User $user, array $data): BankDetail
    {
        return DB::transaction(function () use ($user, $data): BankDetail {
            $bankUser = BankUser::where('user_id', $user->id)
                ->where('is_default', true)
                ->with('bankDetail')
                ->first();

            $bankDetail = $bankUser?->bankDetail;

            if (!$bankDetail) {
                $bankDetail = BankDetail::create([
                    'bank_name' => $data['bank_name'],
                    'bank_code' => $data['bank_code'] ?? null,
                    'account_holder_name' => $data['account_holder_name'],
                    'account_number' => $data['account_number'],
                ]);

                $bankUser = BankUser::create([
                    'bank_detail_id' => $bankDetail->id,
                    'user_id' => $user->id,
                    'is_default' => true,
                ]);
            } else {
                $bankDetail->fill([
                    'bank_name' => $data['bank_name'],
                    'bank_code' => $data['bank_code'] ?? null,
                    'account_holder_name' => $data['account_holder_name'],
                ]);

                if (!empty($data['account_number'])) {
                    $bankDetail->account_number = $data['account_number'];
                }

                $bankDetail->save();
            }

            if (filter_var($data['is_default'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                BankUser::where('user_id', $user->id)
                    ->where('id', '!=', $bankUser->id)
                    ->update(['is_default' => false]);

                $bankUser->update(['is_default' => true]);
            }

            return $bankDetail;
        });
    }
}
