<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Payment extends Model
{
    use HasUlids;

    protected $fillable = [
        'lease_id',        // 关联哪份合约
        'tenant_id',       // 关联哪个学生/租客
        'invoice_no',
        'payment_type',    // 'rent', 'deposit_security', 'agreement', etc.
        'period',          // 哪个月的账单 (2023-10-01)
        'amount_due',      // 应付 (cents)
        'amount_paid',     // 实付 (cents)
        'amount_over_paid',
        'amount_under_paid',
        'payment_date',
        'payment_method',  // 'cash', 'bank_transfer'
        'transaction_ref', // 银行 Reference No
        'received_via',    // 我们的哪个银行 (Maybank/Public Bank)
        'receipt_path',
        'status',          // 'unpaid', 'pending', 'paid', 'rejected'
        'attachment',
        'approved_by',
        'approved_at',
        'remarks',       
    ];

    protected $casts = [
        'amount_due' => 'integer',
        'amount_paid' => 'integer',
        'period' => 'date',         // 自动转成 Carbon 对象，方便格式化
        'approved_at' => 'datetime',
    ];

    // --- Relationships ---

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function tenant(): BelongsTo
    {
        // 建议类名用单数 Tenant，如果你数据库表是 tenants
        return $this->belongsTo(Tenants::class, 'tenant_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Payment.php
    protected function amountPaid(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
            // 这里不写 set，或者原样返回：set: fn ($value) => $value
        );
    }

    protected function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value / 100,
        );
    }

    protected function periodDisplay(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->period) return null;

            $date = Carbon::parse($this->period);
            $lease = $this->lease; // 获取关联的租约
            $termType = strtolower($lease->term_type ?? 'monthly');

            $formatted = match($termType) {
                'daily'   => $date->format('d/m/Y'),

                'weekly'  => (function() use ($date, $lease) {
                    $start = $date->format('d/m/Y');
                    
                    // 默认结束日期是起始日 + 6 天
                    $expectedEnd = $date->copy()->addDays(6);
                    
                    // 获取租约真实的结束日期
                    $leaseEnd = $lease ? Carbon::parse($lease->end_date) : $expectedEnd;

                    // 如果 +6 天超过了租约结束日，则取租约结束日
                    $actualEnd = $expectedEnd->gt($leaseEnd) ? $leaseEnd : $expectedEnd;

                    return $start . ' - ' . $actualEnd->format('d/m/Y');
                })(),

                'monthly' => $date->format('M Y'),

                'yearly'  => $date->format('Y'),

                default   => $date->format('M Y'),
            };

            return "For " . $formatted;
        });
    }
}