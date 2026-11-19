<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne, MorphTo};
use Illuminate\Validation\Rule;

class UtilityService extends Model
{
    use HasUlids, SoftDeletes, Auditable;

    public const TYPES = ['water', 'electricity', 'indah_water', 'gas'];
    public const METHODS = ['metered', 'fixed', 'shared'];
    public const STATUSES = ['unconfirmed', 'active', 'inactive'];

    protected $fillable = [
        'management_user_id', 'location_type', 'location_id', 'type', 'billing_method',
        'label', 'meter_number', 'parent_service_id', 'opening_reading', 'opening_reading_date', 'status', 'billing_scope',
    ];

    protected $casts = ['opening_reading' => 'decimal:3', 'opening_reading_date' => 'date'];

    public function managementUser(): BelongsTo { return $this->belongsTo(User::class, 'management_user_id'); }
    public function location(): MorphTo { return $this->morphTo(); }
    public function parentService(): BelongsTo { return $this->belongsTo(self::class, 'parent_service_id')->withTrashed(); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_service_id'); }
    public function records(): HasMany { return $this->hasMany(Utility::class); }
    public function tariffAssignments(): HasMany { return $this->hasMany(UtilityTariffAssignment::class); }
    public function meterEvents(): HasMany { return $this->hasMany(UtilityMeterEvent::class); }
    public function latestRecord(): HasOne { return $this->hasOne(Utility::class)->latestOfMany(); }

    public function scopeForManagement(Builder $query, ?string $id): Builder
    {
        return $id ? $query->where('management_user_id', $id) : $query->whereRaw('1 = 0');
    }

    public function scopeForActiveLocations(Builder $query): Builder
    {
        return $query->whereHasMorph('location', [Property::class, Unit::class, Room::class], fn ($q) => $q->forActiveHierarchy());
    }

    public static function filterRules(array $input = []): array
    {
        $rules = [
            'utility_location_type' => ['nullable', Rule::in(['property', 'unit', 'room'])],
            'utility_status' => ['nullable', Rule::in(self::STATUSES)],
            'utility_method' => ['nullable', Rule::in(self::METHODS)],
            'utility_readings' => ['nullable', Rule::in(['recorded', 'missing'])],
        ];
        foreach (['property', 'unit', 'room', 'tenant', 'label', 'account', 'meter', 'parent'] as $field) {
            $rules['utility_'.$field] = ['nullable', 'string', 'max:255'];
        }
        return array_merge($rules, Utility::filterRules($input));
    }

    public function scopeTenantNamed(Builder $query, string $name): Builder
    {
        return $query->whereExists(function ($tenant) use ($name) {
            $tenant->selectRaw('1')->from('leases')->join('tenants', 'tenants.id', '=', 'leases.tenant_id')
                ->join('users', 'users.id', '=', 'tenants.user_id')
                ->whereColumn('leases.leasable_type', 'utility_services.location_type')
                ->whereColumn('leases.leasable_id', 'utility_services.location_id')
                ->where('leases.is_current', true)->whereIn('leases.status', ['New', 'Renew'])
                ->whereNull('leases.cancelled_at')->whereNull('leases.checked_out_at')->whereNull('leases.agreement_ended_at')
                ->whereDate('leases.start_date', '<=', today())
                ->where(fn ($dates) => $dates->whereNull('leases.end_date')->orWhereDate('leases.end_date', '>=', today()))
                ->where('users.name', 'like', '%'.$name.'%');
        });
    }

    /** Independent fields combine with AND; hierarchy alternatives stay inside each field. */
    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        foreach (['utility_status' => 'status', 'utility_method' => 'billing_method'] as $key => $column) {
            if (filled($filters[$key] ?? null)) $query->where('utility_services.'.$column, $filters[$key]);
        }
        if (filled($filters['utility_location_type'] ?? null)) {
            $model = ['property' => Property::class, 'unit' => Unit::class, 'room' => Room::class][$filters['utility_location_type']];
            $query->where('location_type', (new $model)->getMorphClass());
        }
        foreach (['label', 'meter'] as $field) {
            if (filled($filters['utility_'.$field] ?? null)) $query->where('utility_services.'.($field === 'meter' ? 'meter_number' : 'label'), 'like', '%'.trim($filters['utility_'.$field]).'%');
        }
        if (filled($filters['utility_tenant'] ?? null)) $query->tenantNamed(trim($filters['utility_tenant']));
        if (filled($filters['utility_parent'] ?? null)) $query->whereHas('parentService', fn ($parent) => $parent->where('label', 'like', '%'.trim($filters['utility_parent']).'%'));

        foreach (['property', 'unit', 'room', 'account'] as $field) {
            if (!filled($filters['utility_'.$field] ?? null)) continue;
            $pattern = '%'.trim($filters['utility_'.$field]).'%';
            $query->whereHasMorph('location', [Property::class, Unit::class, Room::class], function (Builder $location, string $model) use ($field, $pattern) {
                if ($field === 'property') {
                    match ($model) {
                        Property::class => $location->where('name', 'like', $pattern),
                        Unit::class => $location->whereHas('property', fn ($property) => $property->where('name', 'like', $pattern)),
                        Room::class => $location->whereHas('unit.property', fn ($property) => $property->where('name', 'like', $pattern)),
                    };
                } elseif ($field === 'room') {
                    $model === Room::class ? $location->where('room_no', 'like', $pattern) : $location->whereRaw('1 = 0');
                } elseif ($model === Property::class) {
                    $location->whereRaw('1 = 0');
                } else {
                    $unitMatch = function (Builder $unit) use ($field, $pattern) {
                        if ($field === 'unit') {
                            $unit->where('unit_no', 'like', $pattern);
                        } else {
                            $unit->where(function ($accounts) use ($pattern) {
                                foreach (['water' => 'water_acc_no', 'electricity' => 'electricity_acc_no', 'indah_water' => 'indah_water_acc_no'] as $type => $column) {
                                    $accounts->orWhere(fn ($account) => $account->where('utility_services.type', $type)->where($column, 'like', $pattern));
                                }
                            });
                        }
                    };
                    $model === Room::class ? $location->whereHas('unit', $unitMatch) : $location->where($unitMatch);
                }
            });
        }
        if (($filters['utility_readings'] ?? '') === 'missing') $query->whereDoesntHave('latestRecord');
        elseif (($filters['utility_readings'] ?? '') === 'recorded') $query->whereHas('latestRecord');
        $readingFilters = array_filter(array_intersect_key($filters, Utility::filterRules()), fn ($value) => filled($value));
        if ($readingFilters) {
            $query->where(function ($readings) use ($readingFilters) {
                $readings->whereHas('latestRecord', fn ($record) => $record->applyFilters($readingFilters));
                // Before history exists, the index displays the opening value as Previous Reading.
                if (!array_diff_key($readingFilters, array_flip(['utility_previous_min', 'utility_previous_max']))) {
                    $readings->orWhere(function ($opening) use ($readingFilters) {
                        $opening->whereDoesntHave('latestRecord')->where('billing_method', 'metered')->whereNotNull('opening_reading');
                        foreach (['min' => '>=', 'max' => '<='] as $bound => $operator) {
                            if (filled($readingFilters['utility_previous_'.$bound] ?? null)) $opening->where('opening_reading', $operator, $readingFilters['utility_previous_'.$bound]);
                        }
                    });
                }
            });
        }
        return $query;
    }

