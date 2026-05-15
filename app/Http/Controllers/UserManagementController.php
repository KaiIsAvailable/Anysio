<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserManagement;
use App\Models\UserPayment;
use App\Models\RefCodePackage; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = UserManagement::query()
            ->join('users', 'user_management.user_id', '=', 'users.id')
            
            // 这里的关键：确保 'ref_code_packages.id' 是该表的主键
            // 如果你的包表主键不叫 id，请改为实际的名字
            ->leftJoin('ref_code_packages', function($join) {
                $join->on('user_management.package_id', '=', 'ref_code_packages.id');
            })
            
            ->select(
                'user_management.*',
                'users.name as user_name',
                'users.email as user_email',
                'ref_code_packages.ref_code as applied_ref_code', // 确保这个字段名在表里存在
            );

        $pendingPayments = UserPayment::with('user')
            ->where('status', 'pending') 
            ->whereNotNull('attachment')
            ->latest()
            ->get();
        //dd($pendingPayments->toArray());

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                ->orWhere('users.email', 'like', "%{$search}%")
                ->orWhere('ref_code_packages.ref_code', 'like', "%{$search}%");
            });
        }

        // 注意：latest() 里的字段要写全表名
        $userManagement = $query->orderBy('user_management.created_at', 'desc')->paginate(5);
        //dd($userManagement->toArray());
        return view('adminSide.userManagement.index', compact('userManagement', 'pendingPayments'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // 这里的变量名和你的逻辑保持一致
        $refCodes = RefCodePackage::all(); 
        return view('adminSide.userManagement.create', compact('refCodes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. 验证规则 (保持不变)
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required_without:random_email|nullable|email|unique:users,email',
            'role_type' => 'required|in:admin,owner,agent',
        ];

        if ($request->role_type !== 'admin') {
            $rules['referred_by'] = 'required|exists:ref_code_packages,ref_code';
        } else {
            $rules['referred_by'] = 'nullable';
        }

        $request->validate($rules, [
            'referred_by.required' => 'As an Owner or Agent, a Reference Code is mandatory.',
            'referred_by.exists' => 'The provided Reference Code is invalid.',
            'email.required_without' => 'Please provide an email or select the random email option.'
        ]);

        DB::beginTransaction();
        try {
            $finalPackageId = null;
            $sourcePackage = null; // 用于存储推荐人的包信息以继承价格

            if ($request->role_type !== 'admin' && $request->referred_by) {
                $sourcePackage = RefCodePackage::where('ref_code', $request->referred_by)->first();
                if ($sourcePackage) {
                    $finalPackageId = $sourcePackage->id;
                }

                if (!$finalPackageId) {
                    return back()->withErrors(['referred_by' => 'Invalid Reference Code.'])->withInput();
                }
            }

            // 3. 处理 Email & Password
            $email = $request->has('random_email') 
                ? strtolower(Str::random(8)) . '@system.com' 
                : $request->email;
            $plainPassword = Str::random(10); 

            // 5. 写入 User 表
            $user = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'role' => $request->role_type, 
            ]);

            $roleMapping = ['admin' => 'admin', 'owner' => 'ownerAdmin', 'agent' => 'agentAdmin'];
            $pmsRole = $roleMapping[$request->role_type] ?? 'admin'; 

            // 7. 写入 UserManagement 表
            $userMgnt = UserManagement::create([
                'user_id'             => $user->id,
                'referred_by'         => $finalPackageId, 
                'subscription_status' => 'active',
                'discount_rate'       => 0,
                'usage_count'         => 0,
                'role'                => $pmsRole,
            ]);

            // ================== 【新增逻辑 1：为新用户创建专属 Ref Code】 ==================
            $newRefCode = Str::slug($request->name) . '_' . Str::upper(Str::random(4));
            
            $userMgnt = $userMgnt->fresh();
            
            RefCodePackage::create([
                'id'                     => (string) Str::ulid(),
                'ref_code'               => $newRefCode,
                'user_mgnt_id'           => $userMgnt->id,
                'is_official'            => 0,
                'ref_installation_price' => $sourcePackage ? $sourcePackage->ref_installation_price : 0,
                'ref_monthly_price'      => $sourcePackage ? $sourcePackage->ref_monthly_price : 0,
            ]);

            // ================== 【新增逻辑 2：奖励推荐人】 ==================
            if ($sourcePackage && $sourcePackage->user_mgnt_id) {
                $referrer = UserManagement::find($sourcePackage->user_mgnt_id);
                if ($referrer) {
                    $referrer->usage_count += 1;
                    
                    $calculatedDiscount = $referrer->usage_count * 10;
                    $referrer->discount_rate = min($calculatedDiscount, 100);
                    
                    $referrer->save();
                }
            }
            // ===========================================================================

            DB::commit();

            return redirect()->route('admin.userManagement.index')->with('status', [
                'message'  => 'User created successfully!',
                'email'    => $email,
                'password' => $plainPassword,
                'new_ref_code' => $newRefCode // 也可以传回前端展示
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Database Error: ' . $e->getMessage()])->withInput();
        }
    }
    /**
     * Display the specified resource.
     */
    // 参数名一定要叫 $userManagement
    public function show(string $userManagement)
    {
        // 复用 index 的关联逻辑，确保 details 页面能拿到 name, email 和 ref_code
        $user = UserManagement::query()
            ->join('users', 'user_management.user_id', '=', 'users.id')
            ->leftJoin('ref_code_packages', 'user_management.package_id', '=', 'ref_code_packages.id')
            
            ->select(
                'user_management.*',
                'users.name as user_name',
                'users.email as user_email',
                'ref_code_packages.ref_code as applied_ref_code',
            )
            // 关键点：只查当前点击的这一条记录
            ->where('user_management.id', $userManagement)
            ->firstOrFail();

        return view('adminSide.userManagement.details', [
            'userManagement' => $user
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // 找到 UserManagement 记录并关联加载 User
        $userMgnt = UserManagement::with('user')->findOrFail($id);
        
        return view('adminSide.userManagement.edit', compact('userMgnt'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $userMgnt = UserManagement::findOrFail($id);
        $user = $userMgnt->user; // 确保 UserManagement 模型里有 public function user() 关联

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role_type' => 'required', // 对应你前端的 name="role_type"
            'pms_role' => 'required',  // 对应你前端的 name="pms_role"
            'subscription_status' => 'required|in:active,inactive',
        ]);

        // 1. 更新 User 表
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role_type, // 使用 role_type
        ]);

        // 2. 更新 UserManagement 表
        $userMgnt->update([
            'role' => $request->pms_role, // 使用 pms_role
            'subscription_status' => $request->subscription_status,
        ]);

        return redirect()->route('admin.userManagement.index')
                        ->with('success', 'User updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::transaction(function () use ($id) {
                // 找到记录（如果之前没删过）
                $userMgnt = UserManagement::findOrFail($id);
                $user = $userMgnt->user; 

                // 1. 软删除管理记录
                // 只要模型里用了 SoftDeletes Trait，这一行就是软删除
                $userMgnt->delete();

                // 2. 处理关联的登录账号
                if ($user) {
                    // 如果 User 模型也用了 SoftDeletes，这里也是软删除
                    // 如果 User 模型没用 SoftDeletes，这里就是永久删除（小心！）
                    $user->delete();
                }
            });

            return redirect()->route('admin.userManagement.index')
                            ->with('success', 'User moved to trash successfully.');

        } catch (\Exception $e) {
            return redirect()->back()
                            ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function displayReceipt($filename)
    {
        // 因为 $filename 现在是 "receipts/MuEX...png"
        // storage_path('app/private') 后面直接接上这个 $filename 即可
        $path = storage_path('app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . $filename);

        if (!file_exists($path)) {
            // 如果图片不显示，取消下面这一行的注释来检查生成的绝对路径是否正确
            // dd($path); 
            abort(404);
        }

        return response()->file($path);
    }

    // Approve 处理
    public function approve(Request $request, UserPayment $payment)
    {
        // 1. 验证输入（假设审批时管理员可以修正或确认实际收到多少钱）
        $payload = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'received_via' => ['required', 'string'], // MATCHES HTML <select name="received_via">
            'transaction_ref' => ['nullable', 'string'], // 接收 Modal 里的单号
            'remarks' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($payload, $payment) {
            // 2. 转换金额为“分”(Cent)，避免浮点运算错误
            $totalInputCents = (int) round($payload['amount_paid'] * 100);
            $dueCents = $payment->getRawOriginal('amount_due'); // 拿到数据库存的原始金额

            $underPaidCents = 0;
            $overPaidCents = 0;
            $actualAppliedCents = $totalInputCents;

            // 3. 处理：给少了 (Underpaid)
            if ($totalInputCents < $dueCents) {
                $underPaidCents = $dueCents - $totalInputCents;
                $subscriptionType = 'SUBSCRIPTION';
                $newInvoiceNo = PaymentsController::generateSequenceInvoiceNo($subscriptionType, UserPayment::class);
                
                // 自动创建一个新的账单补齐差额
                UserPayment::create([
                    'user_id'     => $payment->user_id,
                    'ref_code'    => $payment->ref_code,
                    'invoice_no'  => $newInvoiceNo, // 标记为原单的余额
                    'amount_due'  => $underPaidCents,
                    'amount_paid' => 0,
                    'status'      => 'unpaid', // 设为未付，等待用户下次支付
                    'attachment'  => null,
                    'remarks'     => $payment->remarks,
                ]);
            } 

            elseif ($totalInputCents > $dueCents) {
                $overPaidCents = $totalInputCents - $dueCents;
                $actualAppliedCents = $dueCents; // 只扣除应付的部分，剩下的存入 over_paid 字段
            }
            
            // 4. 更新当前这条支付记录
            $payment->update([
                'amount_paid' => $actualAppliedCents,
                'amount_under_paid' => $underPaidCents,
                'amount_over_paid' => $overPaidCents,
                'payment_date' => $payload['payment_date'],
                'received_via' => $payload['received_via'], // Use received_via from payload
                'approve_transaction_ref' => $payload['transaction_ref'] ?? 'CASH',
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'remarks' => $payload['remarks'],
            ]);

            // 5. 更新用户的订阅状态
            // 逻辑建议：如果给够了才设为 active，如果给少了，你可能需要根据业务决定是否激活
            if ($payment->user->user_management) {
                $payment->user->user_management->update([
                    'subscription_status' => ($underPaidCents > 0) ? 'pending' : 'active',
                ]);
            }

            return back()->with('success', 'Payment processed. ' . ($underPaidCents > 0 ? 'New balance invoice created.' : ''));
        });
    }

    // Reject 处理
    public function reject(UserPayment $payment)
    {
        DB::transaction(function () use ($payment) {
            $payment->update([
                'status' => 'rejected',
                'rejected_at' => now(),
            ]);

            if ($payment->user->user_management) {
                $payment->user->user_management->update([
                    'subscription_status' => 'inactive',
                    // 如果需要记录审批时间，也可以在这里加
                ]);
            }
        });

        return back()->with('success', 'Payment proof has been rejected.');
    }
}
