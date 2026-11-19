<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Traits\SyncableStatus;
use App\Traits\Auditable;

class Property extends Model
{
    use HasUlids, SyncableStatus, Auditable;
    protected $table = 'properties';
    /**
     * 可批量赋值的字段
     * 按照你之前定稿的 Migration 字段
     */
    protected $fillable = [
        'name',
        'address',
        'city',
        'postcode',
        'state',
        'type',
        'owner_id',
        'created_by',
        'status',
    ];

    public static function propertyTypes(): array
    {
        return [
            'Condo'              => 'Condo / Apartment',
            'Landed'             => 'Landed House',
            'Commercial'         => 'Commercial Building',
            'Shop Lot'           => 'Shop Lot',
            'Shophouse'          => 'Shophouse / Mixed-Use',
            'Serviced Residence' => 'Serviced Residence',
            'Townhouse'          => 'Townhouse',
        ];
    }

    public function scopeForManagement(Builder $query, User $manager): Builder
    {
        if (\Illuminate\Support\Facades\Gate::allows('super-admin')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($manager) {
            if ($manager->role === 'ownerAdmin') {
                $q->where('properties.created_by', $manager->id)->orWhere('properties.owner_id', $manager->id);
            } elseif ($manager->role === 'agentAdmin') {
                $q->where('properties.created_by', $manager->id)
                    ->orWhereIn('properties.owner_id', Owners::where('agent_id', $manager->id)->select('user_id'));
            } else {
                $q->whereRaw('1 = 0');
            }
        });
    }

    /** A managed unit grants visibility of its parent, without granting management of that parent. */
    public function scopeForViewing(Builder $query, ?User $manager): Builder
    {
        if (\Illuminate\Support\Facades\Gate::allows('super-admin')) {
            return $query;
        }
        if (! $manager) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $visible) use ($manager) {
            $visible->forManagement($manager);
            if ($manager->role === 'agentAdmin') {
                $visible->orWhereHas('units', fn (Builder $units) => $units->forViewing($manager));
            }
        });
    }

    public function utilityServices(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(UtilityService::class, 'location');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'property_id');
    }

    public function rooms(): HasManyThrough
    {
        // Property 通过 Unit 关联到 Room
        return $this->hasManyThrough(Room::class, Unit::class, 'property_id', 'unit_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeWithSortedRelations($query)
    {
        return $query->leftJoin('users as owners', 'properties.owner_id', '=', 'owners.id')
                    ->leftJoin('users as creators', 'properties.created_by', '=', 'creators.id')
                    ->select('properties.*', 'owners.name as owner_name', 'creators.name as creator_name');
    }

    public static function availabilityStatusSql(?User $manager = null): string
    {
        $unitCounts = [];
        foreach (['vacant', 'occupied', 'maintenance'] as $status) {
            $units = Unit::query()->whereColumn('units.property_id', 'properties.id')
                ->whereRaw('LOWER(TRIM(units.status)) = ?', [$status]);
            if ($manager) {
                $units->forViewing($manager);
            }
            $unitCounts[$status] = '('.$units->selectRaw('COUNT(*)')->toRawSql().')';
        }

        return "CASE
            WHEN LOWER(TRIM(properties.status)) IN ('removed', 'inactive') THEN properties.status
            WHEN {$unitCounts['vacant']} > 0 AND {$unitCounts['vacant']} >= {$unitCounts['occupied']} AND {$unitCounts['vacant']} >= {$unitCounts['maintenance']} THEN 'Vacant'
            WHEN {$unitCounts['occupied']} > 0 AND {$unitCounts['occupied']} >= {$unitCounts['maintenance']} THEN 'Occupied'
            WHEN {$unitCounts['maintenance']} > 0 THEN 'Maintenance'
            ELSE properties.status END";
    }

    public function scopeSearch(Builder $query, ?string $search, ?User $manager = null): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        $createdDate = null;
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $search, $dateParts)
            && checkdate((int) $dateParts[2], (int) $dateParts[1], (int) $dateParts[3])) {
            $createdDate = sprintf('%04d-%02d-%02d', $dateParts[3], $dateParts[2], $dateParts[1]);
        }

        return $query->where(function (Builder $q) use ($search, $createdDate, $manager) {
            $q->where('properties.name', 'like', "%{$search}%")
                ->orWhere('properties.type', 'like', "%{$search}%")
                ->orWhereRaw(static::availabilityStatusSql($manager).' LIKE ?', ["%{$search}%"])
                ->orWhere('properties.address', 'like', "%{$search}%")
                ->orWhere('properties.city', 'like', "%{$search}%")
                ->orWhere('properties.postcode', 'like', "%{$search}%")
                ->orWhere('properties.state', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($owner) => $owner->where(function ($owner) use ($search) {
                    $owner->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                }))
                ->orWhere('properties.created_at', 'like', "%{$search}%")
                ->orWhereHas('units', fn ($units) => $units->when($manager, fn ($units) => $units->forViewing($manager))->where('units.unit_no', 'like', "%{$search}%"))
                ->orWhereHas('units.rooms', fn ($rooms) => $rooms->when($manager, fn ($rooms) => $rooms->whereHas('unit', fn ($units) => $units->forViewing($manager)))->where('rooms.room_no', 'like', "%{$search}%"));

            if ($createdDate !== null) {
                $q->orWhereDate('properties.created_at', $createdDate);
            }
        });
    }

    public function scopeWithSearchMatches(Builder $query, ?string $search, ?User $manager = null): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        return $query->with([
            'units' => fn ($units) => $units->when($manager, fn ($units) => $units->forViewing($manager))->where('units.unit_no', 'like', "%{$search}%")->orderBy('units.unit_no'),
            'rooms' => fn ($rooms) => $rooms->when($manager, fn ($rooms) => $rooms->whereHas('unit', fn ($units) => $units->forViewing($manager)))->where('rooms.room_no', 'like', "%{$search}%")
                ->with('unit:id,unit_no')->orderBy('rooms.room_no'),
        ]);
    }

    public function getFullAddressAttribute()
    {
        // 使用 PHP 8.0+ 的方式，确保字段为空时不会有奇怪的逗号
        return implode(', ', array_filter([
            $this->address,
            $this->postcode . ' ' . $this->city,
            $this->state
        ]));
    }

    protected function statusFromChildren($statuses): string
    {
        return $this->dominantUnitStatus($statuses->countBy()->all());
    }

    public function getUnitSummaryStatusAttribute(): ?string
    {
        if (in_array(strtolower(trim((string) $this->status)), ['removed', 'inactive'], true)) {
            return $this->status;
        }

        $counts = [
            'vacant' => (int) $this->vacant_units_count,
            'occupied' => (int) $this->occupied_units_count,
            'maintenance' => (int) $this->maintenance_units_count,
        ];

        return array_sum($counts) > 0 ? $this->dominantUnitStatus($counts) : $this->status;
    }

    protected function dominantUnitStatus(array $counts): string
    {
        $maximum = max($counts);

        // Break ties consistently: Vacant, then Occupied, then Maintenance.
        foreach (['vacant' => 'Vacant', 'occupied' => 'Occupied', 'maintenance' => 'Maintenance'] as $value => $label) {
            if (($counts[$value] ?? 0) === $maximum) {
                return $label;
            }
        }

        return $this->getAttribute('status');
    }

    public function childrenItems() { return $this->hasMany(Unit::class); }
}
