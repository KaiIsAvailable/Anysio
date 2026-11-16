<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class Maintenance extends Model
{
    use HasUlids, Auditable;

    // 指定表名
    protected $table = 'maintenance';

    protected $fillable = [
        'id', 'maintenance_number', 'source_type', 'ticket_id', 'management_user_id', 'location_snapshot', 'location_detail',
        'contractor_name', 'contractor_phone', 'created_by', 'assigned_by', 'assigned_at', 'completed_by', 'completed_at', 'version',
        'lease_id',
        'asset_room_id',
        'title',
        'desc',
        'photo_path',
        'status',
        'cost',
        'paid_by',
    ];

    protected $casts = [
        'cost' => 'integer', 'version' => 'integer', 'assigned_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function ticket() { return $this->belongsTo(Ticket::class); }
    public function attachments() { return $this->hasMany(MaintenanceAttachment::class); }
    public function events() { return $this->hasMany(MaintenanceEvent::class)->orderBy('created_at')->orderBy('id'); }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(RoomAsset::class, 'asset_room_id');
    }
}