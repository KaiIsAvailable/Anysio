<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkDuplicateValidator
{
    /** Field definitions are trusted server-side model/column pairs. */
    public function validate(array $rows, array $checks, callable $existingId, ?callable $scope = null): void
    {
        foreach ($checks as $field => [$model, $column]) {
            $values = collect($rows)->where('action', '!=', 'remove')->pluck($field)->filter(fn ($value) => filled($value))
                ->map(fn ($value) => mb_strtolower($value))->unique()->values()->all();
            if (! $values) {
                continue;
            }
            $qualifiedColumn = (new $model)->qualifyColumn($column);
            $query = $model::query();
            if ($scope) {
                $scope($query, $field);
            }
            $matches = $query->whereIn(DB::raw('LOWER('.$qualifiedColumn.')'), $values)
                ->get(['id', $column])->groupBy(fn ($record) => mb_strtolower($record->$column));
            $seen = [];
            foreach ($rows as $index => $row) {
                if ($row['action'] === 'remove' || blank($row[$field] ?? null)) {
                    continue;
                }
                $value = mb_strtolower($row[$field]);
                $ownId = $existingId($row, $field);
                $message = null;
                if (isset($seen[$value]) && ($field !== 'ssmNew' || ! $ownId || $seen[$value] !== $ownId)) {
                    $message = 'Duplicate value in this batch.';
                } elseif (isset($matches[$value]) && $matches[$value]->contains(fn ($record) => $record->id !== $ownId)) {
                    $message = 'This value is already used by another record.';
                }
                if ($message) {
                    throw ValidationException::withMessages(["rows.{$index}.{$field}" => 'Row '.($row['rowNumber'] ?? $index + 1).': '.$message]);
                }
                $seen[$value] = $ownId ?? 'new-'.$index;
            }
        }
    }
}
