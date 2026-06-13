<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Room;
use App\Models\Unit;
use App\Models\Property;
use App\Models\Tenants;
use App\Models\Agreements;
use App\Models\User;
use App\Models\Owners;
use App\Models\Utility;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Http\Controllers\PaymentsController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class LeaseController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $search = $request->input('search');
        $status = $request->input('status');

        // 1. 使用 leasable 预加载多态关联
        // 同时保留 tenant 关联
        $query = Lease::with([
            'leasable', // 这会自动加载 Room, Unit 或 Property
            'tenant.user',
        ]);

        // 2. 权限过滤：如果不是 super-admin，只能看自己拥有的资源
        // 这里需要根据你的业务逻辑调整，如果不同模型的 owner 字段不一样，可能需要更复杂的判断
        if (!Gate::allows('super-admin')) {
            $query->where(function ($q) use ($userId) {
                // A: 基于多态资源的归属权 (你原有的逻辑)
                $q->whereHasMorph('leasable', [Room::class, Unit::class, Property::class], function ($mq, $type) use ($userId) {
                    // the `owner` relation on Unit/Property points to the `users` table,
                    // so compare against `users.id` (->where('id', ...)) instead of `user_id`.
                    if ($type === Room::class) {
                        $mq->whereHas('unit.owner', function ($oq) use ($userId) {
                            $oq->where(function ($q) use ($userId) {
                                $q->where('created_by', $userId)
                                ->orWhere('owner_id', $userId);
                            });
                        });
                    } else {
                        $mq->whereHas('owner', function ($oq) use ($userId) {
                            $oq->where(function ($q) use ($userId) {
                                $q->where('created_by', $userId)
                                ->orWhere('owner_id', $userId);
                            });
                        });
                    }
                })
                    // B: 或者基于租户的创建者 (你新要求的逻辑)
                    // 只要满足其中一个条件，就能看到该租约
                    ->orWhereHas('tenant', function ($tq) use ($userId) {
                        $tq->where('created_by', $userId);
                    });
            });
        }

        // 3. 搜索逻辑更新
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('status', 'like', '%' . $search . '%')
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
                    ->orWhereHas('tenant.user', function ($tq) use ($search) {
                        $tq->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        // 4. 获取数据（既然去掉了 hover 展开，不需要 $groups 了）
        $leases = $query->orderBy('created_at', 'desc')
            ->where('is_current', true)
            ->paginate(10)
            ->onEachSide(1)
            ->appends($request->query());

        $statusOptions = ['New', 'Renew', 'Check out', 'End Agreement'];

        if ($request->ajax()) {
            return view('adminSide.leases._table', compact('leases', 'statusOptions'));
        }

        return view('adminSide.leases.index', compact('leases', 'statusOptions'));
    }

    public function create(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $authFilter = function ($query) use ($user) {
            if ($user->role === 'ownerAdmin' || $user->role === 'agentAdmin') {
                // 直接匹配 created_by 字段
                $query->where('created_by', $user->id);
            }
        };

        $properties = Property::with(['owner'])
            ->where('status', 'Vacant')
            // 排除掉那些有 Unit 不是 Vacant 的 Property
            ->whereDoesntHave('units', function ($q) {
                $q->where('status', '!=', 'Vacant');
            })
            // 排除掉那些有 Room 不是 Vacant 的 Property
            ->whereDoesntHave('units.rooms', function ($q) {
                $q->where('status', '!=', 'Vacant');
            })
            ->when($user->role !== 'admin', $authFilter)
            ->get();

        $units = Unit::with(['owner'])
            ->where('status', 'Vacant')
            // 排除掉那些有 Room 不是 Vacant 的 Unit
            ->whereDoesntHave('rooms', function ($q) {
                $q->where('status', '!=', 'Vacant');
            })
            ->when($user->role !== 'admin', function ($q) use ($authFilter) {
                $q->whereHas('owner', $authFilter);
            })
            ->get();

        $rooms = Room::with(['unit.owner'])
            ->where('status', 'Vacant')
            ->when($user->role !== 'admin', function ($q) use ($authFilter) {
                $q->whereHas('unit.owner', $authFilter);
            })
            ->get();

        $tenants = Tenants::with('user')
            ->when($user->role !== 'admin', function ($query) use ($user) {
                return $query->where('created_by', $user->id);
            })
            ->get();

        $status = $request->query('status');

        $leases = Lease::with([
                'tenant.user',
            ])
            ->where('is_current', true)
            // 根据请求的 status 切换查询逻辑
            ->when($status === 'End Agreement', function ($query) {
                // 如果是终止协议或退房，只显示目前处于 Active 状态的租约
                return $query->where('status', ['Check Out']);
            }, function ($query) {
                // 默认情况（比如 New 或 Renew），显示你原本定义的范围
                return $query->whereIn('status', ['New', 'Renew']);
            })
            // 权限过滤逻辑保持不变
            ->when($user->role !== 'admin', function ($query) use ($user) {
                $query->whereHas('tenant', function ($q) use ($user) {
                    $q->where('created_by', $user->id);
                });
            })
            ->get();

        $leasePreviewData = $leases->map(function ($lease) {
            $leasable = $this->getLeasableWithOwner($lease);
            return array_merge($lease->toArray(), [
                'leasable_name' => $this->getLeasableName($leasable),
                'leasable_address' => $this->getLeasableAddress($leasable),
                'owner_data' => $this->getOwnerData($leasable),
            ]);
        });

        $templates = Agreements::withTrashed()
            ->where('type', 'rental_lease')
            ->where('status', 'active')
            ->when($user->role !== 'admin', function ($query) use ($user) {
                // 1. 如果是 OwnerAdmin：只能看到自己创建的协议
                if ($user->role === 'ownerAdmin') {
                    $query->where('user_id', $user->id);
                }
                // 2. 如果是 AgentAdmin：可以看到自己负责的所有 Owner 创建的协议 + 自己创建的
                elseif ($user->role === 'agentAdmin') {
                    // 先找到此 Agent 负责的所有 Owner 的 user_id
                    $managedOwnerUserIds = Owners::where('agent_id', $user->id)
                        ->pluck('user_id');

                    $query->where(function ($q) use ($user, $managedOwnerUserIds) {
                        $q->where('user_id', $user->id)
                            ->orWhereIn('user_id', $managedOwnerUserIds);
                    });
                }
            })
            ->get();

        // 初始变量
        $selectedRoom = null;
        $selectedTenant = null;
        $statuses = ['New', 'Renew', 'Check out', 'End Agreement'];

        return view('adminSide.leases.create', compact(
            'properties',
            'units',
            'rooms',
            'tenants',
            'leases',
            'leasePreviewData',
            'statuses',
            'selectedRoom',
            'selectedTenant',
            'templates'
        ));
    }

    private function getLeasableWithOwner($lease)
    {
        if ($lease->leasable_type === 'App\Models\Property' || strpos($lease->leasable_type, 'Property') !== false) {
            return Property::with('owner')->find($lease->leasable_id);
        } elseif ($lease->leasable_type === 'App\Models\Unit' || strpos($lease->leasable_type, 'Unit') !== false) {
            return Unit::with('owner')->find($lease->leasable_id);
        } elseif ($lease->leasable_type === 'App\Models\Room' || strpos($lease->leasable_type, 'Room') !== false) {
            return Room::with('unit.owner')->find($lease->leasable_id);
        }
        return null;
    }

    private function getLeasableName($leasable)
    {
        if ($leasable) {
            if ($leasable instanceof Property) {
                return $leasable->name;
            } elseif ($leasable instanceof Unit) {
                return $leasable->unit_no;
            } elseif ($leasable instanceof Room) {
                return $leasable->room_no;
            }
        }
        return '';
    }

    private function getLeasableAddress($leasable)
    {
        if ($leasable) {
            if ($leasable instanceof Property) {
                return $leasable->full_address ?? '';
            } elseif ($leasable instanceof Unit) {
                return $leasable->full_address ?? '';
            } elseif ($leasable instanceof Room) {
                return $leasable->full_address ?? '';
            }
        }
        return '';
    }

    private function getOwnerData($leasable)
    {
        if ($leasable) {
            if ($leasable instanceof Property && $leasable->owner) {
                return [
                    'id' => $leasable->owner->id ?? '',
                    'name' => $leasable->owner->name ?? '',
                    'ic_number' => $leasable->owner->ic_number ?? '',
                ];
            } elseif ($leasable instanceof Unit && $leasable->owner) {
                return [
                    'id' => $leasable->owner->id ?? '',
                    'name' => $leasable->owner->name ?? '',
                    'ic_number' => $leasable->owner->ic_number ?? '',
                ];
            } elseif ($leasable instanceof Room && $leasable->unit && $leasable->unit->owner) {
                return [
                    'id' => $leasable->unit->owner->id ?? '',
                    'name' => $leasable->unit->owner->name ?? '',
                    'ic_number' => $leasable->unit->owner->ic_number ?? '',
                ];
            }
        }
        return ['id' => '', 'name' => '', 'ic_number' => ''];
    }

    public function store(Request $request)
    {
        // 1. 验证数据
        $validated = $request->validate([
            'status' => 'required|string|in:New,Renew,Check Out,End Agreement',
            'lease_id' => 'required_unless:status,New|nullable|exists:leases,id',

            'lease_selection' => 'required_if:status,New|nullable|in:property,unit,room',

            'tenant_id' => 'required_if:status,New|nullable|exists:tenants,id',

            'start_date' => 'required_if:status,New,Renew|nullable|date',
            // 💡 修复点 1: 改为 after_or_equal，允许 start 和 end 是同一天（1天）
            'end_date' => 'required_if:status,New,Renew|nullable|date|after_or_equal:start_date',
            'checked_out_at' => 'required_if:status,Check Out|nullable|date',
            'agreement_ended_at' => 'required_if:status,End Agreement|nullable|date',
            'rent_price' => 'required_if:status,New,Renew|nullable|numeric|min:1',
            'term_type' => 'required_if:status,New,Renew|nullable|string',
            'security_deposit' => 'nullable|numeric|min:1',
            'utilities_deposit' => 'nullable|numeric|min:1',
            'agreement_id' => 'required_if:status,New,Renew',
        ]);

        // 💡 修复点 2: 后端二次校验 Fee Type 是否合法，防止恶意绕过前端
        if (in_array($validated['status'], ['New', 'Renew']) && !empty($validated['start_date']) && !empty($validated['end_date'])) {
            $start = Carbon::parse($validated['start_date']);
            $end = Carbon::parse($validated['end_date']);

            $diffDays = $start->diffInDays($end);
            $diffMonths = $start->diffInMonths($end);
            $diffYears = $start->diffInYears($end);

            $allowedFeeTypes = ['daily']; // 永远可以选择 daily

            if ($diffDays >= 7 && $diffMonths < 1) {
                $allowedFeeTypes[] = 'weekly';
            } elseif ($diffMonths >= 1 && $diffYears < 1) {
                $allowedFeeTypes[] = 'weekly';
                $allowedFeeTypes[] = 'monthly';
            } elseif ($diffYears >= 1) {
                $allowedFeeTypes = ['daily', 'weekly', 'monthly', 'yearly'];
            }

            if (!in_array($validated['term_type'], $allowedFeeTypes)) {
                return back()->withErrors(['term_type' => 'The selected Fee Type is not valid for the chosen date range.'])->withInput();
            }
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 步骤 1：先紧急验证最基础的状态
        $baseRules = [
            'status' => 'required|string|in:New,Renew,Check Out,End Agreement',
        ];
        $baseValidated = $request->validate($baseRules);
        $status = $baseValidated['status'];

        // 步骤 2：仅当创建“新租约 (New)”时，才进行套餐限额拦截！
        if ($status === 'New') {
            $leasableId = $request->input($validated['lease_selection'] . '_id');
            $leasableType = match ($validated['lease_selection']) {
                'property' => Property::class,
                'unit' => Unit::class,
                'room' => Room::class,
            };

            // 动态查询该房源的 owner_id
            $leasable = $leasableType::find($leasableId);
            if (!$leasable) {
                return back()->withErrors(['error' => 'Property/Unit/Room not found.']);
            }

            // 递归获取 owner
            $ownerId = match ($validated['lease_selection']) {
                'room' => $leasable->unit->owner_id,
                'unit' => $leasable->owner_id,
                'property' => $leasable->owner_id,
            };

            // 获取该业主的套餐进行限额校验
            $owner = User::find($ownerId);

            /** @var \App\Models\UserManagement|null $management */
            if ($user->role === 'admin') {
                $management = $owner->user_management;
            } else {
                $management = $user->user_management;
            }

            // 检查套餐是否存在
            if ($user->role === 'admin'){

            }else if(!$management || !$management->package) {
                return back()->with('error', 'You do not have an active subscription package.');
            }

            $package = $management->package;
            $baseLeaseLimit = $package->base_lease;
            $extraLeaseLimit = $management->extra_lease;
            $totalLeaseLimit = $baseLeaseLimit + $extraLeaseLimit;

            // 计算当前活跃的主租约数量
            $currentLeaseCount = Lease::where('is_current', 1)
                ->whereIn('status', ['New', 'Renew', 'Check Out']) // 只要没到 End Agreement，都算占用
                ->when($user->role !== 'admin', function ($query) use ($user) {
                    return $query->whereHas('tenant', function ($q) use ($user) {
                        $q->where('created_by', $user->id);
                    });
                })
                ->count();

            // 达到上限，拒绝创建新租约
            if ($user->role !== 'admin' && $currentLeaseCount >= $totalLeaseLimit) {
                return back()->with('error', "Limit Reached: Your current package ({$package->name}) only allows {$baseLeaseLimit} leases. You cannot create a NEW lease, but you can still Renew or Check Out existing ones.");
            }
        }

        // 预提取旧租约 (仅 Renew/Check Out/End)
        $oldLease = null;
        if ($validated['status'] !== 'New') {
            $oldLease = Lease::findOrFail($validated['lease_id']);
        }

        if ($validated['status'] === 'Renew' && $oldLease) {
            $request->validate([
                'start_date' => [
                    'required',
                    'date',
                    'after_or_equal:' . Carbon::parse($oldLease->end_date)->toDateString(),
                ],
            ], [
                'start_date.after_or_equal' => 'Your start date must be after or equal to the previous end date.',
            ]);
        }

        // 确定物理属性 (房产信息和租客)
        if ($validated['status'] === 'New') {
            $leasableId = match ($validated['lease_selection']) {
                'property' => $request->property_id ?? null,
                'unit' => $request->unit_id ?? null,
                'room' => $request->room_id ?? null,
            };

            if (!$leasableId) {
                return back()->withErrors(['lease_selection' => 'Please select a valid property/unit/room.'])->withInput();
            }

            $leasableType = match ($validated['lease_selection']) {
                'property' => Property::class,
                'unit' => Unit::class,
                'room' => Room::class,
            };

            $tenantId = $validated['tenant_id'];
        } else {
            // Renew/Check Out 模式：直接从旧记录继承
            $leasableId = $oldLease->leasable_id;
            $leasableType = $oldLease->leasable_type;
            $tenantId = $oldLease->tenant_id;
        }

        // 金额计算 (转为 Cents)
        $sDep = $validated['security_deposit'] ?? 0;
        $uDep = $validated['utilities_deposit'] ?? 0;

        $depositMode = 'none';
        if ($sDep > 0 && $uDep > 0)
            $depositMode = 'both';
        elseif ($sDep > 0)
            $depositMode = 'security_only';
        elseif ($uDep > 0)
            $depositMode = 'utilities_only';

        // 执行数据库事务
        DB::transaction(function () use ($validated, $oldLease, $leasableType, $leasableId, $tenantId, $sDep, $uDep, $depositMode) {

            if ($oldLease) {
                $oldLease->update(['is_current' => false]);
            }

            // 1. 获取模型实例并更新状态 (这样内存中的对象 status 也是最新的)
            $leasable = $leasableType::findOrFail($leasableId);
            
            $targetRoomStatus = match ($validated['status']) {
                'Check Out' => 'Cleaning',
                'End Agreement' => 'Vacant',
                default => 'Occupied',
            };
            
            $leasable->propagateStatus($targetRoomStatus);
            $leasable->update(['status' => $targetRoomStatus]);

            if (in_array($validated['status'], ['Check Out', 'End Agreement'])) {
                $leasable->syncStatus();
            }

            Log::info("更新后的状态: " . $leasable->fresh()->status);

            // 创建新记录
            $newLease = Lease::create([
                'parent_lease_id' => $oldLease?->id,
                'agreement_id' => $validated['agreement_id'],
                'is_current' => true,
                'leasable_type' => $leasableType,
                'leasable_id' => $leasableId,
                'tenant_id' => $tenantId,
                'start_date' => in_array($validated['status'], ['New', 'Renew'])
                    ? $validated['start_date']
                    : $oldLease?->start_date,
                'end_date' => in_array($validated['status'], ['New', 'Renew'])
                    ? $validated['end_date']
                    : $oldLease?->end_date,
                'checked_out_at' => $validated['status'] === 'Check Out' ? $validated['checked_out_at'] : null,
                'agreement_ended_at' => $validated['status'] === 'End Agreement' ? $validated['agreement_ended_at'] : null,
                'term_type' => $validated['term_type'] ?? $oldLease?->term_type,
                'rent_price' => ($validated['status'] === 'New' || $validated['status'] === 'Renew')
                    ? ($validated['rent_price'] ?? 0)
                    : ($oldLease?->rent_price ?? 0),
                'deposit_mode' => $depositMode,
                'security_deposit' => $sDep,
                'utilities_deposit' => $uDep,
                'status' => $validated['status'],
            ]);

            if (in_array($validated['status'], ['New', 'Renew'])) {
                $paymentController = new PaymentsController();
                $paymentController->generateMonthlyInvoice($newLease->id);

                $depositTypes = [
                    'Security Deposit'  => $sDep,
                    'Utilities Deposit' => $uDep,
                ];

                foreach ($depositTypes as $type => $amount) {
                    if ($amount > 0) {
                        $paymentController->storeManualInvoiceLogic($newLease, [
                            'payment_type' => $type,
                            'amount_due'   => $amount,
                            'period'       => $newLease->start_date,
                            'remarks'      => 'Initial ' . $type
                        ]);
                    }
                }
            }
        });

        return redirect()->route('admin.leases.index')->with('success', 'Lease, first rent invoice and deposit invoice generated successfully.');
    }

    public function show(Request $request, Lease $lease)
    {
        // 如果是 AJAX 请求 (点击 Progression 切换)
        if ($request->ajax()) {
            $targetId = $request->get('lease_id');
            // 查找特定的 Lease，确保它属于当前链条（安全起见）
            $targetLease = Lease::findOrFail($targetId);

            $rentPayments = $targetLease->payments()
                ->where('payment_type', 'rent')
                ->where('status', '!=', 'void')
                ->orderBy('period', 'desc')
                ->latest()
                ->paginate(5, ['*'], 'rent_page')
                ->onEachSide(1);

            $otherPayments = $targetLease->payments()
                ->where('payment_type', '!=', 'rent')
                ->where('status', '!=', 'void')
                ->latest()
                ->paginate(5, ['*'], 'other_page')
                ->onEachSide(1);

            // 返回专门的局部视图
            return  view('adminSide.tenants.payments.partial_overview', [
                'lease' => $targetLease,
                'rentPayments' => $rentPayments,
                'otherPayments' => $otherPayments
            ])->render();
        }

        // 加载当前租约需要的关联
        $lease->load([
            'tenant.user',
            'utilities',
            'agreement', // 别忘了加载合同模板
            'leasable' => function ($morphTo) {
                $morphTo->morphWith([
                    Room::class => ['owner', 'assets'],
                    Unit::class => ['owner'],
                    Property::class => ['owner'],
                ]);
            }
        ]);

        // --- 开始获取历史链条 ---
        $leaseHistory = collect([$lease]); // 先把当前的放进去
        $current = $lease;

        // 只要有 parent_lease_id，就一直往上找
        while ($current->parent_lease_id) {
            $parent = Lease::with(['tenant.user', 'room'])->find($current->parent_lease_id);
            if ($parent) {
                $leaseHistory->push($parent);
                $current = $parent;
            } else {
                break;
            }
        }

        $rentPayments = $lease->payments()
            ->where('payment_type', 'rent')
            ->where('status', '!=', 'void')
            ->orderBy('period', 'desc')
            ->latest()
            ->paginate(5, ['*'], 'rent_page')
            ->onEachSide(1);

        // 2. 如果你还想显示其他类型的费用（比如押金、水电费）
        $otherPayments = $lease->payments()
            ->where('payment_type', '!=', 'rent')
            ->where('status', '!=', 'void')
            ->latest()
            ->paginate(5, ['*'], 'other_page')
            ->onEachSide(1);

        // 将结果按时间正序排列（从最老的到最新的）
        $leaseHistory = $leaseHistory->reverse();

        return view('adminSide.leases.show', compact('lease', 'leaseHistory', 'rentPayments', 'otherPayments'));
    }

    public function edit()
    {
        abort(403);
    }

    public function update()
    {
        abort(403);
    }

    public function destroy()
    {
        abort(403);
    }

    private function toCents($value): int
    {
        $sanitized = preg_replace('/[^0-9.]/', '', (string) $value);
        if ($sanitized === '') {
            return 0;
        }

        return (int) round(((float) $sanitized) * 100);
    }

    public function getDetailsJson(Lease $lease)
    {
        return view('admin.leases.partials.details-content', [
            'lease' => $lease
        ])->render();
    }

    public function uploadStamping(Request $request, Lease $lease)
    {
        $validated = $request->validate([
            'stamping_reference_no' => 'required|string|max:100',
            'stamping_cert' => 'required|mimes:pdf|max:2048',
        ]);

        try {
            if ($request->hasFile('stamping_cert')) {
                if ($lease->stamping_cert_path && Storage::disk('local')->exists($lease->stamping_cert_path)) {
                    Storage::disk('local')->delete($lease->stamping_cert_path);
                }

                $path = $request->file('stamping_cert')->store('stamping_certs', 'local');

                $lease->update([
                    'stamping_status' => true,
                    'stamping_cert_path' => $path,
                    'stamping_reference_no' => $validated['stamping_reference_no'],
                    'stamped_at' => now(),
                ]);
            }

            return back()->with('success', 'Stamping certificate uploaded successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Something went wrong: ' . $e->getMessage())->withInput();
        }
    }

    public function viewCert(Lease $lease)
    {
        if (empty($lease->stamping_cert_path)) {
            abort(404, 'No certificate path record.');
        }

        $path = storage_path('app/private/' . $lease->stamping_cert_path);

        if (!file_exists($path)) {
            abort(404, 'File not found on server.');
        }

        $fileContent = file_get_contents($path);
        $base64 = base64_encode($fileContent);
        $pdfData = 'data:application/pdf;base64,' . $base64;

        return view('adminSide.leases.view-cert', compact('pdfData', 'lease'));
    }

    public function getPaymentsTableOnly(Lease $lease)
    {
        $payments = $lease->payments()->latest()->get();

        return view('your.table.path', [
            'payments' => $payments,
            'emptyMessage' => 'No records for this lease.'
        ])->render();
    }

    public function refreshPaymentsTable(Lease $lease)
    {
        $canGenerate = !is_null(PaymentsController::calculateNextPendingPeriod($lease));
        $allPayments = $lease->payments()
            ->where('status', '!=', 'void')
            ->latest()->get();

        $rentPayments = $allPayments->filter(fn($p) => in_array($p->payment_type, ['rent']));
        $otherPayments = $allPayments->filter(fn($p) => !in_array($p->payment_type, ['rent']));

        $rentHtml = view('adminSide.tenants.payments.paymentTable', [
            'payments' => $rentPayments,
            'emptyMessage' => 'No outstanding rent found.'
        ])->render();

        $otherHtml = view('adminSide.tenants.payments.paymentTable', [
            'payments' => $otherPayments,
            'emptyMessage' => 'No miscellaneous records found.'
        ])->render();

        return response()->json([
            'rentHtml' => $rentHtml,
            'otherHtml' => $otherHtml,
            'can_generate' => $canGenerate
        ]);
    }
}
