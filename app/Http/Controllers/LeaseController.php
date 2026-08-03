<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Room;
use App\Models\Unit;
use App\Models\Property;
use App\Models\Tenants;
use App\Models\User;
use App\Models\FeeType;
use App\Models\Owners;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\RoleBasedDataTrait;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Services\FileService;
use App\Services\InvoiceService;
use App\Services\LeaseService;
use App\Http\Requests\Lease\{StoreLeaseRequest};
use App\FeeTypeCategory;

class LeaseController extends Controller
{
    use RoleBasedDataTrait;
    protected InvoiceService $invoiceService;
    protected LeaseService $leaseService;

    public function __construct(InvoiceService $invoiceService, LeaseService $leaseService)
    {
        $this->invoiceService = $invoiceService;
        $this->leaseService = $leaseService;
    }
    public function index(Request $request)
    {
        $userId = Auth::id();
        $search = $request->input('search');
        $status = $request->input('status');

        // 1. 使用 leasable 预加载多态关联
        // 同时保留 tenant 关联
        $query = Lease::with([
            'leasable', // 这会自动加载 Room, Unit 或 
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

        // 3. 搜索逻辑更新 (保持你的原有优秀逻辑)
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

        // 4. 完美对齐前端表头的 Sort 逻辑
        $sortParam = $request->query('sort');
        $field = Str::beforeLast($sortParam, '_');
        $direction = Str::afterLast($sortParam, '_');

        if ($field === 't' && in_array($direction, ['asc', 'desc'])) {
            // 通过 Join 排序租客名称
            $query->join('tenants', 'leases.tenant_id', '=', 'tenants.id')
                  ->join('users as tu', 'tenants.user_id', '=', 'tu.id')
                  ->orderBy('tu.name', $direction)
                  ->select('leases.*'); // 防止 ID 冲突
        } else {
            // 直属字段的排序白名单
            $sortMapping = [
                'd'  => 'start_date',
                'r'  => 'rent_price',
                'de' => 'security_deposit',
                's'  => 'status',
            ];

            if (array_key_exists($field, $sortMapping) && in_array($direction, ['asc', 'desc'])) {
                $query->orderBy($sortMapping[$field], $direction);
            } else {
                $query->orderBy('leases.created_at', 'desc');
            }
        }

        // 获取数据
        $leases = $query->where('is_current', true)
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
        /** @var User $user */
        $user = Auth::user();

        $properties = $this->getAuthorizedProperties()
            ->where('status', 'Vacant')
            ->get();

        $units = $this->getAuthorizedUnits()
            ->where('status', 'Vacant')
            ->get();

        $rooms = $this->getAuthorizedRooms()
            ->where('status', 'Vacant')
            ->get();

        $tenants = $this->applyOwnershipFilter(
            Tenants::query(),
            $user
        )->get();

        /*
        |--------------------------------------------------------------------------
        | Fee Types
        |--------------------------------------------------------------------------
        */

        $feeTypesQuery = FeeType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                // System fee types are visible to everyone
                $query->where('is_system', true);
                // User-owned fee types
                if ($user->role === 'ownerAdmin') {
                    $query->orWhere('user_id', $user->id);
                } elseif ($user->role === 'agentAdmin') {
                    $managedOwnerIds = Owners::where('agent_id', $user->id)
                        ->select('user_id');

                    $query->orWhere('user_id', $user->id)
                        ->orWhereIn('user_id', $managedOwnerIds);
                }
            });

        $feeTypes = $feeTypesQuery
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $rentFeeTypes = $feeTypes->where('category', FeeTypeCategory::RENT->value)->values();
        $depositFeeTypes = $feeTypes->where('category', FeeTypeCategory::DEPOSIT->value)->values();
        $managementFeeTypes = $feeTypes->where('category', FeeTypeCategory::MANAGEMENT->value)->values();

        /*
        |--------------------------------------------------------------------------
        | Existing Leases
        |--------------------------------------------------------------------------
        */

        $status = $request->query('status');

        $leases = Lease::with([
            'tenant.user',

            'leasable' => function ($morphTo) {
                $morphTo->morphWith([
                    Room::class => ['unit.owner'],
                    Unit::class => ['owner'],
                    Property::class => ['owner'],
                ]);
            }
        ])
        ->where('is_current', true)
        ->when(
            $status === 'End Agreement',
            fn ($q) => $q->where('status', 'Check Out'),
            fn ($q) => $q->whereIn('status', ['New', 'Renew'])
        )
        ->when(
            $user->role !== 'admin',
            fn ($query) =>
                $this->applyLeaseOwnershipFilter($query, $user)
        )
        ->get();

        /*
        |--------------------------------------------------------------------------
        | Lease Preview Data
        |--------------------------------------------------------------------------
        */

        $leasePreviewData = $leases->map(function ($lease) {
            $leasable = $this->getLeasableWithOwner($lease);

            $cumulativeSecurity = 0;
            $cumulativeUtilities = 0;

            $current = $lease;

            while ($current) {
                $cumulativeSecurity += $current->security_deposit ?? 0;
                $cumulativeUtilities += $current->utilities_deposit ?? 0;

                if ($current->parent_lease_id) {
                    $current = Lease::find(
                        $current->parent_lease_id
                    );
                } else {
                    $current = null;
                }
            }

            return array_merge(
                $lease->toArray(),
                [
                    'leasable_name' => $this->getLeasableName($leasable),
                    'leasable_address' =>
                        $this->getLeasableAddress($leasable),
                    'owner_data' =>
                        $this->getOwnerData($leasable),
                    'cumulative_security' =>
                        $cumulativeSecurity,
                    'cumulative_utilities' =>
                        $cumulativeUtilities,
                ]
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Agreement Templates
        |--------------------------------------------------------------------------
        */

        $templates = $this->applyOwnershipFilter(
            DocumentTemplate::query()
                ->where('category', 'agreement')
                ->where('status', 'active'),
            $user,
            'user_id'
        )->get();

        /*
        |--------------------------------------------------------------------------
        | Initial Variables
        |--------------------------------------------------------------------------
        */

        $selectedRoom = null;
        $selectedTenant = null;
        $statuses = [
            'New',
            'Renew',
            'Check Out',
            'End Agreement',
        ];

        return view(
            'adminSide.leases.create',
            compact(
                'properties',
                'units',
                'rooms',
                'tenants',
                'leases',
                'leasePreviewData',
                'templates',
                'rentFeeTypes',
                'depositFeeTypes',
                'managementFeeTypes',
                'statuses',
                'selectedRoom',
                'selectedTenant'
            )
        );
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

    public function store(StoreLeaseRequest $request, LeaseService $leaseService) {
        Gate::authorize('owner-admin');

        $leaseService->process(
            Auth::user(),
            $request->validated()
        );

        return redirect()->route('admin.leases.index')->with('success', 'Lease processed successfully.');
    }

    public function show(Request $request, Lease $lease)
    {
        // 如果是 AJAX 请求 (点击 Progression 切换)
        if ($request->ajax()) {
            $targetId = $request->get('lease_id');
            // 查找特定的 Lease，确保它属于当前链条（安全起见）
            $targetLease = Lease::findOrFail($targetId);

            $rentInvoices = $lease->invoices()
                ->where('type', 'rent')
                ->where('status', '!=', 'void')
                ->orderByDesc('period')
                ->paginate(5, ['*'], 'rent_page')
                ->onEachSide(1);

            $otherInvoices = $lease->invoices()
                ->where('type', '!=', 'rent')
                ->where('status', '!=', 'void')
                ->latest()
                ->paginate(5, ['*'], 'other_page')
                ->onEachSide(1);

            // 返回专门的局部视图
            return  view('adminSide.tenants.payments.partial_overview', [
                'lease' => $targetLease,
                'rentPayments' => $rentInvoices,
                'otherPayments' => $otherInvoices
            ])->render();
        }

        // 加载当前租约需要的关联
        $lease->load([
            'tenant.user',
            'utilities',
            'documentTemplate',
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

        $rentInvoices = $lease->invoices()
            ->where('type', 'rent')
            ->where('status', '!=', 'void')
            ->orderByDesc('period')
            ->paginate(5, ['*'], 'rent_page')
            ->onEachSide(1);

        $otherInvoices = $lease->invoices()
            ->where('type', '!=', 'rent')
            ->where('status', '!=', 'void')
            ->latest()
            ->paginate(5, ['*'], 'other_page')
            ->onEachSide(1);

        // 将结果按时间正序排列（从最老的到最新的）
        $leaseHistory = $leaseHistory->reverse();

        $historyJson = $leaseHistory->keyBy('id')->map(function ($item) {
            return [
                'status' => $item->status,
                'checked_out_at' => $item->checked_out_at ? $item->checked_out_at_formatted : null,
                'agreement_ended_at' => $item->agreement_ended_at ? $item->agreement_ended_at_formatted : null,
                'start_date' => $item->start_date_formatted ?? null,
                'end_date' => $item->end_date_formatted ?? null,
                'term_type' => strtoupper($item->term_type) ?? 'N/A',
                'rent_price' => number_format($item->rent_price, 2),
                'deposit_mode' => strtoupper($item->deposit_mode) ?? 'SECURITY',
                'security_deposit' => $item->security_deposit > 0
                    ? number_format($item->security_deposit, 2)
                    : null,
                'utilities_deposit' => $item->utilities_deposit > 0
                    ? number_format($item->utilities_deposit, 2)
                    : null,
                'edit_url' => route('admin.leases.edit', $item->id),
                'stamping_status' => (bool) $item->stamping_status,
                'stamping_cert_path' => $item->stamping_cert_path,
                'stamping_reference_no' => $item->stamping_reference_no,
                'stamped_at' => $item->stamped_at ? $item->stamped_at_formatted : null,
                'can_stamp' => in_array($item->status, ['New', 'Renew']),
                'upload_url' => route('admin.leases.upload-stamping', $item->id),
                'view_url' => route('admin.leases.cert-file', $item->id),
                'agreement' => [
                    'title' => $item->agreement?->title ?? 'Agreement',
                    'content' => $item->agreement?->content ?? '',
                ],
                'agreement_id' => $item->agreement_id,
                'tenant_name' => $item->tenant?->user?->name ?? 'N/A',
                'tenant_ic' => $item->tenant?->ic_number ?? 'N/A',

                'owner_name' => ($item->leasable instanceof Room)
                    ? ($item->leasable->unit?->owner?->name ?? 'N/A')
                    : ($item->leasable->owner?->name ?? 'N/A'),

                'owner_ic' => ($item->leasable instanceof Room)
                    ? ($item->leasable->unit?->owner?->owner?->ic_number ?? 'N/A')
                    : ($item->leasable->owner?->owner?->ic_number ?? 'N/A'),

                'property_address' => $item->leasable?->full_address ?? 'N/A',
                'property_type' => strtolower(class_basename($item->leasable_type)),
                'property_name' => $item->leasableName ?? 'N/A',
                'rent_mode' => strtoupper($item->term_type ?? 'N/A'),
                'check_out_date' => $item->checked_out_at?->format('d/m/Y') ?? 'N/A',
                'end_agreement_date' => $item->agreement_ended_at?->format('d/m/Y') ?? 'N/A',

                // This now works because we're inside the controller
                'can_generate' => $this->invoiceService->nextBillingPeriod($item) !== null,
            ];
        });

        return view('adminSide.leases.show', compact('lease', 'leaseHistory', 'rentInvoices', 'otherInvoices', 'historyJson'));
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

    public function uploadStamping(Request $request, Lease $lease, FileService $fileService)
    {
        $validated = $request->validate([
            'stamping_reference_no' => 'required|string|max:100',
            'stamping_cert' => 'required|mimes:pdf|max:2048',
        ]);

        try {
            if ($lease->stamping_cert_path) {
                $fileService->delete($lease->stamping_cert_path);
            }

            $userId = Auth::id(); 
            
            $path = $fileService->upload(
                $request->file('stamping_cert'), 
                $userId, 
                'lease_stamping'
            );

            $lease->update([
                'stamping_status' => true,
                'stamping_cert_path' => $path,
                'stamping_reference_no' => $validated['stamping_reference_no'],
                'stamped_at' => now(),
            ]);

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

        return view('adminSide.leases.view-cert', compact('lease'));
    }

    public function showCertFile(Lease $lease, FileService $fileService)
    {
        return $fileService->getStreamResponse($lease->stamping_cert_path);
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
