<?php

namespace App\Http\Controllers;

use App\Models\{Lease, Room, Unit, Property, Tenants, User, FeeType, Owners};
use App\Services\{DocumentTemplateService, FileService, InvoiceService, LeaseService, SettingService};
use App\Http\Requests\Lease\{StoreLeaseRequest};
use Illuminate\Support\Facades\{Auth, Gate, Log};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Traits\RoleBasedDataTrait;
use App\Models\DocumentTemplate;
use App\FeeTypeCategory;

class LeaseController extends Controller
{
    use RoleBasedDataTrait;
    protected InvoiceService $invoiceService;
    protected LeaseService $leaseService;
    protected SettingService $settingService;

    public function __construct(InvoiceService $invoiceService, LeaseService $leaseService, SettingService $settingService)
    {
        $this->invoiceService = $invoiceService;
        $this->leaseService = $leaseService;
        $this->settingService = $settingService;
    }

    public function index(Request $request, LeaseService $leaseService)
    {
        if (Gate::denies('owner-admin') && Gate::denies('leases.tab')) {
            return view('errors.403');
        }

        $userId = get_effective_user();
        $effectiveUserId = $userId->id;
        $status = $request->input('status');

        // 1. 预加载多态关联与租客关联
        $query = Lease::with([
            'leasable', 
            'tenant.user',
        ]);

        // 2. 权限过滤
        if (!Gate::allows('super-admin')) {
            $actualUserId = Auth::id(); 
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

        // 💡 Calculate total base active count BEFORE applying search, filters, or sorting
        $countQuery = clone $query;
        $currentActiveCount = $countQuery->where('is_current', true)
            ->whereIn('status', ['New', 'Renew', 'End'])
            ->count();

        // 3. 全局搜索逻辑
        $query->search($request->input('search'));

        // 4. 高级筛选代码
        $query->applyFilters($request);

        // 5. 排序逻辑
        $sortParam = $request->query('sort');
        $field = Str::beforeLast($sortParam, '_');
        $direction = Str::afterLast($sortParam, '_');

        if ($field === 't' && in_array($direction, ['asc', 'desc'])) {
            $query->leftJoin('tenants', 'leases.tenant_id', '=', 'tenants.id')
                ->leftJoin('users as tu', 'tenants.user_id', '=', 'tu.id')
                ->orderBy('tu.name', $direction)
                ->select('leases.*'); 
        } else {
            $sortMapping = [
                'd'  => 'leases.start_date',
                'r'  => 'leases.rent_price',
                'de' => 'leases.security_deposit',
                's'  => 'leases.status',
            ];

            if (array_key_exists($field, $sortMapping) && in_array($direction, ['asc', 'desc'])) {
                $query->orderBy($sortMapping[$field], $direction);
            } else {
                $query->orderBy('leases.created_at', 'desc');
            }
        }

        $user = Auth::user();

        // Always enforce is_current = true on the main table query
        $query->where('is_current', true);

        $leases = $query->orderByRaw("CASE WHEN leases.status IN ('Check Out', 'cancelled') THEN 1 ELSE 0 END ASC")
            ->orderBy('leases.created_at', 'desc')
            ->paginate(10)
            ->onEachSide(1)
            ->appends($request->query());

        $statusOptions = ['New', 'Renew', 'Check Out', 'End', 'cancelled'];

        if ($request->ajax()) {
            return view('adminSide.leases._table', compact('leases', 'statusOptions'));
        }

        $leaseModalData = $leaseService->getLeaseFormPayload($userId, $request);

        $packageLimitInfo = null;
        if (!in_array($user->role, ['admin']) && $userId) {
            $management = $userId->user_management;
            if ($management && $management->package) {
                $baseLimit = (int) $management->package->base_lease;
                $extraLimit = (int) $management->extra_lease;
                $totalLimit = $baseLimit + $extraLimit;

                $packageLimitInfo = [
                    'packageName' => $management->package->name,
                    'current' => $currentActiveCount, 
                    'limit' => $totalLimit,
                    'isReached' => $currentActiveCount >= $totalLimit,
                ];
            }
        }

        return view('adminSide.leases.index', array_merge(
            $leaseModalData,
            [
                'statusOptions' => $statusOptions,
                'packageLimitInfo' => $packageLimitInfo,
                'leases' => $leases,
            ]
        ));
    }

    public function create(Request $request, LeaseService $leaseService)
    {
        if (Gate::denies('owner-admin') && Gate::denies('leases.lease controller')) {
            return view('errors.403');
        }

        $user = get_effective_user();
        $payload = $leaseService->getLeaseFormPayload($user, $request);

        return view('adminSide.leases.create', array_merge($payload, [
            'selectedRoom' => null,
            'selectedTenant' => null,
        ]));
    }

    public function store(StoreLeaseRequest $request, LeaseService $leaseService)
    {
        if (Gate::denies('owner-admin') && Gate::denies('leases.lease controller')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        $leaseService->process(
            get_effective_user(),
            $request->validated()
        );

        if ($request->input('redirect_to') === 'dashboard') {
            return redirect()->route('admin.dashboard')->with('success', 'Lease created successfully.');
        }

        return redirect()->route('admin.leases.index')->with('success', 'Lease processed successfully.');
    }

    public function previewTemplate(
        Request $request,
        DocumentTemplateService $documentTemplateService,
        LeaseService $leaseService
    ) {
        if (Gate::denies('owner-admin') && Gate::denies('leases.lease controller')) {
            return response()->json(['message' => 'You have no permission.'], 403);
        }

        $category = $request->input('category', 'agreement');
        abort_unless(in_array($category, ['agreement', 'refund'], true), 422);

        $template = DocumentTemplate::query()
            ->whereKey($request->input('template_id'))
            ->where('category', $category)
            ->where('status', 'active')
            ->firstOrFail();
        $requiredVariables = $documentTemplateService->extractVariableNames($template->html_template);
        Log::channel('testing')->info('Lease preview request resolved:', [
            'request' => $request->except(['_token']),
            'category' => $category,
            'template_id' => $template->id,
            'template_title' => $template->title,
            'template_html' => $template->html_template,
            'required_variables' => $requiredVariables,
        ]);
        $needsUserVariables = collect($requiredVariables)
            ->contains(fn (string $variable): bool => str_starts_with($variable, 'user_'));
        $needsTenantVariables = collect($requiredVariables)
            ->contains(fn (string $variable): bool => str_starts_with($variable, 'tenant_'));
        $needsOwnerVariables = collect($requiredVariables)
            ->contains(fn (string $variable): bool => str_starts_with($variable, 'owner_'));

        $effectiveUser = get_effective_user();
        $leaseId = trim((string) $request->input('lease_id', ''));
        $lease = $leaseId !== ''
            ? Lease::with(['tenant.user', 'leasable', 'charges.feeType'])->find($leaseId)
            : null;
        if ($leaseId !== '' && !$lease) {
            return response()->json([
                'message' => 'The selected lease could not be found.',
            ], 422);
        }
        $tenantId = $request->input('tenant_id') ?: $lease?->tenant_id;
        $ownerId = $this->resolvePreviewOwnerId($request, $lease);
        abort_unless(
            $template->user_id === null
                || (string) $template->user_id === (string) $effectiveUser?->id
                || (string) $template->user_id === (string) $ownerId,
            403
        );

        $variables = $needsUserVariables
            ? $documentTemplateService->userVariables($effectiveUser, 'user', $requiredVariables)
            : [];
        $tenant = $needsTenantVariables
            ? ($lease?->tenant ?? Tenants::with('user')->find($tenantId))
            : null;

        if ($tenant?->user) {
            $variables += $documentTemplateService->userVariables(
                $tenant->user,
                'tenant',
                $requiredVariables
            );
            $variables['tenant_name'] = $tenant->user->name ?? 'N/A';
            $variables['tenant_ic'] = $documentTemplateService->identificationValue($tenant, $tenant->user);
        }

        $owner = $needsOwnerVariables && $ownerId ? User::find($ownerId) : null;
        if ($owner) {
            $variables += $documentTemplateService->userVariables(
                $owner,
                'owner',
                $requiredVariables
            );
            $variables['owner_name'] = $owner->name ?? 'N/A';
            $variables['owner_ic'] = $documentTemplateService->identificationValue($owner->owner, $owner);
        }

        $refundContext = [];

        if ($lease) {
            $variables = array_merge(
                $variables,
                $leaseService->getAgreementVariables($lease)
            );
        }

        $selectedAsset = $category === 'agreement' && !$lease
            ? $this->resolvePreviewAsset($request)
            : null;
        if ($selectedAsset) {
            $servicePackage = match (true) {
                $selectedAsset instanceof Property => $selectedAsset->type,
                $selectedAsset instanceof Unit => $selectedAsset->type,
                $selectedAsset instanceof Room => $selectedAsset->room_type,
                default => null,
            };
            $servicePackage = filled($servicePackage) ? $servicePackage : 'Not recorded';
            $servicePackageDescription = match (strtoupper(trim($servicePackage))) {
                'WIFI UNIT' => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER, FACILITY ALLOWANCE & HIGH SPEED WIFI',
                'WIFI ZONE' => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER, FACILITY ALLOWANCE, CCTV, FACILITY ROOM & HIGH SPEED WIF',
                'STANDARD UNIT' => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER & FACILITY ALLOWANCE',
                'PREMIUM UNIT' => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER, FACILITY ALLOWANCE, CCTV, FACILITY ROOM & HIGH SPEED WIFI',
                default => 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER,FACILITY ALLOWANCE, CCTV, FACILITY ROOM &HIGH SPEED WIFI',
            };

            $variables['property_name'] = $selectedAsset->name
                ?? $selectedAsset->unit_no
                ?? $selectedAsset->room_no
                ?? 'N/A';
            $variables['property_address'] = $selectedAsset->full_address
                ?? $selectedAsset->property?->full_address
                ?? $selectedAsset->unit?->property?->full_address
                ?? 'N/A';
            $variables['property_type'] = class_basename($selectedAsset);
            $variables['leasable_type'] = $servicePackage;
            $variables['leasalable_type'] = $servicePackage;
            $variables['service_package'] = $servicePackage;
            $variables['service_package_description'] = $servicePackageDescription;
        }

        foreach ($category === 'agreement' ? [
            'start_date',
            'end_date',
            'check_out_date',
            'end_agreement_date',
            'rent_price',
            'security_deposit',
            'utilities_deposit',
            'combined_deposit',
            'total_deposit',
            'management_fee',
        ] : ['check_out_date'] as $key) {
            if (!$lease && $request->filled($key)) {
                $variables[$key] = $request->input($key);
            }
        }

        if ($category === 'refund') {
            $submittedRefundContext = $request->input('refund_context');
            if (is_string($submittedRefundContext)) {
                $submittedRefundContext = json_decode($submittedRefundContext, true);
            }

            if (is_array($submittedRefundContext)) {
                $refundContext = $submittedRefundContext;
            } elseif ($lease) {
                $refund = $lease->refunds()
                    ->with('items.feeType')
                    ->latest('created_at')
                    ->first();

                if (!$refund) {
                    return response()->json([
                        'message' => 'No saved refund was found for this lease.',
                    ], 422);
                }

                $refundContext = [
                    'total_deposit_collected_cents' => (int) $refund->deposit_collected_cents,
                    'auto_deduction_total_cents' => (int) $refund->auto_deduction_cents,
                    'active_refund' => [
                        'id' => $refund->id,
                        'status' => $refund->status?->value ?? $refund->status,
                        'gross_amount_cents' => (int) $refund->gross_amount_cents,
                        'manual_deduction_cents' => (int) $refund->manual_deduction_cents,
                        'deduction_amount_cents' => (int) $refund->deduction_amount_cents,
                        'net_amount_cents' => (int) $refund->net_amount_cents,
                        'items' => $refund->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'type' => $item->type?->value ?? $item->type,
                                'description' => $item->description
                                    ?: $item->feeType?->name
                                    ?: 'Refund deduction',
                                'fee_type_id' => $item->fee_type_id,
                                'qty' => (int) ($item->qty ?: 1),
                                'unit_amount_cents' => (int) $item->unit_amount_cents,
                                'amount_cents' => (int) $item->amount_cents,
                            ];
                        })->values()->all(),
                    ],
                ];
            } else {
                $refundContext = [];
            }

            $totalDeposit = (int) data_get($refundContext, 'total_deposit_collected_cents', 0);
            $autoDeduction = (int) data_get($refundContext, 'auto_deduction_total_cents', 0);
            $unpaidInvoiceItems = is_array($submittedRefundContext)
                ? collect(data_get($refundContext, 'unpaid_invoices', []))
                ->map(fn ($invoice) => [
                    'description' => $invoice['description'] ?? 'Unpaid Invoice',
                    'invoice_no' => $invoice['invoice_no'] ?? null,
                    'type' => 'utility',
                    'source' => 'Unpaid invoice',
                    'qty' => 1,
                    'amount_cents' => (int) ($invoice['balance_cents'] ?? 0),
                ])
                : collect();
            $manualItems = collect(data_get($refundContext, 'active_refund.items', []))
                ->map(fn ($item) => is_array($item) ? $item : $item->toArray())
                ->map(function (array $item) {
                    $type = $item['type'] ?? null;
                    $item['type'] = is_array($type)
                        ? ($type['value'] ?? null)
                        : (is_object($type) && property_exists($type, 'value')
                            ? $type->value
                            : $type);
                    $item['description'] = $item['description']
                        ?? data_get($item, 'fee_type.name')
                        ?? $item['fee_type_name']
                        ?? 'Refund deduction';
                    $item['qty'] = (int) ($item['qty'] ?? 1);
                    $item['amount_cents'] = (int) ($item['amount_cents'] ?? 0);

                    return $item;
                })
                ->map(fn ($item) => array_merge($item, [
                    'source' => 'Manual deduction',
                ]))
                ->values()
                ->all();
            $isSavedRefundView = !is_array($submittedRefundContext);
            $refundItems = $isSavedRefundView
                ? $manualItems
                : $unpaidInvoiceItems->concat($manualItems)->values()->all();
            $customDeduction = $isSavedRefundView
                ? (int) data_get($refundContext, 'active_refund.manual_deduction_cents', 0)
                : collect($manualItems)->sum(fn ($item) => (int) ($item['amount_cents'] ?? 0));
            $totalDeductions = $isSavedRefundView
                ? (int) data_get($refundContext, 'active_refund.deduction_amount_cents', 0)
                : $autoDeduction + $customDeduction;
            $netRefund = max(
                0,
                (int) data_get(
                    $refundContext,
                    'active_refund.net_amount_cents',
                    $totalDeposit - $totalDeductions
                )
            );
            $amount = fn (int $cents) => number_format($cents / 100, 2);
            $depositReceiptNo = $lease
                ? $leaseService->getDepositReceiptNumbers($lease)
                : 'N/A';

            $variables += [
                'checkout_date' => $request->input('check_out_date', 'N/A'),
                'total_deposit_collected' => $amount($totalDeposit),
                'deposit_receipt_no' => $depositReceiptNo,
                'auto_deductions_total' => $amount($autoDeduction),
                'custom_deductions_total' => $amount($customDeduction),
                'total_deductions' => $amount($totalDeductions),
                'nett_refund_amount' => $amount($netRefund),
                'unpaid_invoices_breakdown' => $amount($autoDeduction),
                'custom_items_breakdown' => $amount($customDeduction),
            ];
        }

