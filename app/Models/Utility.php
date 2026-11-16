<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Utility extends Model
{
    use HasUlids, Auditable;

    protected $table = 'utilities';
    protected $fillable = [
        'utility_service_id',
        'lease_id',
        'type',
        'prev_reading',
        'curr_reading',
        'amount',
        'period', 'prev_reading_date', 'curr_reading_date', 'applied_rate',
        'calculation_details', 'invoice_item_id', 'status',
        'entry_key', 'consumption', 'utility_tariff_version_id', 'utility_meter_event_id', 'allocation_revision',
    ];

    protected $casts = [
        'prev_reading' => 'decimal:3',
        'curr_reading' => 'decimal:3',
        'amount' => 'integer',
        'period' => 'date',
        'prev_reading_date' => 'date',
        'curr_reading_date' => 'date',
        'applied_rate' => 'decimal:6',
        'calculation_details' => 'array',
        'consumption' => 'decimal:3', 'allocation_revision' => 'integer',
    ];

    public function utilityService(): BelongsTo
    {
        return $this->belongsTo(UtilityService::class)->withTrashed();
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function allocations() { return $this->hasMany(UtilityAllocation::class)->whereNull('superseded_at'); }
    public function corrections() { return $this->hasMany(UtilityCorrection::class); }
    public function latestCorrection() { return $this->hasOne(UtilityCorrection::class)->whereNull('utility_allocation_id')->where('status', 'issued')->latestOfMany(); }
    public function tariffVersion() { return $this->belongsTo(UtilityTariffVersion::class, 'utility_tariff_version_id'); }
    public function meterEvent() { return $this->belongsTo(UtilityMeterEvent::class, 'utility_meter_event_id'); }

    /** Search reading values as displayed: decimal readings, RM amounts and local-format dates. */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') return $query;

        $pattern = '%'.$search.'%';
        $number = trim(str_ireplace(['RM', ','], '', $search));
        $numeric = preg_match('/^-?\d+(?:\.\d+)?$/D', $number) === 1;
        $previous = $this->qualifyColumn('prev_reading');
        $current = $this->qualifyColumn('curr_reading');
        $amount = $this->qualifyColumn('amount');

        return $query->where(function (Builder $match) use ($search, $pattern, $number, $numeric, $previous, $current, $amount) {
            $match->where($this->qualifyColumn('type'), 'like', $pattern)
                ->orWhereRaw("CAST($previous AS CHAR) LIKE ?", [$pattern])
                ->orWhereRaw("CAST($current AS CHAR) LIKE ?", [$pattern])
                ->orWhereRaw("CAST(COALESCE(utilities.consumption, ROUND($current - $previous, 3)) AS CHAR) LIKE ?", [$pattern]);
            if ($numeric) {
                $match->orWhere($previous, $number)->orWhere($current, $number)
                    ->orWhereRaw("COALESCE(utilities.consumption, ROUND($current - $previous, 3)) = CAST(? AS DECIMAL(15, 3))", [$number]);
                if (preg_match('/^-?\d+(?:\.\d{1,2})?$/D', $number)) {
                    $match->orWhere($amount, (int) round((float) $number * 100));
                }
            }
            $moneyPattern = '%'.trim(str_ireplace(['RM', ','], '', $search)).'%';
            $match->orWhereRaw("CAST(ROUND($amount / 100.0, 2) AS CHAR) LIKE ?", [$moneyPattern]);
            foreach (['created_at', 'updated_at'] as $field) {
                $column = $this->qualifyColumn($field);
                $dateSql = $this->getConnection()->getDriverName() === 'sqlite'
                    ? "strftime('%d/%m/%Y %H:%M', $column)"
                    : "DATE_FORMAT($column, '%d/%m/%Y %H:%i')";
                $match->orWhereRaw("$dateSql LIKE ?", [$pattern])
                    ->orWhereRaw("CAST($column AS CHAR) LIKE ?", [$pattern]);
            }
        });
    }

    public static function filterRules(array $input = []): array
    {
        $rules = [];
        foreach (['previous', 'current', 'usage', 'amount'] as $field) {
            foreach (['min', 'max'] as $bound) {
                $key = "utility_{$field}_{$bound}";
                $rules[$key] = ['nullable', 'numeric', $field === 'amount' ? 'decimal:0,2' : 'decimal:0,3'];
                $rules[$key][] = $field === 'amount' ? 'between:-21474836.48,21474836.47' : 'between:-999999999999.999,999999999999.999';
            }
            if (filled($input["utility_{$field}_min"] ?? null)) $rules["utility_{$field}_max"][] = "gte:utility_{$field}_min";
        }
        foreach (['recorded', 'updated'] as $field) {
            $rules["utility_{$field}_from"] = ['nullable', 'date_format:Y-m-d'];
            $rules["utility_{$field}_to"] = ['nullable', 'date_format:Y-m-d'];
            if (filled($input["utility_{$field}_from"] ?? null)) $rules["utility_{$field}_to"][] = "after_or_equal:utility_{$field}_from";
        }
        return $rules;
    }

    /** All supplied ranges apply to the same reading row. */
    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        foreach (['previous' => 'prev_reading', 'current' => 'curr_reading', 'usage' => null, 'amount' => 'amount'] as $field => $column) {
            foreach (['min' => '>=', 'max' => '<='] as $bound => $operator) {
                $value = $filters["utility_{$field}_{$bound}"] ?? null;
                if (!filled($value)) continue;
                if ($field === 'usage') {
                    $query->whereRaw('COALESCE(utilities.consumption, ROUND(utilities.curr_reading - utilities.prev_reading, 3)) '.$operator.' CAST(? AS DECIMAL(15, 3))', [$value]);
                } else {
                    $query->where('utilities.'.$column, $operator, $field === 'amount' ? (int) round((float) $value * 100) : $value);
                }
            }
        }
        foreach (['recorded' => 'created_at', 'updated' => 'updated_at'] as $field => $column) {
            if (filled($filters["utility_{$field}_from"] ?? null)) $query->where('utilities.'.$column, '>=', $filters["utility_{$field}_from"].' 00:00:00');
            if (filled($filters["utility_{$field}_to"] ?? null)) {
                $query->where('utilities.'.$column, '<', \Carbon\Carbon::parse($filters["utility_{$field}_to"])->addDay()->startOfDay());
            }
        }
        return $query;
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
