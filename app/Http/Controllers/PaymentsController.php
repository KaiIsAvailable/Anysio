<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenants;
use App\Models\Lease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use \Illuminate\Validation\Rule;
use \Carbon\Carbon;
use \Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PaymentsController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $query = Payment::with(['tenant.user', 'lease.room']);

        if (Gate::denies('super-admin')) {
            // 嵌套三层 whereHas，顺着关系链摸到最深处的 user_id
            $query->whereHas('lease.room.owner', function($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        // 搜索逻辑
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                ->orWhereHas('tenant.user', function($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%");
                });
            });
        }

        $payments = $query->latest()->paginate(10)->withQueryString();
        return view('adminSide.tenants.payments.index', compact('payments'));
    }

    // 参数直接改为 Lease $lease
    public function generateMonthlyInvoice(Lease $lease)
    {
        // 1. 状态校验
        if (!in_array($lease->status, ['New', 'Renew'])) {
            return back()->with('error', 'This lease is not active.');
        }

        $termType = strtolower($lease->term_type); 
        $startDate = Carbon::parse($lease->start_date)->startOfDay();
        $endDate = Carbon::parse($lease->end_date)->startOfDay();

        // 2. 生成该租约所有应收的周期 (Periods)
        $allPeriods = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            // --- 核心逻辑：根据类型确定存入数据库的精确日期 ---
            $storageDate = match($termType) {
                'daily'   => $current->copy()->format('Y-m-d'),
                'weekly'  => $current->copy()->format('Y-m-d'), // 存周起始日
                'monthly' => $current->copy()->startOfMonth()->format('Y-m-d'), // 强制存 1 号
                'yearly'  => $current->copy()->startOfYear()->format('Y-m-d'),  // 强制存 1 月 1 号
                default   => $current->copy()->format('Y-m-d')
            };

            $allPeriods[] = [
                'date' => $storageDate,
                // key 用于精准查重，保持与 storageDate 一致即可
                'key'  => $storageDate 
            ];

            // 步进：移动到下一个周期
            match($termType) {
                'daily'   => $current->addDay(),
                'weekly'  => $current->addWeek(),
                'monthly' => $current->addMonth(),
                'yearly'  => $current->addYear(),
                default   => $current->addMonth(),
            };
        }

        // 3. 找出所有已存在的账单 (只需查 period 字段)
        $existingKeys = Payment::where('lease_id', $lease->id)
            ->where('payment_type', 'rent')
            ->where('status', '!=', 'void')
            ->pluck('period') // 直接获取 period 数组
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->toArray();

        // 4. 寻找第一个还没生成的周期
        $target = null;
        foreach ($allPeriods as $p) {
            if (!in_array($p['key'], $existingKeys)) {
                $target = $p;
                break; 
            }
        }

        // 5. 如果没有找到，说明全出了
        if (!$target) {
            return back()->with('error', "All $termType invoices for this period have already been generated.");
        }

        // 6. 创建账单
        $targetDate = Carbon::parse($target['date']);
        $newInvoiceNo = $this->generateSequenceInvoiceNo('RENT');

        Payment::create([
            'id'           => (string) Str::ulid(),
            'tenant_id'    => $lease->tenant_id,
            'lease_id'     => $lease->id,
            'invoice_no'   => $newInvoiceNo,
            'payment_type' => 'rent',
            'period'       => $targetDate->format('Y-m-d'), // 这里存的就是 01号 或 01-01
            'amount_due'   => (int) round($lease->rent_price * 100),
            'amount_paid'  => 0,
            'status'       => 'unpaid',
        ]);

        // 7. 格式化提示语 (让用户看得舒服)
        $msg = match($termType) {
            'daily'   => $targetDate->format('d M Y'),
            'weekly'  => $targetDate->format('d M Y') . ' (Weekly)',
            'monthly' => $targetDate->format('M Y'),
            'yearly'  => $targetDate->format('Y'),
            default   => $targetDate->format('Y-m-d')
        };

        return back()->with('success', "Invoice for $msg generated successfully.");
    }

    public function storeManualInvoice(Request $request, Tenants $tenant)
    {
        // 1. 验证表单提交的数据
        $payload = $request->validate([
            'payment_type' => ['required', 'string', 'max:50'], // 例如：Utilities, Deposit, Maintenance
            'amount_due'   => ['required', 'numeric', 'min:0.01'],
            'period'       => ['required', 'date'],
            'remarks'      => ['nullable', 'string', 'max:255'],
        ]);

        // 获取活跃租约
        $lease = $tenant->leases()->whereIn('status', ['New', 'Renew'])->first();

        return DB::transaction(function () use ($payload, $tenant, $lease) {
            
            // 2. 将金额转为分
            $amountCents = (int) round($payload['amount_due'] * 100);

            // 3. 生成单号 (建议给手动账单一个前缀或通用标识)
            $newInvoiceNo = $this->generateSequenceInvoiceNo('INV'); 

            // 4. 执行创建
            Payment::create([
                'id'           => (string) Str::ulid(),
                'tenant_id'    => $tenant->id,
                'lease_id'     => $lease ? $lease->id : null, // 手动账单可能不关联租约
                'invoice_no'   => $newInvoiceNo,
                'payment_type' => $payload['payment_type'],
                'period'       => Carbon::parse($payload['period'])->startOfMonth(),
                'amount_due'   => $amountCents,
                'amount_paid'  => 0,
                'status'       => 'unpaid',
                'remarks'      => $payload['remarks'],
            ]);

            return back()->with('success', 'Manual invoice generated successfully.');
        });
    }

    public function update(Request $request, Payment $payment)
    {
        $payload = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'received_via' => ['required', 'string'],
            'transaction_ref' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        // 1. 核心阻断：检查是否有任何【更早日期】且【未付清】的单子
        // 无论类型是 'rent' 还是 'Underpaid xxx'，只要 period 更早且未付，就必须先还
        $earlierUnpaid = Payment::where('tenant_id', $payment->tenant_id)
            ->where('status', 'unpaid')
            ->where('period', '<', $payment->period)
            ->exists();

        if ($earlierUnpaid) {
            return back()->with('error', 'Must clear all previous outstanding balances (including underpayments) before paying for this period.');
        }

        return DB::transaction(function () use ($payload, $payment) {
            // 1. 转换输入为分
            $totalInputCents = (int) round($payload['amount_paid'] * 100);
            
            // 2. 获取数据库里原始的“分” (避开 Model 的 get 转换)
            $dueCents = $payment->getRawOriginal('amount_due'); 

            $underPaidCents = 0;
            $overPaidCents = 0;
            $actualAppliedCents = $totalInputCents;

            if ($totalInputCents < $dueCents) {
                $underPaidCents = $dueCents - $totalInputCents;
                
                Payment::create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $payment->tenant_id,
                    'lease_id' => $payment->lease_id,
                    'invoice_no' => $this->generateSequenceInvoiceNo('RENT'), 
                    'payment_type' => 'Underpaid ' . $payment->invoice_no,
                    'period' => $payment->period, 
                    'amount_due' => $underPaidCents, // Model set: 转回 Cent
                    'amount_paid' => 0, 
                    'status' => 'unpaid',
                    'remarks' => 'Remaining balance from ' . $payment->invoice_no,
                ]);
            } 
            // 3. 处理溢缴 (Overpaid)
            elseif ($totalInputCents > $dueCents) {
                $overPaidCents = $totalInputCents - $dueCents;
                $actualAppliedCents = $dueCents;

                // 寻找下一期抵扣
                $nextPayment = Payment::where('tenant_id', $payment->tenant_id)
                    ->where('status', 'unpaid')
                    ->where('period', '>', $payment->period)
                    ->orderBy('period', 'asc')
                    ->first();

                if ($nextPayment) {
                    // 注意：这里也要拿原始的分来减
                    $nextDueCents = $nextPayment->getRawOriginal('amount_due');
                    $newDueCents = max(0, $nextDueCents - $overPaidCents);
                    
                    $nextPayment->update([
                        'amount_due' => $newDueCents, // 存入扣减后的分
                        'remarks' => $nextPayment->remarks . " | Deducted RM" . ($overPaidCents / 100)
                    ]);
                }
    }

            // 4. 更新当前账单
            $payment->update([
                'amount_paid' => $actualAppliedCents,    // Model set: 转回 Cent
                'amount_under_paid' => $underPaidCents,  // Model set: 转回 Cent
                'amount_over_paid' => $overPaidCents,    // Model set: 转回 Cent
                'status' => 'paid',
                'payment_date' => $payload['payment_date'],
                'received_via' => $payload['received_via'],
                'transaction_ref' => $payload['transaction_ref'],
                'remarks' => $payload['remarks'],
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return back()->with('success', 'Payment recorded.');
        });
    }

    public function voidPayment(Payment $payment)
    {
        $payment->update([
            'status' => 'void',
            'remarks' => $payment->remarks . "\n[VOIDED at " . now()->format('Y-m-d H:i') . " by " . Auth::user()->name . "]",
        ]);

        return back()->with('success', 'Payment marked as void. Original amount preserved for audit.');
    }

    public static function generateSequenceInvoiceNo($payment_type)
    {
        $type = strtoupper($payment_type);
        $currentYear = now()->format('Y'); // 获取当前年份，如 2026
        $today = now()->format('Ymd');     // 完整日期用于显示
        
        // 关键点：匹配前缀只锁死到年份
        // 搜索格式类似于：INV-RENT-2026%
        $searchPrefix = "INV-{$type}-{$currentYear}";
        
        $lastInvoice = Payment::where('invoice_no', 'like', $searchPrefix . '%')
            ->orderBy('invoice_no', 'desc')
            ->first();

        if ($lastInvoice) {
            // 提取最后 5 位数字并加 1
            // substr($str, -5) 永远抓取字符串最后面的 5 位
            $lastNum = intval(substr($lastInvoice->invoice_no, -5));
            $nextNum = str_pad($lastNum + 1, 5, '0', STR_PAD_LEFT);
        } else {
            // 如果今年还没开过票，则从 00001 开始
            $nextNum = '00001';
        }

        // 返回格式：INV-类型-年月日-序号
        // 例如：INV-RENT-20260212-00001
        return "INV-{$type}-{$today}-{$nextNum}";
    }
}
