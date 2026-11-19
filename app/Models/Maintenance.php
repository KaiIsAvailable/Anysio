<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Maintenance extends Model
{
    use HasUlids, Auditable, SoftDeletes;

    // 指定表名
    protected $table = 'maintenance';

    protected $fillable = [
        'id', 'maintenance_number', 'source_type', 'ticket_id', 'management_user_id', 'location_snapshot', 'location_detail',
        'fee_type_id', 'qty', 'manual_request_key', 'manual_payload_hash', 'refund_id', 'refund_item_id',
        'contractor_name', 'contractor_phone', 'assigned_staff_id', 'created_by', 'assigned_by', 'assigned_at', 'completed_by', 'completed_at', 'version',
        'lease_id', 'leasable_type', 'leasable_id',
        'asset_room_id',
        'title',
        'desc',
        'photo_path',
        'status',
        'cost',
        'paid_by',
    ];

    protected $casts = [
        'cost' => 'integer', 'qty' => 'integer', 'version' => 'integer', 'assigned_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');

        return $query->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('title', 'like', '%'.$search.'%')
            ->orWhere('maintenance_number', 'like', '%'.$search.'%')
            ->orWhere('location_snapshot', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['created_year'] ?? null, fn (Builder $query, $year) => $query->whereYear('created_at', $year))
            ->when($filters['created_month'] ?? null, fn (Builder $query, $month) => $query->whereMonth('created_at', $month));
    }

    public function scopeOngoing(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'maintaining']);
    }

    public function ticket() { return $this->belongsTo(Ticket::class); }
    public function attachments() { return $this->hasMany(MaintenanceAttachment::class); }
    public function events() { return $this->hasMany(MaintenanceEvent::class)->orderBy('created_at')->orderBy('id'); }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function leasable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function getLeasableNameAttribute(): string
    {
        return $this->leasable?->name ?? $this->leasable?->unit_no ?? $this->leasable?->room_no ?? $this->location_snapshot ?? 'Not recorded';
    }

    public function getLeasableTypeLabelAttribute(): string
    {
        return $this->leasable_type ? class_basename($this->leasable_type) : '';
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function refundItem(): BelongsTo
    {
        return $this->belongsTo(RefundItem::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class, 'asset_room_id');
    }
}
