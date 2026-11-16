<?php

namespace App\Services;

use App\Models\Owners;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class OwnerIdentityValidator
{
    public function rules(?Owners $owner, ?string $managerId): array
    {
        $scope = $owner ? $owner->agent_id : $managerId;

        return [
            'ic_number' => ['nullable', 'string', 'max:20', Rule::unique('owners', 'ic_number')->where('agent_id', $scope)->ignore($owner?->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('owners', 'phone')->where('agent_id', $scope)->ignore($owner?->id)],
        ];
    }

    /** Serialize owner writes within each management account, including empty accounts. */
    public function lockAccounts(array $managerIds): void
    {
        User::whereIn('id', array_values(array_unique(array_filter($managerIds))))
            ->orderBy('id')->lockForUpdate()->get(['id']);
    }

    public function validateRows(array $rows, Collection $existing, string $createManagerId): void
    {
        $groups = [];
        foreach ($rows as $index => $row) {
            $owner = $existing[$row['id'] ?? ''] ?? null;
            $scope = $owner ? $owner->agent_id : $createManagerId;
            $key = $scope ?? '';
            $groups[$key]['scope'] = $scope;
            $groups[$key]['rows'][$index] = $row;
        }
        foreach ($groups as $group) {
            app(BulkDuplicateValidator::class)->validate($group['rows'], [
                'ic' => [Owners::class, 'ic_number'], 'phone' => [Owners::class, 'phone'],
            ], fn (array $row) => ($existing[$row['id'] ?? ''] ?? null)?->id,
                fn ($query) => $query->where('agent_id', $group['scope']));
        }
    }
}
