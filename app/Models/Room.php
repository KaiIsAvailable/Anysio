<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;
use App\Traits\SyncableStatus;
use App\Traits\Auditable;

class Room extends Model
{
    use HasUlids, SyncableStatus, Auditable;

    protected $table = 'rooms';
    protected $fillable = [
        'unit_id',
        'owner_id',
        'room_no',
        'room_type',
        'status',
        'created_by',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        $pattern = "%{$search}%";
        $createdDate = null;
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $search, $dateParts)
            && checkdate((int) $dateParts[2], (int) $dateParts[1], (int) $dateParts[3])) {
            $createdDate = sprintf('%04d-%02d-%02d', $dateParts[3], $dateParts[2], $dateParts[1]);
        } elseif (preg_match('/^\d{1,2}\s+[A-Za-z]+\s+\d{4}$/', $search)) {
            foreach (['!j M Y', '!j F Y'] as $format) {
                $date = \DateTimeImmutable::createFromFormat($format, $search);
                if ($date && \DateTimeImmutable::getLastErrors() === false) {
                    $createdDate = $date->format('Y-m-d');
                    break;
                }
            }
        }

        return $query->where(function (Builder $q) use ($pattern, $createdDate) {
            $q->where('rooms.room_no', 'like', $pattern)
                ->orWhere('rooms.room_type', 'like', $pattern)
                ->orWhere('rooms.status', 'like', $pattern)
                ->orWhere('rooms.created_at', 'like', $pattern)
                ->orWhereHas('unit.owner', fn ($owner) => $owner->where(function ($owner) use ($pattern) {
                    $owner->where('users.name', 'like', $pattern)
                        ->orWhere('users.email', 'like', $pattern);
                }))
                ->orWhereHas('assets', fn ($assets) => $assets->where('assets.name', 'like', $pattern));

            if ($createdDate !== null) {
                $q->orWhereDate('rooms.created_at', $createdDate);
            }
        });
    }

    public function assets(): BelongsToMany
    {
        // 关联 Asset 字典表，指定中间表为 asset_room；只返回当前 Active 的房间资产
        return $this->belongsToMany(Asset::class, 'asset_room')
                    ->withPivot(['id', 'status', 'condition', 'last_maintenance', 'remark'])
                    ->wherePivot('status', 'Active')
                    ->withTimestamps();
    }

    public function allAssets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_room')
                    ->withPivot(['id', 'status', 'condition', 'last_maintenance', 'remark'])
                    ->withTimestamps();
    }

    public function leases()
    {
        // 使用 morphMany 而不是 hasMany
        return $this->morphMany(Lease::class, 'leasable');
    }

    public function getFullAddressAttribute() {
        return $this->unit && $this->unit->property 
            ? $this->unit->property->full_address 
            : 'N/A';
    }

    public function parentItem() { return $this->belongsTo(Unit::class, 'unit_id'); }
}
