<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\Auditable;
use Illuminate\Http\Request;

class Lease extends Model
{
    use HasUlids, Auditable;

    protected $table = 'leases';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'leasable_type',
        'leasable_id',
        'parent_lease_id',
        'document_id',
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
        'is_pending_renewal',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'stamping_status',
        'stamping_cert_path',
        'stamping_reference_no',
        'stamped_at',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'checked_out_at' => 'date:Y-m-d',
        'agreement_ended_at' => 'date:Y-m-d',
        'stamped_at' => 'date:Y-m-d',
        'monthly_rent' => 'integer',
        'security_deposit' => 'integer',
        'utilities_depost' => 'integer',
        'is_pending_renewal' => 'boolean',
        'is_current' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Tenants::class, 'leasable_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenants::class, 'tenant_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    protected function ownerUserId(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 1. 获取关联的 leasable 模型 (Property/Unit/Room)
                $model = $this->leasable;
                if (!$model) return null;

                // 2. 根据类型往上找 owner_id
                return match (get_class($model)) {
                    \App\Models\Property::class => $model->owner_id,
                    \App\Models\Unit::class     => $model->property->owner_id ?? null,
                    \App\Models\Room::class     => $model->unit->property->owner_id ?? null,
                    default                     => null,
                };
            },
        );
    }

    public function utilities(): HasMany
    {
        return $this->hasMany(Utility::class);
    }

    public function unit(): BelongsTo
    {
        // 假设 Lease 表里有 unit_id 字段
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function documentTemplate(): BelongsTo
    {
        // 参数 1: 关联的模型类名
        // 参数 2: 你 Lease 表里的外键名 (agreement_id)
        return $this->belongsTo(DocumentTemplate::class, 'document_id');
    }

    public function leasable()
    {
        return $this->morphTo();
    }

    public function user_management() {
        return $this->hasOne(UserManagement::class, 'user_id');
    }

    public function parentLease()
    {
        return $this->belongsTo(Lease::class, 'parent_lease_id')
                    ->with(['charges.feeType', 'parentLease']); 
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

    // All charges
    public function charges(): HasMany
    {
        return $this->hasMany(LeaseCharge::class)->orderBy('sort_order');
    }

    // Scoped shortcuts
    public function recurringCharges(): HasMany
    {
        return $this->hasMany(LeaseCharge::class)
                    ->where('charge_type', LeaseCharge::TYPE_RECURRING)
                    ->where('is_active', true)
                    ->orderBy('sort_order');
    }

    public function refundableCharges(): HasMany
    {
        return $this->hasMany(LeaseCharge::class)
                    ->where('charge_type', LeaseCharge::TYPE_REFUNDABLE)
                    ->where('is_active', true)
                    ->orderBy('sort_order');
    }

    public function oneTimeCharges(): HasMany
    {
        return $this->hasMany(LeaseCharge::class)
                    ->where('charge_type', LeaseCharge::TYPE_ONE_TIME)
                    ->where('is_active', true)
                    ->orderBy('sort_order');
    }

    // Financial summaries
    public function getTotalMonthlyAmountAttribute(): int
    {
        return $this->recurringCharges()->sum('amount');
    }

    public function getTotalDepositAmountAttribute(): int
    {
        return $this->refundableCharges()->sum('amount');
    }

    //Search
    public function scopeSearch($query, ?string $search)
    {
        if (!$search) {
            return $query;
        }

        $searchLower = strtolower(trim($search));

        $numericSearch = preg_replace('/[^\d.]/', '', $search);
        $searchAmountCents = is_numeric($numericSearch) ? (float) $numericSearch * 100 : null;

        return $query->where(function ($q) use ($search, $searchLower, $searchAmountCents) {
            // 1. Status search
            $q->where('leases.status', 'like', '%' . $search . '%')

                ->orWhere(function ($pendingQuery) use ($searchLower) {
                    if (str_contains($searchLower, 'pending') || str_contains($searchLower, 'renewal')) {
                        $pendingQuery->where('leases.is_pending_renewal', true);
                    }
                })
                
                // 2. Leasable type keyword matches ('room', 'unit', 'property')
                ->orWhere(function ($typeQuery) use ($searchLower) {
                    if ($searchLower === 'room' || str_contains('room', $searchLower)) {
                        $typeQuery->orWhere('leasable_type', Room::class);
                    }
                    if ($searchLower === 'unit' || str_contains('unit', $searchLower)) {
                        $typeQuery->orWhere('leasable_type', Unit::class);
                    }
                    if ($searchLower === 'property' || str_contains('property', $searchLower)) {
                        $typeQuery->orWhere('leasable_type', Property::class);
                    }
                })

                // 3. Date Range Search (e.g., "01/11/2026 to 31/12/2026")
                ->orWhere(function ($dateQuery) use ($search) {
                    if (str_contains(strtolower($search), ' to ')) {
                        [$rawStart, $rawEnd] = explode(' to ', strtolower($search));
                        $startDate = $this->parseUserDate(trim($rawStart));
                        $endDate = $this->parseUserDate(trim($rawEnd));

                        if ($startDate && $endDate) {
                            // Exactly matches leases whose start and end dates fall within this window
                            $dateQuery->where('leases.start_date', '>=', $startDate)
                                    ->where('leases.end_date', '<=', $endDate);
                        }
                    } else {
                        // Single date search
                        $formattedDate = $this->parseUserDate(trim($search));
                        if ($formattedDate) {
                            $dateQuery->whereDate('leases.start_date', $formattedDate)
                                    ->orWhereDate('leases.end_date', $formattedDate);
                        }
                    }
                })

                // 4. Polymorphic assets & owner search
                ->orWhereHasMorph('leasable', [Room::class, Unit::class, Property::class], function ($mq, $type) use ($search) {
                    $mq->where(function ($query) use ($search, $type) {
                        if ($type === Room::class) {
                            $query->where('room_no', 'like', '%' . $search . '%');
                        } elseif ($type === Unit::class) {
                            $query->where('unit_no', 'like', '%' . $search . '%');
                        } elseif ($type === Property::class) {
                            $query->where('name', 'like', '%' . $search . '%');
                        }
                    })
                    ->orWhere(function ($query) use ($search, $type) {
                        if ($type === Room::class) {
                            $query->whereHas('unit.owner', function ($oq) use ($search) {
                                $oq->where('name', 'like', '%' . $search . '%');
                            });
                        } else {
                            $query->whereHas('owner', function ($oq) use ($search) {
                                $oq->where('name', 'like', '%' . $search . '%');
                            });
                        }
                    });
                })

                // 5. Tenant user name search
                ->orWhereHas('tenant.user', function ($tq) use ($search) {
                    $tq->where('name', 'like', '%' . $search . '%');
                })

                ->orWhereHas('charges', function ($cq) use ($search) {
                    $cq->where('description', 'like', '%' . $search . '%');
                })

                ->orWhereHas('charges', function ($cq) use ($searchAmountCents) {
                    if ($searchAmountCents !== null) {
                        $cq->where('amount', 'like', '%' . $searchAmountCents . '%');
                    }
                })

                ->when($searchAmountCents !== null, function ($totalQuery) use ($searchAmountCents) {
                    $totalQuery->orWhereRaw(
                        '(select COALESCE(sum(amount), 0) from lease_charges where lease_charges.lease_id = leases.id and lease_charges.deleted_at is null) = ?',
                        [$searchAmountCents]
                    );
                });
        });
    }

    /**
     * Helper to convert user-inputted dates (DD/MM/YYYY or similar) to YYYY-MM-DD
     */
    private function parseUserDate(string $dateStr): ?string
    {
        // Try matching common DD/MM/YYYY or DD-MM-YYYY formats
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateStr, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            return "{$year}-{$month}-{$day}";
        }

        // Fallback attempt via standard strtotime if user types something standard
        $timestamp = strtotime($dateStr);
        return $timestamp ? date('YYYY-MM-DD', $timestamp) : null;
    }

    public function scopeApplyFilters($query, Request $request)
    {
        if ($request->filled('leaseable_name')) {
            $typeSearch = $request->input('leaseable_name');
            
            if ($typeSearch === 'Room') {
                $query->where('leasable_type', Room::class);
            } elseif ($typeSearch === 'Unit') {
                $query->where('leasable_type', Unit::class);
            } elseif ($typeSearch === 'Property') {
                $query->where('leasable_type', Property::class);
            }
        }

        // 1. Property / Unit / Room Filter
        if ($request->filled('leaseable')) {
            $leaseableSearch = $request->input('leaseable');
            $query->whereHasMorph('leasable', [Room::class, Unit::class, Property::class], function ($mq, $type) use ($leaseableSearch) {
                if ($type === Room::class) {
                    $mq->where('room_no', 'like', '%' . $leaseableSearch . '%');
                } elseif ($type === Unit::class) {
                    $mq->where('unit_no', 'like', '%' . $leaseableSearch . '%');
                } elseif ($type === Property::class) {
                    $mq->where('name', 'like', '%' . $leaseableSearch . '%');
                }
            });
        }

        // 2. Owner Filter
        if ($request->filled('owner')) {
            $ownerSearch = $request->input('owner');
            $query->whereHasMorph('leasable', [Room::class, Unit::class, Property::class], function ($mq, $type) use ($ownerSearch) {
                if ($type === Room::class) {
                    $mq->whereHas('unit.owner', function ($oq) use ($ownerSearch) {
                        $oq->where('name', 'like', '%' . $ownerSearch . '%');
                    });
                } else {
                    $mq->whereHas('owner', function ($oq) use ($ownerSearch) {
                        $oq->where('name', 'like', '%' . $ownerSearch . '%');
                    });
                }
            });
        }

        // 3. Tenant Filter
        if ($request->filled('tenant')) {
            $tenantSearch = $request->input('tenant');
            $query->whereHas('tenant.user', function ($tq) use ($tenantSearch) {
                $tq->where('name', 'like', '%' . $tenantSearch . '%');
            });
        }

        // 4. Start Date Filter
        if ($request->filled('start_date')) {
            $query->where('leases.start_date', '>=', $request->input('start_date'));
        }

        // 5. End Date Filter
        if ($request->filled('end_date')) {
            $query->where('leases.end_date', '<=', $request->input('end_date'));
        }

        // 6. Status Filter
        if ($request->filled('status')) {
            $query->where('leases.status', $request->input('status'));
        }

        // 7. Pending Renewal Toggle Filter
        if ($request->filled('show_pending_renewal')) {
            $query->where('leases.is_pending_renewal', true);
        }

        // 8. Charges Min Amount Filter (converted from RM to cents)
        if ($request->filled('min_amount')) {
            $minCents = (float) $request->input('min_amount') * 100;
            $query->whereRaw(
                '(select COALESCE(sum(amount), 0) from lease_charges where lease_charges.lease_id = leases.id and lease_charges.deleted_at is null) >= ?',
                [$minCents]
            );
        }

        // 9. Charges Max Amount Filter (converted from RM to cents)
        if ($request->filled('max_amount')) {
            $maxCents = (float) $request->input('max_amount') * 100;
            $query->whereRaw(
                '(select COALESCE(sum(amount), 0) from lease_charges where lease_charges.lease_id = leases.id and lease_charges.deleted_at is null) <= ?',
                [$maxCents]
            );
        }

        // 10. Stamping Status Filter
        if ($request->filled('stampingStatus')) {
            $stampingStatus = $request->input('stampingStatus');
            if ($stampingStatus === 'Stamped') {
                $query->where('leases.stamping_status', true);
            } elseif ($stampingStatus === 'Pending Stamping') {
                $query->where('leases.stamping_status', false)
                    ->whereNotIn('leases.status', ['Check Out', 'End']);
            } elseif ($stampingStatus === 'No Need Stamping') {
                $query->whereIn('leases.status', ['Check Out', 'End']);
            }
        }

        return $query;
    }
}