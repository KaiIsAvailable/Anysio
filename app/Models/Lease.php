<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lease extends Model
{
    use HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'leasable_type',
        'leasable_id',
        'parent_lease_id',
        'agreement_id',
        'is_current',
        'tenant_id',
        'start_date',
        'end_date',
        'checked_out_at',
        'agreement_ended_at',
        'term_type',
        'rent_price',
        'deposit_mode',
        'security_deposit',
        'utilities_deposit',
        'status',
        'stamping_status',
        'stamping_cert_path',
        'stamping_reference_no',
        'stamped_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'checked_out_at' => 'date',
        'agreement_ended_at' => 'date',
        'stamped_at' => 'date',
        'monthly_rent' => 'integer',
        'security_deposit' => 'integer',
        'utilities_depost' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Tenants::class, 'leasable_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenants::class, 'tenant_id');
    }

    public function utilities(): HasMany
    {
        return $this->hasMany(Utility::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
    public function payments(): HasMany
    {
        // 假设你的 Payment 模型中有一个 lease_id 外键
        return $this->hasMany(Payment::class);
    }

    public function agreement(): BelongsTo
    {
        // 参数 1: 关联的模型类名
        // 参数 2: 你 Lease 表里的外键名 (agreement_id)
        return $this->belongsTo(Agreements::class, 'agreement_id');
    }

    public function leasable()
    {
        return $this->morphTo();
    }

    public function user_management() {
        return $this->hasOne(UserManagement::class, 'user_id');
    }

    protected function leasableName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $model = $this->leasable;
                if (!$model) return 'N/A';

                // 根据不同的模型类名返回不同的字段
                return match (get_class($model)) {
                    \App\Models\Property::class => $model->name,
                    \App\Models\Unit::class     => $model->unit_no,
                    \App\Models\Room::class     => $model->room_no,
                    default                     => 'Unknown',
                };
            },
        );
    }

    /**
     * 获取更易读的类型名称
     */
    protected function leasableTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->leasable_type) {
                \App\Models\Property::class => 'Property',
                \App\Models\Unit::class     => 'Unit',
                \App\Models\Room::class     => 'Room',
                default                     => 'N/A',
            },
        );
    }

    protected function rentPrice(): Attribute
    {
        return Attribute::make(
            // 读取时：分 -> 元
            get: fn ($value) => $value / 100,
            // 写入时：元 -> 分 (关键修复)
            // 增加这个之后，无论是 500 还是 500.00，都会被存为 50000
            set: fn ($value) => (int) (round($value * 100)),
        );
    }

    // 同样的，押金也需要加上 set
    protected function securityDeposit(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) (round($value * 100)),
        );
    }

    protected function utilitiesDeposit(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            set: fn ($value) => (int) (round($value * 100)),
        );
    }

    protected function startDateFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->start_date ? $this->start_date->format('d/m/Y') : '-',
        );
    }

    /**
     * 获取格式化的结束日期：d/m/Y
     */
    protected function endDateFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->end_date ? $this->end_date->format('d/m/Y') : '-',
        );
    }

    /**
     * 获取格式化的退房日期
     */
    protected function checkedOutAtFormatted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->checked_out_at ? $this->checked_out_at->format('d/m/Y') : '-',
        );
    }

    protected function agreementEndedAtFormatted(): Attribute // 确保拼写是 Formatted
    {
        return Attribute::make(
            get: fn () => $this->agreement_ended_at ? $this->agreement_ended_at->format('d/m/Y') : '-',
        );
    }

    protected function stampedAtFormatted(): Attribute // 确保拼写是 Formatted
    {
        return Attribute::make(
            get: fn () => $this->stamped_at ? $this->stamped_at->format('d/m/Y') : '-',
        );
    }
}