        $rent = 0;
        $security = 0;
        $utilities = 0;
        $totalDeposit = 0;
        $management = 0;
        $feeTypes = $category === 'agreement'
            ? FeeType::whereIn('id', collect($request->input('charges', []))
                ->pluck('fee_type_id')
                ->filter())
                ->get()
                ->keyBy('id')
            : collect();

        foreach ($category === 'agreement' ? $request->input('charges', []) : [] as $charge) {
            $amount = (float) ($charge['amount'] ?? 0);
            $feeType = $feeTypes->get($charge['fee_type_id']);
            $categoryName = strtolower($feeType?->category?->value ?? '');
            $description = strtolower((string) ($charge['description'] ?? $feeType?->name ?? ''));

            if ($categoryName === 'rent') {
                $rent += $amount;
            } elseif ($categoryName === 'deposit') {
                $totalDeposit += $amount;
                if (str_contains($description, 'security')) {
                    $security += $amount;
                }
                if (str_contains($description, 'utilities')) {
                    $utilities += $amount;
                }
            } elseif ($categoryName === 'management') {
                $management += $amount;
            }
        }

        if ($category === 'agreement' && $request->has('charges')) {
            $variables += [
                'rent_price' => number_format($rent, 2),
                'security_deposit' => number_format($security, 2),
                'utilities_deposit' => number_format($utilities, 2),
                'combined_deposit' => number_format($security + $utilities, 2),
                'total_deposit' => number_format($totalDeposit, 2),
                'management_fee' => number_format($management, 2),
            ];
        }

