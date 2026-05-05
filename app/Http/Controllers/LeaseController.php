<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Room;
use App\Models\Unit;
use App\Models\Property;
use App\Models\Tenants;
use App\Models\Agreements;
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
                    if ($type === Room::class) {
                        $mq->whereHas('unit.owner', fn($oq) => $oq->where('user_id', $userId));
                    } else {
                        $mq->whereHas('owner', fn($oq) => $oq->where('user_id', $userId));
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
                    // 搜索多态关联的数据
                    ->orWhereHasMorph('leasable', ['Room', 'Unit', 'Property'], function ($mq, $type) use ($search) {
                        if ($type === 'Room') {
                            $mq->where('room_no', 'like', '%' . $search . '%');
                        } elseif ($type === 'Unit') {
                            $mq->where('unit_no', 'like', '%' . $search . '%');
                        } elseif ($type === 'Property') {
                            $mq->where('name', 'like', '%' . $search . '%');
                        }
                    })
                    ->orWhereHas('tenant', function ($tq) use ($search) {
                        $tq->where('ic_number', 'like', '%' . $search . '%')
                            ->orWhereHas('user', function ($uq) use ($search) {
                                $uq->where('name', 'like', '%' . $search . '%')
                                    ->orWhere('email', 'like', '%' . $search . '%');
                            });
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
            ->appends($request->query());

        $statusOptions = ['New', 'Renew', 'Check out', 'End Agreement'];

        return view('adminSide.leases.index', compact('leases', 'statusOptions'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        
        $authFilter = function ($query) use ($user) {
            if ($user->role === 'ownerAdmin' || $user->role === 'agentAdmin') {
                // 直接匹配 created_by 字段
                $query->where('created_by', $user->id);
            }
        };

        $properties = Property::with(['owner.user'])
            ->where('status', 'Vacant')
            ->when($user->role !== 'admin', $authFilter)
            ->get();

        $units = Unit::with(['owner.user'])
        ->where('status', 'Vacant')
        ->when($user->role !== 'admin', function ($q) use ($authFilter) {
            $q->whereHas('owner', $authFilter);
        })
        ->get();

        $rooms = Room::with(['unit.owner.user'])
        ->where('status', 'Vacant')
        ->when($user->role !== 'admin', function ($q) use ($authFilter) {
            $q->whereHas('unit.property.owner', $authFilter);
        })
        ->get();

        $tenants = Tenants::with('user')
        ->when($user->role !== 'admin', function ($query) use ($user) {
            return $query->where('created_by', $user->id);
        })
        ->get();

        $status = $request->query('status');

        $leases = Lease::with(['tenant.user', 'leasable'])
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
                $query->whereHas('tenant', function($q) use ($user) {
                    $q->where('created_by', $user->id);
                });
            })
            ->get();

        $templates = Agreements::withTrashed() // 关键：包含已删除的数据
            ->where('type', 'rental_lease')
            ->where('status', 'active')

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
            'statuses',
            'selectedRoom', 
            'selectedTenant',
            'templates'
        ));
    }

    public function store(Request $request)
    {
        // 1. 验证数据 (修复了逻辑冲突，确保选 room 时不要求 property_id)
        $validated = $request->validate([
            'status' => 'required|string|in:New,Renew,Check Out,End Agreement',
            'lease_id' => 'required_unless:status,New|nullable|exists:leases,id',
            
            // 关键修复：去掉 required_if:status,New，改用更精准的依赖关系
            'lease_selection' => 'required_if:status,New|nullable|in:property,unit,room',
            //'property_id' => 'required_if:lease_selection,property|nullable',
            //'unit_id'     => 'required_if:lease_selection,unit|nullable',
            //'room_id'     => 'required_if:lease_selection,room|nullable',

            // 别忘了验证租客 ID
            'tenant_id'   => 'required_if:status,New|nullable|exists:tenants,id',

            'start_date' => 'required_if:status,New,Renew|nullable|date',
            'end_date'   => 'required_if:status,New,Renew|nullable|date|after:start_date',
            'checked_out_at' => 'required_if:status,Check Out|nullable|date',
            'agreement_ended_at' => 'required_if:status,End Agreement|nullable|date',
            'rent_price' => 'required_if:status,New,Renew|nullable|numeric|min:0',
            'term_type'  => 'required_if:status,New,Renew|nullable|string',
            'security_deposit'  => 'nullable|numeric|min:0',
            'utilities_deposit' => 'nullable|numeric|min:0',
            'agreement_id' => 'required',
        ]);

        // 2. 预提取旧租约 (仅 Renew/Check Out/End)
        $oldLease = null;
        if ($validated['status'] !== 'New') {
            $oldLease = Lease::findOrFail($validated['lease_id']);
        }

        if ($validated['status'] === 'Renew' && $oldLease) {
            $request->validate([
                // 这里的 start_date 必须在旧租约的 end_date 之后（或者当天，取决于你的业务）
                // 如果要求必须是之后的一天，用 after
                // 如果允许衔接当天，用 after_or_equal
                'start_date' => [
                    'required',
                    'date',
                    'after_or_equal:' . $oldLease->end_date->format('Y-m-d'),
                ],
            ], [
                'start_date.after_or_equal' => 'Your start date must be after or equal (' . $oldLease->end_date->format('d/m/Y') . ')',
            ]);
        }

        // 3. 确定物理属性 (房产信息和租客)
        if ($validated['status'] === 'New') {
            // 使用 request() 配合 ?? null，可以防止 "Undefined array key" 报错
            $leasableId = match ($validated['lease_selection']) {
                'property' => $request->property_id ?? null,
                'unit'     => $request->unit_id ?? null,
                'room'     => $request->room_id ?? null,
            };

            // 严谨起见，检查一下是否真的拿到了 ID
            if (!$leasableId) {
                return back()->withErrors(['lease_selection' => 'Please select a valid property/unit/room.'])->withInput();
            }

            $leasableType = match ($validated['lease_selection']) {
                'property' => Property::class,
                'unit'     => Unit::class,
                'room'     => Room::class,
            };
            
            $tenantId = $validated['tenant_id']; 
        } else {
            // Renew/Check Out 模式：直接从旧记录继承
            $leasableId   = $oldLease->leasable_id;
            $leasableType = $oldLease->leasable_type;
            $tenantId     = $oldLease->tenant_id;
        }



        // 4. 金额计算 (转为 Cents)
        $sDep = $validated['security_deposit'] ?? 0;
        $uDep = $validated['utilities_deposit'] ?? 0;

        $depositMode = 'none';
        if ($sDep > 0 && $uDep > 0) $depositMode = 'both';
        elseif ($sDep > 0) $depositMode = 'security_only';
        elseif ($uDep > 0) $depositMode = 'utilities_only';

        // 5. 执行数据库事务
        DB::transaction(function () use ($validated, $oldLease, $leasableType, $leasableId, $tenantId, $sDep, $uDep, $depositMode) {
            
            if ($oldLease) {
                $oldLease->update(['is_current' => false]);
            }

            // 更新物理状态
            $targetRoomStatus = match ($validated['status']) {
                'Check Out'     => 'Cleaning',
                'End Agreement' => 'Vacant',
                default         => 'Occupied', 
            };
            $leasableType::where('id', $leasableId)->update(['status' => $targetRoomStatus]);

            // 创建新记录
            Lease::create([
                'parent_lease_id'   => $oldLease?->id,
                'agreement_id'      => $validated['agreement_id'],
                'is_current'        => true, 
                'leasable_type'     => $leasableType,
                'leasable_id'       => $leasableId,
                'tenant_id'         => $tenantId,
                'start_date'        => in_array($validated['status'], ['New', 'Renew']) 
                                       ? $validated['start_date'] 
                                       : $oldLease?->start_date,
                'end_date'          => in_array($validated['status'], ['New', 'Renew']) 
                                        ? $validated['end_date'] 
                                        : $oldLease?->end_date,
                'checked_out_at'    => $validated['status'] === 'Check Out' ? $validated['checked_out_at'] : null,
                'agreement_ended_at'=> $validated['status'] === 'End Agreement' ? $validated['agreement_ended_at'] : null,
                'term_type'         => $validated['term_type'] ?? $oldLease?->term_type,
                // 注意：rent_price 如果是 New/Renew 应该用输入的，如果是 CheckOut 建议保留旧的
                'rent_price'        => ($validated['status'] === 'New' || $validated['status'] === 'Renew') 
                                ? ($validated['rent_price'] ?? 0) 
                                : ($oldLease?->rent_price ?? 0),
                'deposit_mode'      => $depositMode,
                'security_deposit'  => $sDep,
                'utilities_deposit' => $uDep,
                'status'            => $validated['status'],
            ]);
        });

        return redirect()->route('admin.leases.index')->with('success', 'Operation completed successfully.');
    }

    /*public function store(Request $request)
    {
        $statusInput = $request->input('status');
        $isFinal = in_array($statusInput, ['Check out', 'End'], true);
        $utilitiesRule = $isFinal ? ['nullable', 'array'] : ['required', 'array'];
        $utilityFieldRule = $isFinal ? ['nullable', 'numeric', 'min:0'] : ['required', 'numeric', 'min:0'];

        $dateRule = $isFinal ? ['nullable', 'date'] : ['required', 'date', 'after_or_equal:today'];
        $endDateRule = $isFinal ? ['nullable', 'date'] : ['required', 'date', 'after_or_equal:start_date'];
        $moneyRule = $isFinal ? ['nullable', 'numeric', 'min:0'] : ['required', 'numeric', 'min:0'];

        $payload = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'tenant_id' => ['required', 'exists:tenants,id'],
            'start_date' => $dateRule,
            'end_date' => $endDateRule,
            'monthly_rent' => $moneyRule,
            'security_deposit' => $moneyRule,
            'utilities_deposit' => $moneyRule,
            'status' => ['nullable', Rule::in(['New', 'Renew', 'Check out', 'End'])],
            'renew' => ['nullable', 'boolean'],
            'utilities' => $utilitiesRule,
            'utilities.water.prev' => $utilityFieldRule,
            'utilities.water.curr' => $utilityFieldRule,
            'utilities.electric.prev' => $utilityFieldRule,
            'utilities.electric.curr' => $utilityFieldRule,
        ]);

        $roomId = $payload['room_id'];
        $tenantId = $payload['tenant_id'];

        $hasActive = Lease::where('room_id', $roomId)
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'End')
            ->exists();

        $forceRenew = (bool) ($payload['renew'] ?? false);
        $latestStatus = Lease::where('room_id', $roomId)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->value('status');
        if ($hasActive && !$forceRenew) {
            return back()
                ->withErrors(['tenant_id' => 'This tenant already has an active lease for this room.'])
                ->withInput();
        }

        $hasPrevious = Lease::where('room_id', $roomId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($forceRenew) {
            $status = $payload['status'] ?? 'Renew';
            if ($status === 'New') {
                return back()
                    ->withErrors(['status' => 'Status cannot be New for renew.'])
                    ->withInput();
            }
            if (strtolower((string) $latestStatus) === 'check out' && $status !== 'End') {
                return back()
                    ->withErrors(['status' => 'Status must be End after checkout.'])
                    ->withInput();
            }
        } else {
            $status = $hasPrevious ? 'Renew' : 'New';
        }

        if (in_array($status, ['Check out', 'End'], true)) {
            $today = now()->toDateString();
            $payload['start_date'] = $today;
            $payload['end_date'] = $today;
            $payload['monthly_rent'] = 0;
            $payload['security_deposit'] = 0;
            $payload['utilities_deposit'] = 0;
            $payload['utilities'] = [
                'water' => ['prev' => 0, 'curr' => 0],
                'electric' => ['prev' => 0, 'curr' => 0],
            ];
        }

        $waterPrev = $this->toCents($payload['utilities']['water']['prev']);
        $waterCurr = $this->toCents($payload['utilities']['water']['curr']);
        $electricPrev = $this->toCents($payload['utilities']['electric']['prev']);
        $electricCurr = $this->toCents($payload['utilities']['electric']['curr']);

        if ($waterCurr < $waterPrev) {
            return back()
                ->withErrors(['utilities.water.curr' => 'Water current amount must be greater than or equal to previous.'])
                ->withInput();
        }

        if ($electricCurr < $electricPrev) {
            return back()
                ->withErrors(['utilities.electric.curr' => 'Electric current amount must be greater than or equal to previous.'])
                ->withInput();
        }

        $tenantId = $payload['tenant_id'];

        DB::transaction(function () use ($payload, $status, $waterPrev, $waterCurr, $electricPrev, $electricCurr, $tenantId) {
            $lease = Lease::create([
                'room_id' => $payload['room_id'],
                'tenant_id' => $payload['tenant_id'],
                'start_date' => $payload['start_date'],
                'end_date' => $payload['end_date'],
                'monthly_rent' => $this->toCents($payload['monthly_rent']),
                'security_deposit' => $this->toCents($payload['security_deposit']),
                'utilities_depost' => $this->toCents($payload['utilities_deposit']),
                'status' => $status,
            ]);

            Utility::create([
                'lease_id' => $lease->id,
                'type' => 'water',
                'prev_reading' => $waterPrev,
                'curr_reading' => $waterCurr,
                'amount' => $waterCurr - $waterPrev,
            ]);

            Utility::create([
                'lease_id' => $lease->id,
                'type' => 'electric',
                'prev_reading' => $electricPrev,
                'curr_reading' => $electricCurr,
                'amount' => $electricCurr - $electricPrev,
            ]);

            // --- 3. 生成初始支付账单 (Payments) ---
    
            // 准备通用的账单数据
            $basePaymentData = [
                'id' => (string) Str::ulid(), // 既然你模型用了 HasUlids，最好手动生成或确保模型处理了
                'tenant_id' => $tenantId,
                'lease_id' => $lease->id,
                'period' => $payload['start_date'],
                'status' => 'unpaid',
                'amount_paid' => 0,
            ];

            // A. 房租账单 (Rent)
            if ($payload['monthly_rent'] > 0) {
                Payment::create(array_merge($basePaymentData, [
                    'payment_type' => 'rent',
                    'amount_due' => $this->toCents($payload['monthly_rent']),
                    'invoice_no' => PaymentsController::generateSequenceInvoiceNo('RENT'), 
                ]));
            }

            // B. 抵押金账单 (Security Deposit)
            if ($payload['security_deposit'] > 0) {
                Payment::create(array_merge($basePaymentData, [
                    'payment_type' => 'deposit_security',
                    'amount_due' => $this->toCents($payload['security_deposit']),
                    'invoice_no' => PaymentsController::generateSequenceInvoiceNo('SD'), 
                ]));
            }

            // C. 水电押金账单 (Utilities Deposit)
            if ($payload['utilities_deposit'] > 0) {
                Payment::create(array_merge($basePaymentData, [
                    'payment_type' => 'deposit_utilities',
                    'amount_due' => $this->toCents($payload['utilities_deposit']),
                    'invoice_no' => PaymentsController::generateSequenceInvoiceNo('UD'), 
                ]));
            }

            // D. 水费 (如有金额)
            $waterAmount = $waterCurr - $waterPrev;
            if ($waterAmount > 0) {
                Payment::create(array_merge($basePaymentData, [
                    'payment_type' => 'utility_water',
                    'amount_due' => $waterAmount,
                    'invoice_no' => PaymentsController::generateSequenceInvoiceNo('WAT'), 
                ]));
            }

            // E. 电费 (如有金额)
            $electricAmount = $electricCurr - $electricPrev;
            if ($electricAmount > 0) {
                Payment::create(array_merge($basePaymentData, [
                    'payment_type' => 'utility_electric',
                    'amount_due' => $electricAmount,
                    'invoice_no' => PaymentsController::generateSequenceInvoiceNo('ELE'), 
                ]));
            }
        });

        return redirect()
            ->route('admin.leases.index')
            ->with('success', 'Lease created successfully.');
    }*/

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
                ->paginate(5, ['*'], 'rent_page');

            $otherPayments = $targetLease->payments()
                ->where('payment_type', '!=', 'rent')
                ->where('status', '!=', 'void')
                ->latest()
                ->paginate(5, ['*'], 'other_page');

            // 返回专门的局部视图
            return view('adminSide.tenants.payments.partial_overview', [
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
                    Room::class => ['owner.user', 'assets'],
                    Unit::class => ['owner.user'],
                    Property::class => ['owner.user'],
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
            ->paginate(5, ['*'], 'rent_page');

        // 2. 如果你还想显示其他类型的费用（比如押金、水电费）
        $otherPayments = $lease->payments()
            ->where('payment_type', '!=', 'rent')
            ->where('status', '!=', 'void')
            ->latest()
            ->paginate(5, ['*'], 'other_page');

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

    // LeaseController.php

    public function getDetailsJson(Lease $lease)
    {
        // 获取历史记录（为了保持逻辑一致，虽然详情页可能只用 $lease）
        // 这里建议只返回详情部分的 HTML
        return view('admin.leases.partials.details-content', [
            'lease' => $lease
        ])->render();
    }

    public function uploadStamping(Request $request, Lease $lease)
    {
        $request->validate([
            'stamping_reference_no' => 'required|string|max:100',
            'stamping_cert' => 'required|mimes:pdf|max:2048',
        ]);

        if ($request->hasFile('stamping_cert')) {
            // --- 优化点：删除旧文件 ---
            if ($lease->stamping_cert_path && Storage::disk('local')->exists($lease->stamping_cert_path)) {
                Storage::disk('local')->delete($lease->stamping_cert_path);
            }

            $path = $request->file('stamping_cert')->store('stamping_certs', 'local');

            $lease->update([
                'stamping_status' => true,
                'stamping_cert_path' => $path,
                'stamping_reference_no' => $request->stamping_reference_no,
                'stamped_at' => now(),  
            ]);
            $lease->save(['validate' => false]);
        }

        return back()->with('success', 'Stamping uploaded successfully!');
    }

    public function viewCert(Lease $lease)
    {
        // 1. 确保数据库有路径记录
        if (empty($lease->stamping_cert_path)) {
            abort(404, 'No certificate path record.');
        }

        // 2. 获取文件的物理路径 (注意检查你的 store() 是存放在哪个目录)
        // 如果你 store 时没指定 disk，默认在 storage/app/
        $path = storage_path('app/private/' . $lease->stamping_cert_path);

        if (!file_exists($path)) {
            abort(404, 'File not found on server.');
        }

        // 3. 读取文件并转为 Base64
        $fileContent = file_get_contents($path);
        $base64 = base64_encode($fileContent);
        $pdfData = 'data:application/pdf;base64,' . $base64;

        // 4. 返回视图，并传入 pdfData 和 lease 对象
        return view('adminSide.leases.view-cert', compact('pdfData', 'lease'));
    }

    public function getPaymentsTableOnly(Lease $lease) {
        // 重新获取这个 Lease 的支付记录
        $payments = $lease->payments()->latest()->get(); 
        
        // 渲染你那个不可改动的 table 文件
        return view('your.table.path', [
            'payments' => $payments,
            'emptyMessage' => 'No records for this lease.'
        ])->render(); // 使用 render() 转成字符串返回给前端
    }

    public function refreshPaymentsTable(Lease $lease)
    {
        // 1. 获取该 Lease 下的所有支付记录，并按类型分类
        // 这里的逻辑最好和你原本 show 方法里的逻辑保持一致
        $allPayments = $lease->payments()
            ->where('status', '!=', 'void')
            ->latest()->get();

        $rentPayments = $allPayments->filter(fn($p) => in_array($p->payment_type, ['rent']));
        $otherPayments = $allPayments->filter(fn($p) => !in_array($p->payment_type, ['rent']));

        // 2. 渲染 Table 模板为 HTML 字符串
        $rentHtml = view('adminSide.tenants.payments.paymentTable', [
            'payments' => $rentPayments,
            'emptyMessage' => 'No outstanding rent found.'
        ])->render();

        $otherHtml = view('adminSide.tenants.payments.paymentTable', [
            'payments' => $otherPayments,
            'emptyMessage' => 'No miscellaneous records found.'
        ])->render();

        // 3. 以 JSON 格式返回给前端
        return response()->json([
            'rentHtml' => $rentHtml,
            'otherHtml' => $otherHtml
        ]);
    }
}