    /** Group every OR inside the search so management/type filters remain mandatory. */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') return $query;
        $pattern = '%'.$search.'%';
        $unitMatch = function (Builder $unit) use ($pattern) {
            $unit->where('unit_no', 'like', $pattern)
                ->orWhereHas('property', fn ($property) => $property->where('name', 'like', $pattern));
            foreach (['water' => 'water_acc_no', 'electricity' => 'electricity_acc_no', 'indah_water' => 'indah_water_acc_no'] as $type => $field) {
                $unit->orWhere(fn ($account) => $account->where('utility_services.type', $type)->where($field, 'like', $pattern));
            }
        };

        return $query->where(function (Builder $match) use ($search, $pattern, $unitMatch) {
            $match->where('utility_services.label', 'like', $pattern)
                ->orWhere('utility_services.meter_number', 'like', $pattern)
                ->orWhere('utility_services.billing_method', 'like', $pattern)
                ->orWhere('utility_services.status', 'like', $pattern)
                ->orWhere('utility_services.type', 'like', '%'.str_replace(' ', '_', $search).'%')
                ->orWhereHas('parentService', fn ($parent) => $parent->where('label', 'like', $pattern))
                ->orWhereHas('latestRecord', fn ($record) => $record->search($search))
                ->orWhere(function ($opening) use ($search, $pattern) {
                    $opening->whereDoesntHave('latestRecord')->where('billing_method', 'metered')
                        ->where(function ($value) use ($search, $pattern) {
                            $value->whereRaw('CAST(utility_services.opening_reading AS CHAR) LIKE ?', [$pattern]);
                            $number = str_replace(',', '', $search);
                            if (is_numeric($number)) $value->orWhere('opening_reading', $number);
                        });
                })
                ->orWhereHasMorph('location', [Property::class, Unit::class, Room::class], function (Builder $location, string $model) use ($pattern, $unitMatch) {
                    match ($model) {
                        Property::class => $location->where('name', 'like', $pattern),
                        Unit::class => $location->where($unitMatch),
                        Room::class => $location->where(fn ($room) => $room->where('room_no', 'like', $pattern)->orWhereHas('unit', $unitMatch)),
                    };
                })
                ->orWhere(fn ($tenant) => $tenant->tenantNamed($search));
            foreach ([Property::class, Unit::class, Room::class] as $model) {
                if (str_contains(strtolower(class_basename($model)), strtolower($search))) {
                    $match->orWhere('location_type', (new $model)->getMorphClass());
                }
            }
            if (str_contains('not configured', strtolower($search))) $match->orWhereNull('parent_service_id');
        });
    }

    public function getLocationLabelAttribute(): string
    {
        $asset = $this->location;
        return match (true) {
            $asset instanceof Property => $asset->name,
            $asset instanceof Unit => ($asset->property?->name ?? 'Property').' / '.$asset->unit_no,
            $asset instanceof Room => ($asset->unit?->property?->name ?? 'Property').' / '.($asset->unit?->unit_no ?? 'Unit').' / '.$asset->room_no,
            default => 'Location unavailable',
        };
    }

    public function getAccountNumberAttribute(): ?string
    {
        $unit = $this->location instanceof Room ? $this->location->unit : ($this->location instanceof Unit ? $this->location : null);
        return match ($this->type) {
            'water' => $unit?->water_acc_no,
            'electricity' => $unit?->electricity_acc_no,
            'indah_water' => $unit?->indah_water_acc_no,
            default => null,
        };
    }
}