        Log::channel('testing')->info('Lease preview variables before replacement:', [
            'request_lease_id' => $leaseId,
            'lease_found' => $lease !== null,
            'lease' => $lease?->toArray(),
            'category' => $category,
            'variables' => $variables,
        ]);
        $content = $documentTemplateService->replaceData($template->html_template, $variables);
        if ($category === 'refund') {
            $content = $documentTemplateService->replaceRefundTable($content, $refundItems ?? [], [
                'deposit_receipt_no' => $variables['deposit_receipt_no'] ?? 'N/A',
                'total_deposit_collected' => $variables['total_deposit_collected'] ?? '0.00',
            ]);
        }

        return response()->json([
            'title' => $template->title,
            'content' => $content,
        ]);
    }

    public function checkoutPreview(Lease $lease, LeaseService $leaseService)
    {
        if (Gate::denies('owner-admin') && Gate::denies('leases.lease controller')) {
            return response()->json(['message' => 'You have no permission.'], 403);
        }

        $user = get_effective_user();
        $query = Lease::query()->whereKey($lease->id);
        if ($user->role !== 'admin') {
            $query = $this->applyLeaseOwnershipFilter($query, $user);
        }

        $lease = $query->firstOrFail();

        return response()->json($leaseService->getCheckoutPreviewData($lease));
    }

    private function resolvePreviewOwnerId(Request $request, ?Lease $lease = null): ?string
    {
        if ($request->filled('owner_id')) {
            return (string) $request->input('owner_id');
        }

        if ($lease?->leasable) {
            return $lease->leasable->owner_id
                ?? $lease->owner_user_id;
        }

        foreach ([
            ['property_id', Property::class],
            ['unit_id', Unit::class],
            ['room_id', Room::class],
        ] as [$field, $model]) {
            if (!$request->filled($field)) {
                continue;
            }

            $record = $model::with($model === Room::class ? 'unit' : [])->find($request->input($field));
            return $record?->owner_id
                ?? ($model === Room::class ? $record?->unit?->owner_id : null);
        }

        return null;
    }

    private function resolvePreviewAsset(Request $request): Property|Unit|Room|null
    {
        if ($request->filled('property_id')) {
            return Property::with('owner')->find($request->input('property_id'));
        }

        if ($request->filled('unit_id')) {
            return Unit::with(['owner', 'property'])->find($request->input('unit_id'));
        }

        if ($request->filled('room_id')) {
            return Room::with(['owner', 'unit.property'])->find($request->input('room_id'));
        }

        return null;
    }

    public function show(Request $request, Lease $lease, LeaseService $leaseService)
    {
        if (Gate::denies('owner-admin') && Gate::denies('leases.show')) {
            return view('errors.403');
        }

        $settings = $this->settingService->getEffectiveSettings();
        $latePenaltyConfig = $settings['late_penalty_config'] ?? null;
        $dueDateDays = (int) data_get($settings, 'due_date_config.value.days', 7);

        //Log::channel('testing')->info('Formatted Invoices Payload:', [
        //    'settings' => $settings,
        //    'latePenaltyConfig' => $latePenaltyConfig,
        //]);

        // If it's an AJAX request, we want to support fetching invoices 
        // for ANY lease in the history chain (passed via query parameter, fallback to current route lease)
        if ($request->ajax()) {
            $targetLeaseId = $request->get('lease_id', $lease->id);
            $targetLease = Lease::findOrFail($targetLeaseId);

            $invoices = $targetLease->invoices()
                ->with([
                    'documentTemplate',
                    'items.feeType',
                    'transactions.documentTemplate',
                    'transactions.approver',
                ])
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
                        'name' => $subItem->feeType?->name ?? 'Item',
                        'description' => $subItem->description,
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
                            'amount_paid'    => number_format(($transaction->amount_paid ?? 0) / 100, 2),
                            'payment_date'   => $transaction->payment_date
                                ? \Carbon\Carbon::parse($transaction->payment_date)->format('d/m/Y')
                                : ($transaction->created_at?->format('d/m/Y') ?? '—'),

                            'payment_method' => $transaction->payment_method ?? '—',
                            'reference_no'   => $transaction->transaction_ref ?? '—',

                            // 當時確認 Payment 的登入用戶
                            'issued_by_name'  => $transaction->approver?->name ?? 'N/A',
                            'issued_by_email' => $transaction->approver?->email ?? 'N/A',

                            'issued_by_phone' => $transaction->approver?->phone
                                ?? $transaction->approver?->phone_number
                                ?? 'N/A',
                        ]),
                    ];
                });

                return [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'document_template_id' => $invoice->document_template_id ?? '—',
                    'variables'      => $variables,
                    'invoice_items' => $invoiceItems,
                    'receipts'      => $receipts,
                    'period' => $formattedPeriod,
                    'due_date' => $invoice->due_date?->format('Y-m-d') ?? '',
                    'due_date_formatted' => $invoice->due_date?->format('d/m/Y') ?? '—',
                    'total_amount' => number_format($invoice->total_amount / 100 ?? 0, 2),
                    'amount_paid' => number_format($invoice->amount_paid / 100 ?? 0, 2),
                    'amount_balance' => number_format(($invoice->total_amount / 100 ?? 0) - ($invoice->amount_paid / 100 ?? 0), 2),
                    'status' => strtolower($invoice->status ?? 'unpaid'),
                    'remarks' => $invoice->remarks ?? '—',
                    'template_title' => $invoice->documentTemplate?->title,
                    'template_html'  => $invoice->documentTemplate?->html_template,
                ];
            });

            //Log::channel('testing')->info('Formatted Invoices Payload:', [
            //    'formattedInvoices' => $formattedInvoices
            //]);


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

        $historyJson = $leaseHistory->keyBy('id')->map(function ($item) use ($latePenaltyConfig) {
            $chargesSum = $item->charges->sum('amount');
            $totalRentPrice = $item->rent_price + $chargesSum;

            return [
                'status' => $item->status,
                'is_current' => (bool) $item->is_current,
                'checked_out_at' => $item->checked_out_at ? $item->checked_out_at_formatted : null,
                'agreement_ended_at' => $item->agreement_ended_at ? $item->agreement_ended_at_formatted : null,
                'start_date' => $item->start_date_formatted ?? null,
                'end_date' => $item->end_date_formatted ?? null,
                'term_type' => strtoupper($item->term_type) ?? 'N/A',
                'total_rent_price' => number_format($totalRentPrice / 100, 2),
                'charges' => $item->charges->map(function ($charge) {
                    return [
                        'id' => $charge->id,
                        'name' => $charge->feeType?->name ?? 'Charge',
                        'description' => $charge->description,
                        'amount' => number_format($charge->amount / 100, 2),
                        'next_billing_date' => $charge->next_billing_date?->format('d/m/Y') ?? '',
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
                'leasable_url' => match (true) {
                    $item->leasable instanceof Property => route('admin.properties.show', $item->leasable->id),
                    $item->leasable instanceof Unit => route('admin.units.show', $item->leasable->id),
                    $item->leasable instanceof Room => route('admin.rooms.show', $item->leasable->id),
                    default => null,
                },
                'rent_mode' => strtoupper($item->term_type ?? 'N/A'),
                'check_out_date' => $item->checked_out_at?->format('d/m/Y') ?? 'N/A',
                'end_agreement_date' => $item->agreement_ended_at?->format('d/m/Y') ?? 'N/A',
                'wallet_balance' => $item->tenant?->user?->wallet?->formatted_balance ?? '0.00',
                'settings' => $latePenaltyConfig,

                // 🌟 這裡加上 with(['documentTemplate']) 載入模板關聯
                // 🌟 加入 transactions.documentTemplate 關聯
                'invoices' => $item->invoices()
                    ->with([
                        'documentTemplate',
                        'items.feeType',
                        'transactions.documentTemplate',
                        'transactions.approver',
                    ])->latest()->get()->map(function ($invoice) {

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
                                'name' => $subItem->feeType?->name ?? 'Item',
                                'description' => $subItem->description,
                                'amount' => number_format($subItem->amount / 100 ?? 0, 2),
                                'category' => $subItem->feeType?->category ?? '—',
                            ];
                        });

                        // Fallback if no items relationship records exist, but main invoice has a description
                        if ($invoiceItems->isEmpty() && $invoice->description) {
                            $invoiceItems->push([
                                'description' => $invoice->feeType?->name,
                                'amount' => number_format($invoice->total_amount / 100 ?? 0, 2),
                                'category' => '—',
                            ]);
                        }

                        // 💡 關鍵修復：在這裡也要把 Receipts 資料打包進去，和上方 AJAX 保持一致
                        $receipts = $invoice->transactions->map(function ($transaction) use ($variables) {
                            return [
                                'id' => $transaction->id,
                                'receipt_no' => $transaction->receipt_no ?? '—',
                                'document_template_id' => $transaction->document_template_id ?? '—',
                                'template_title' => $transaction->documentTemplate?->title,
                                'template_html' => $transaction->documentTemplate?->html_template,
                                'amount' => number_format(($transaction->amount_paid ?? 0) / 100, 2),
                                'date' => $transaction->payment_date
                                    ? \Carbon\Carbon::parse($transaction->payment_date)->format('d M Y')
                                    : $transaction->created_at?->format('d M Y'),

                                'variables' => array_merge($variables, [
                                    'receipt_no' =>$transaction->receipt_no ?? '—',
                                    'amount_paid' =>number_format(($transaction->amount_paid ?? 0) / 100, 2),
                                    'payment_date' => $transaction->payment_date
                                        ? \Carbon\Carbon::parse($transaction->payment_date)->format('d/m/Y')
                                        : ($transaction->created_at?->format('d/m/Y') ?? '—'),
                                    'payment_method' => $transaction->payment_method ?? '—',
                                    'reference_no' => $transaction->transaction_ref ?? '—',
                                    // 當時確認這筆 Payment 的用戶
                                    'issued_by_name' => $transaction->approver?->name ?? 'N/A',
                                    'issued_by_email' => $transaction->approver?->email ?? 'N/A',
                                    'issued_by_phone' => $transaction->approver?->phone
                                        ?? $transaction->approver?->phone_number
                                        ?? 'N/A',
                                ]),
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
                            'due_date' => $invoice->due_date?->format('Y-m-d') ?? '',
                            'due_date_formatted' => $invoice->due_date?->format('d/m/Y') ?? '—',
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

        $leaseModalData = $leaseService->getLeaseFormPayload($userId, $request);

        return view('adminSide.leases.show', array_merge([
            'lease' => $lease,
            'leaseHistory' => $leaseHistory,
            'invoices' => $invoices,
            'historyJson' => $historyJson,
            'feeTypes' => $feeTypes,
            'settings' => $settings,
            'dueDateDays' => $dueDateDays,
            'allowManualRecurringInvoice' => data_get($settings, 'due_date_config.value.recurring_type') !== 'auto',
        ], $leaseModalData));
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
        if (Gate::denies('owner-admin') && Gate::denies('leases.upload stamping')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

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

    public function cancel(Request $request, Lease $lease, LeaseService $leaseService)
    {
        $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $leaseService->cancelLease($lease, $request->cancellation_reason);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['cancellation_reason' => $e->getMessage()]);
        }

        return redirect()->route('admin.leases.index')->with('success', 'Lease has been successfully cancelled.');
    }
}
