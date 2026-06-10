<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenants;
use App\Models\Lease;
use App\Models\UserPayment;
use App\Models\UserManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use \Illuminate\Validation\Rule;
use \Carbon\Carbon;
use \Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PaymentsController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $query = Payment::with(['tenant.user', 'lease.room']);

        if (!Gate::allows('super-admin')) {
            // 关键：只要 tenant 表里的 created_by 是当前登录用户，就显示这条账单
            $query->whereHas('tenant', function($q) use ($userId) {
                $q->where('created_by', $userId);
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

        $payments = $query->latest()->paginate(10)->onEachSide(1)->withQueryString();
        return view('adminSide.tenants.payments.index', compact('payments'));
    }

    // 参数直接改为 Lease $lease
    public function generateMonthlyInvoice($leaseId)
    {
        // 1. 查找租约
        $lease = Lease::findOrFail($leaseId);

        // 2. 状态校验
        if (!in_array($lease->status, ['New', 'Renew'])) {
            return back()->with('error', 'Invalid to generate invoice for your lease status');
        }

        // 3. 计算逻辑：获取下一个应该生成的周期
        $targetDate = $this->calculateNextPendingPeriod($lease);

        if (!$targetDate) {
            return back()->with('error', "You have fully generated all invoice for this lease");
        }

        // 4. 执行创建：使用事务确保数据安全
        DB::transaction(function () use ($lease, $targetDate) {
            $this->createRentPayment($lease, $targetDate);
        });

        $previousUrl = url()->previous();

        $redirectUrl = $previousUrl . '#lease-' . $leaseId;

        return redirect()->to($redirectUrl)
                        ->with('success', "Invoice " . $targetDate->format('M Y') . " successfully generated");
    }

    public static function calculateNextPendingPeriod(Lease $lease)
    {
        $startDate = Carbon::parse($lease->start_date)->startOfDay();
        $endDate = Carbon::parse($lease->end_date)->startOfDay();
        
        // 获取所有已存在的 period，用于查重
        $existingPeriods = Payment::where('lease_id', $lease->id)
            ->where('payment_type', 'rent')
            ->where('status', '!=', 'void')
            ->pluck('period')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->toArray();

        $current = $startDate->copy();
        while ($current <= $endDate) {
            $period = self::getStorageDate($current, $lease->term_type);
            
            // 如果这个周期不在数据库里，就是我们要生成的那个
            if (!in_array($period, $existingPeriods)) {
                return Carbon::parse($period);
            }
            
            $current = self::advancePeriod($current, $lease->term_type);
        }
        
        return null; // 全生成了
    }

    private static function getStorageDate(Carbon $date, $termType)
    {
        return match(strtolower($termType)) {
            'daily'   => $date->copy()->format('Y-m-d'),
            'weekly'  => $date->copy()->format('Y-m-d'),
            'monthly' => $date->copy()->startOfMonth()->format('Y-m-d'),
            'yearly'  => $date->copy()->startOfYear()->format('Y-m-d'),
            default   => $date->copy()->format('Y-m-d')
        };
    }

    private static function advancePeriod(Carbon $date, $termType)
    {
        return match(strtolower($termType)) {
            'daily'   => $date->addDay(),
            'weekly'  => $date->addWeek(),
            'monthly' => $date->addMonth(),
            'yearly'  => $date->addYear(),
            default   => $date->addMonth()
        };
    }

    private function createRentPayment(Lease $lease, Carbon $targetDate)
    {
        Payment::create([
            'id'           => (string) Str::ulid(),
            'tenant_id'    => $lease->tenant_id,
            'lease_id'     => $lease->id,
            'invoice_no'   => self::generateSequenceInvoiceNo('RENT'), // 调用你之前的序列生成器
            'payment_type' => 'rent',
            'period'       => $targetDate->format('Y-m-d'),
            'amount_due'   => (int) round($lease->rent_price * 100),
            'amount_paid'  => 0,
            'status'       => 'unpaid',
        ]);
    }

    public function storeManualInvoice(Request $request, Lease $lease)
    {
        // 1. 验证表单提交的数据
        $payload = $request->validate([
            'payment_type' => ['required', 'string', 'max:50'], // 例如：Utilities, Deposit, Maintenance
            'amount_due'   => ['required', 'numeric', 'min:0.01'],
            'period'       => ['required', 'date'],
            'remarks'      => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($payload, $lease) {
            
            // 2. 将金额转为分
            $amountCents = (int) round($payload['amount_due'] * 100);

            // 3. 生成单号 (建议给手动账单一个前缀或通用标识)
            $newInvoiceNo = $this->generateSequenceInvoiceNo('INV'); 

            // 4. 执行创建
            Payment::create([
                'id'           => (string) Str::ulid(),
                'tenant_id'    => $lease->tenant_id,
                'lease_id'     => $lease->id,
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
                'transaction_ref' => $payload['transaction_ref'] ?? 'CASH',
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

    public static function generateSequenceInvoiceNo($payment_type, $modelClass = Payment::class)
    {
        $type = $payment_type;
        $currentYear = now()->format('Y'); // 获取当前年份，如 2026
        $today = now()->format('Ymd');     // 完整日期用于显示
        
        // 关键点：匹配前缀只锁死到年份
        // 搜索格式类似于：INV-RENT-2026%
        $searchPrefix = "INV-{$type}-{$currentYear}";
        
        $lastInvoice = $modelClass::where('invoice_no', 'like', $searchPrefix . '%')
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

    public function uploadProof(Request $request, $id)
    {
        // 1. 找到支付记录
        $payment = UserPayment::findOrFail($id);
        
        // 2. 检查是否为 0 元账单
        $isZeroAmount = ($payment->amount_due <= 0);

        // 3. 如果不是 0 元，则强制验证文件；如果是 0 元，则文件可选
        $rules = [
            'transaction_ref' => 'nullable|string|max:255',
        ];
        
        if (!$isZeroAmount) {
            $rules['attachment'] = 'required|image|mimes:jpg,jpeg,png|max:2048';
        } else {
            $rules['attachment'] = 'nullable|image|mimes:jpg,jpeg,png|max:2048';
        }

        $request->validate($rules);

        // 4. 处理文件逻辑
        $path = $payment->attachment; // 默认为原有的路径（如果有）

        if ($request->hasFile('attachment')) {
            if ($payment->attachment) {
                Storage::disk('local')->delete($payment->attachment);
            }
            $path = $request->file('attachment')->store('receipts', 'local');
        } elseif ($isZeroAmount && !$payment->attachment) {
            // 如果是 0 元且用户没上传，给一个特殊标识或留空
            $path = 'system/zero_amount_auto_approved'; 
        }

        // 5. 更新状态
        // 如果是 0 元，状态直接设为 'paid'，否则设为 'pending'
        $newStatus = $isZeroAmount ? 'paid' : 'pending';

        $packageDetails = DB::table('ref_code_packages')
            ->where('ref_code', $payment->ref_code)
            ->first();

        $startDate = null;
        $endDate = null;

        $startDate = now();
        $endDate = ($packageDetails->price_mode === 'monthly') 
                    ? $startDate->copy()->addMonth()
                    : $startDate->copy()->addYear();

        $userManagement = UserManagement::where('user_id', $payment->user_id)->first();

        $userManagement->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $payment->update([
            'attachment' => $path,
            'transaction_ref' => $request->transaction_ref,
            'status' => $newStatus,
        ]);

        // 更新管理状态
        UserManagement::where('user_id', $payment->user_id)->update([
            'subscription_status' => ($newStatus === 'paid') ? 'active' : 'pending'
        ]);

        $message = $isZeroAmount ? 'Subscription activated successfully!' : 'Receipt uploaded! Please wait for admin approval.';
        return back()->with('success', $message);
    }
}
