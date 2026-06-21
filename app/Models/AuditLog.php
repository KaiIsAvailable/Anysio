<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class AuditLog extends Model
{
    use HasUlids; // 自动生成 ULID

    protected $fillable = [
        'user_id', 'event', 'auditable_type', 
        'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
}