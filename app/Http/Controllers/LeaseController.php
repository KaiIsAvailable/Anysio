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
        $userId = get_effective_user();
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
            $actualUserId = Auth::id(); // The currently logged-in user (could be staff)
            $effectiveUser = get_effective_user();
            $effectiveUserId = $effectiveUser?->id;

            $query->where(function ($q) use ($effectiveUserId, $actualUserId) {
                $q->whereHasMorph('leasable', [Room::class, Unit::class, Property::class], function ($mq, $type) use ($effectiveUserId, $actualUserId) {
                    if ($type === Room::class) {
                        $mq->whereHas('unit.owner', function ($oq) use ($effectiveUserId, $actualUserId) {
                            $oq->where(function ($q) use ($effectiveUserId, $actualUserId) {
                                $q->where('created_by', $effectiveUserId)
                                    ->orWhere('created_by', $actualUserId)
                                    ->orWhere('owner_id', $effectiveUserId);
                            });
                        });
                    } else {
                        $mq->whereHas('owner', function ($oq) use ($effectiveUserId, $actualUserId) {
                            $oq->where(function ($q) use ($effectiveUserId, $actualUserId) {
                                $q->where('created_by', $effectiveUserId)
                                    ->orWhere('created_by', $actualUserId)
                                    ->orWhere('owner_id', $effectiveUserId);
                            });
                        });
                    }
                })
                ->orWhereHas('tenant', function ($tq) use ($effectiveUserId, $actualUserId) {
                    $tq->where('created_by', $effectiveUserId)
                    ->orWhere('created_by', $actualUserId);
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
        $user = get_effective_user();

        // ✅ 強制加上 ->select('properties.*')，防止 owner_id 遺失！
        $properties = $this->getAuthorizedProperties()
            ->select('properties.*')
            ->with(['owner.owner'])
            ->where('status', 'Vacant')
            ->get();

        $units = $this->getAuthorizedUnits()
            ->select('units.*') 
            ->with(['property', 'owner.owner'])
            ->where('status', 'Vacant')
            ->get()
            ->each(function ($unit) {
                $propertyName = $unit->property?->name ?? 'N/A';
                $unit->display_label = "{$unit->unit_no} ({$propertyName})";
            });

        $rooms = $this->getAuthorizedRooms()
            ->select('rooms.*') // ✅ 強制撈出 room 的所有欄位
            ->with(['unit.owner.owner', 'owner.owner'])
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
        $serviceFeeTypes = $feeTypes->where('category', FeeTypeCategory::SERVICE->value)->values();
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
                fn($q) => $q->where('status', 'Check Out'),
                fn($q) => $q->whereIn('status', ['New', 'Renew'])
            )
            ->when(
                $user->role !== 'admin',
                fn($query) =>
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
                'serviceFeeTypes',
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

    public function store(StoreLeaseRequest $request, LeaseService $leaseService)
    {
        Gate::authorize('owner-admin');

        $leaseService->process(
            get_effective_user(),
            $request->validated()
        );

        return redirect()->route('admin.leases.index')->with('success', 'Lease processed successfully.');
    }

    public function show(Request $request, Lease $lease)
    {
        // If it's an AJAX request, we want to support fetching invoices 
        // for ANY lease in the history chain (passed via query parameter, fallback to current route lease)
        if ($request->ajax()) {
            $targetLeaseId = $request->get('lease_id', $lease->id);
            $targetLease = Lease::findOrFail($targetLeaseId);

            $invoices = $targetLease->invoices()
                ->with(['documentTemplate', 'items.feeType', 'transactions.documentTemplate'])
                ->latest()
                ->get(); // Use get() instead of paginate() so Alpine can handle the 5-per-page slicing uniformly

            $formattedInvoices = $invoices->map(function ($invoice) {
                $variables = $this->invoiceService->getInvoiceVariables($invoice);
                $rawPeriod = $invoice->period_display ?? $invoice->period;

                $formattedPeriod = '—';
                if ($rawPeriod) {
                    try {
                        $formattedPeriod = \Carbon\Carbon::parse($rawPeriod)->format('m/Y');
                    } catch (\Exception $e) {
                        $formattedPeriod = $rawPeriod;
                    }
                }

                $invoiceItems = $invoice->items->map(function ($subItem) {
                    return [
                        'id' => $subItem->id,
                        // Strictly use feeType name, with a fallback if feeType is missing
                        'description' => $subItem->feeType?->name ?? 'Fee Item',
                        'amount' => number_format($subItem->amount / 100 ?? 0, 2),
                    ];
                });

                // Fallback if the invoice has no relational invoice items at all
                if ($invoiceItems->isEmpty() && $invoice->total_amount) {
                    $invoiceItems->push([
                        'id' => $invoice->id,
                        'description' => 'Invoice Total',
                        'amount' => number_format($invoice->total_amount / 100 ?? 0, 2),
                    ]);
                }

                // 💡 注意這裡加上了 use ($variables)，讓 Receipt 可以繼承 Invoice 的變數
                $receipts = $invoice->transactions->map(function ($transaction) use ($variables) {
                    return [
                        'id' => $transaction->id,
                        'receipt_no' => $transaction->receipt_no ?? '—',
                        'document_template_id' => $transaction->document_template_id ?? '—',
                        'template_title' => $transaction->documentTemplate?->title,
                        'template_html' => $transaction->documentTemplate?->html_template,
                        'amount' => number_format($transaction->amount_paid / 100 ?? 0, 2),
                        'date' => $transaction->payment_date ? \Carbon\Carbon::parse($transaction->payment_date)->format('d M Y') : $transaction->created_at?->format('d M Y'),

                        // 🌟 關鍵修復：把變數交給 Receipt！
                        // 我們將 Invoice 的變數 (如租客姓名、物業地址等) 與 Receipt 的專屬變數合併
                        'variables' => array_merge($variables, [
                            'receipt_no'     => $transaction->receipt_no ?? '—',
                            'amount_paid'    => number_format($transaction->amount_paid / 100 ?? 0, 2),
                            'payment_date'   => $transaction->payment_date ? \Carbon\Carbon::parse($transaction->payment_date)->format('d/m/Y') : ($transaction->created_at ? $transaction->created_at->format('d/m/Y') : '—'),
                            'payment_method' => $transaction->payment_method ?? '—',
                            'reference_no'   => $transaction->reference_no ?? '—',
                        ]),
                    ];
                });

                return [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'document_template_id' => $invoice->document_template_id ?? '—',
                    'template_title' => $invoice->documentTemplate?->title,
                    'template_html'  => $invoice->documentTemplate?->html_template,
                    'variables'      => $variables,
                    'invoice_items' => $invoiceItems,
                    'receipts'      => $receipts,
                    'period' => $formattedPeriod,
                    'due_date' => $invoice->due_date?->format('d/m/Y') ?? '—',
                    'total_amount' => number_format($invoice->total_amount / 100 ?? 0, 2),
                    'amount_paid' => number_format($invoice->amount_paid / 100 ?? 0, 2),
                    'amount_balance' => number_format(($invoice->total_amount / 100 ?? 0) - ($invoice->amount_paid / 100 ?? 0), 2),
                    'status' => strtolower($invoice->status ?? 'unpaid'),
                    'remarks' => $invoice->remarks ?? '—',
                ];
            });

            return response()->json([
                'invoices' => $formattedInvoices,
            ]);
        }

        // --- Standard Page Load ---
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
        $leaseHistory = collect([$lease]);
        $current = $lease;

        while ($current->parent_lease_id) {
            $parent = Lease::with(['tenant.user', 'room'])->find($current->parent_lease_id);
            if ($parent) {
                $leaseHistory->push($parent);
                $current = $parent;
            } else {
                break;
            }
        }

        // Initial load for the main lease invoices on page render
        // 🌟 這裡加上 with(['documentTemplate']) 載入模板關聯
        $invoices = $lease->invoices()
            ->with(['documentTemplate', 'items.feeType'])
            ->latest()
            ->paginate(5, ['*'], 'other_page')
            ->onEachSide(1);

        $leaseHistory = $leaseHistory->reverse();

        $historyJson = $leaseHistory->keyBy('id')->map(function ($item) {
            $chargesSum = $item->charges->sum('amount');
            $totalRentPrice = $item->rent_price + $chargesSum;

            return [
                'status' => $item->status,
                'checked_out_at' => $item->checked_out_at ? $item->checked_out_at_formatted : null,
                'agreement_ended_at' => $item->agreement_ended_at ? $item->agreement_ended_at_formatted : null,
                'start_date' => $item->start_date_formatted ?? null,
                'end_date' => $item->end_date_formatted ?? null,
                'term_type' => strtoupper($item->term_type) ?? 'N/A',
                'total_rent_price' => number_format($totalRentPrice / 100, 2),
                'charges' => $item->charges->map(function ($charge) {
                    return [
                        'id' => $charge->id,
                        'description' => $charge->description,
                        'amount' => number_format($charge->amount / 100, 2),
                    ];
                }),
                'edit_url' => route('admin.leases.edit', $item->id),
                'stamping_status' => (bool) $item->stamping_status,
                'stamping_cert_path' => $item->stamping_cert_path,
                'stamping_reference_no' => $item->stamping_reference_no,
                'stamped_at' => $item->stamped_at ? $item->stamped_at_formatted : null,
                'can_stamp' => in_array($item->status, ['New', 'Renew']),
                'upload_url' => route('admin.leases.upload-stamping', $item->id),
                'view_url' => route('admin.leases.view-cert', $item->id),
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
                'wallet_balance' => $item->tenant?->user?->wallet?->formatted_balance ?? '0.00',

                // 🌟 這裡加上 with(['documentTemplate']) 載入模板關聯
                // 🌟 加入 transactions.documentTemplate 關聯
                'invoices' => $item->invoices()->with(['documentTemplate', 'items.feeType', 'transactions.documentTemplate'])->latest()->get()->map(function ($invoice) {

                    // 🌟 呼叫 Service 取得所有發票變數
                    $variables = $this->invoiceService->getInvoiceVariables($invoice);

                    $rawPeriod = $invoice->period_display ?? $invoice->period;

                    // Format to m/Y if it's a date/carbon instance or parseable string, otherwise keep raw or show '—'
                    $formattedPeriod = '—';
                    if ($rawPeriod) {
                        try {
                            $formattedPeriod = \Carbon\Carbon::parse($rawPeriod)->format('m/Y');
                        } catch (\Exception $e) {
                            $formattedPeriod = $rawPeriod; // Fallback to raw string if parsing fails
                        }
                    }

                    // Map each invoice item to include description and formatted price
                    $invoiceItems = $invoice->items->map(function ($subItem) {
                        return [
                            'description' => $subItem->description ?? 'Item',
                            'amount' => number_format($subItem->amount / 100 ?? 0, 2),
                        ];
                    });

                    // Fallback if no items relationship records exist, but main invoice has a description
                    if ($invoiceItems->isEmpty() && $invoice->description) {
                        $invoiceItems->push([
                            'description' => $invoice->description,
                            'amount' => number_format($invoice->total_amount / 100 ?? 0, 2),
                        ]);
                    }

                    // 💡 關鍵修復：在這裡也要把 Receipts 資料打包進去，和上方 AJAX 保持一致
                    $receipts = $invoice->transactions->map(function ($transaction) {
                        return [
                            'id' => $transaction->id,
                            'receipt_no' => $transaction->receipt_no ?? '—',
                            'document_template_id' => $transaction->document_template_id ?? '—',
                            'template_title' => $transaction->documentTemplate?->title,
                            'template_html' => $transaction->documentTemplate?->html_template,
                            'amount' => number_format($transaction->amount_paid / 100 ?? 0, 2),
                            'date' => $transaction->payment_date ? \Carbon\Carbon::parse($transaction->payment_date)->format('d M Y') : $transaction->created_at?->format('d M Y'),
                        ];
                    });

                    return [
                        'id' => $invoice->id,
                        'invoice_no' => $invoice->invoice_no,
                        'document_template_id' => $invoice->document_template_id ?? '—',

                        'template_title' => $invoice->documentTemplate?->title,
                        'template_html'  => $invoice->documentTemplate?->html_template,

                        // 🌟 把打包好的變數傳遞給 JSON
                        'variables'      => $variables,

                        'invoice_items' => $invoiceItems,
                        'receipts'      => $receipts, // 💡 新增這行，讓前端拿得到 receipt 陣列！
                        'period' => $formattedPeriod,
                        'due_date' => $invoice->due_date->format('d/m/Y') ?? '—',
                        'total_amount' => number_format($invoice->total_amount / 100 ?? 0, 2),
                        'amount_paid' => number_format($invoice->amount_paid / 100 ?? 0, 2),
                        'amount_balance' => number_format(($invoice->total_amount / 100 ?? 0) - ($invoice->amount_paid / 100 ?? 0), 2),
                        'status' => strtolower($invoice->status ?? 'unpaid'),
                        'remarks' => $invoice->remarks ?? '—',
                    ];
                }),
            ];
        });

        $userId = get_effective_user();
        $feeTypes = FeeType::where('user_id', $userId->id)
            ->where('category', 'invoice')
            ->where('is_active', true)
            ->where('is_system', false)
            ->get();

        return view('adminSide.leases.show', compact('lease', 'leaseHistory', 'invoices', 'historyJson', 'feeTypes'));
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

    // Returns the Blade view wrapper with the header and iframe
    public function viewCert(Lease $lease)
    {
        if (empty($lease->stamping_cert_path)) {
            abort(404, 'No certificate path record.');
        }

        // Pass the stream route URL to the blade so the iframe can load it
        $pdfData = route('admin.leases.cert-file', $lease->id);

        return view('adminSide.leases.view-cert', compact('lease', 'pdfData'));
    }

    public function showCertFile(Lease $lease, FileService $fileService)
    {
        return $fileService->getStreamResponse($lease->stamping_cert_path);
    }
}
