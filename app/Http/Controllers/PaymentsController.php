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
        // 1. 校验租约状态（双重保险）
        if (!in_array($lease->status, ['New', 'Renew'])) {
            return back()->with('error', 'This lease is not active.');
        }

        // 2. 生成该租约期间所有的 [年-月] 列表
        $startDate = Carbon::parse($lease->start_date)->startOfMonth();
        $endDate = Carbon::parse($lease->end_date)->startOfMonth();
        
        $allMonths = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $allMonths[] = $current->format('Y-m');
            $current->addMonth();
        }

        // 3. 只针对这个具体的 lease_id 检查已有的账单
        $existingMonths = Payment::where('lease_id', $lease->id)
            ->where('payment_type', 'rent')
            ->where('status', '!=', 'void')
            ->get()
            ->map(fn($p) => Carbon::parse($p->period)->format('Y-m'))
            ->toArray();

        // 4. 找出第一个缺的月份
        $targetMonth = null;
        foreach ($allMonths as $month) {
            if (!in_array($month, $existingMonths)) {
                $targetMonth = $month;
                break; 
            }
        }

        // 5. 执行
        if (!$targetMonth) {
            return back()->with('error', 'All invoices for this specific lease have been generated.');
        }

        $targetPeriod = Carbon::parse($targetMonth . '-01'); 
        $newInvoiceNo = $this->generateSequenceInvoiceNo('RENT');

        Payment::create([
            'id' => (string) Str::ulid(),
            'tenant_id' => $lease->tenant_id, // 从 lease 自动获取租客 ID
            'lease_id' => $lease->id,
            'invoice_no' => $newInvoiceNo,
            'payment_type' => 'rent',
            'period' => $targetPeriod->format('Y-m-d'),
            'amount_due' => $lease->monthly_rent,
            'amount_paid' => 0,
            'status' => 'unpaid',
        ]);

        return back()->with('success', 'Rent invoice for ' . $targetPeriod->format('M Y') . ' generated.');
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
            $paidCents = (int) round($payload['amount_paid'] * 100);
            $dueCents = $payment->amount_due;

            $underPaid = 0;
            $overPaid = 0;

            // 2. 处理欠收情况
            if ($paidCents < $dueCents) {
                $underPaid = $dueCents - $paidCents;
                
                // 生成补缴单，关键点：period 保持不变，这样它会继续阻塞后续月份
                Payment::create([
                    'id' => (string) Str::ulid(),
                    'tenant_id' => $payment->tenant_id,
                    'lease_id' => $payment->lease_id,
                    'invoice_no' => $this->generateSequenceInvoiceNo('RENT'), 
                    'payment_type' => 'Underpaid ' . $payment->invoice_no,
                    'period' => $payment->period, 
                    'amount_due' => $underPaid,
                    'amount_paid' => 0, 
                    'status' => 'unpaid',
                    'remarks' => 'Remaining balance from ' . $payment->invoice_no,
                ]);
            } elseif ($paidCents > $dueCents) {
                $overPaid = $paidCents - $dueCents;
                $paidCents = $dueCents; // 本张单只收它该收的部分

                // --- 核心：处理多出的钱 (Overpaid) ---
                // 寻找该租客下一个“最接近”的未付账单
                $nextPayment = Payment::where('tenant_id', $payment->tenant_id)
                    ->where('status', 'unpaid')
                    ->where('period', '>', $payment->period) // 必须是未来的账单
                    ->orderBy('period', 'asc')              // 拿最近的一个
                    ->first();

                if ($nextPayment) {
                    // 将溢出的钱从下一个账单的应付金额中扣除
                    // 注意：这里要确保扣除后金额不为负数（如果多给的钱超过了下个月租金）
                    $newAmountDue = max(0, $nextPayment->amount_due - $overPaid);
                    
                    $nextPayment->update([
                        'amount_due' => $newAmountDue,
                        'remarks' => $nextPayment->remarks . " | Deducted " . ($overPaid / 100) . " from previous overpayment (INV: {$payment->invoice_no})"
                    ]);

                    // 如果溢缴款非常大，连下个月都扣完了，多出的部分可以在这里继续循环处理，
                    // 或者暂时存入租客的账户余额字段（如果有的话）。
                }
            }

            // 3. 更新当前账单状态
            $payment->update([
                'amount_paid' => $paidCents,
                'amount_under_paid' => $underPaid,
                'amount_over_paid' => $overPaid,
                'status' => 'paid', // 只要处理了，这张旧单就标记为 paid
                'payment_date' => $payload['payment_date'],
                'received_via' => $payload['received_via'],
                'transaction_ref' => $payload['transaction_ref'],
                'remarks' => $payload['remarks'],
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return back()->with('success', 'Payment recorded and balance updated.');
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
