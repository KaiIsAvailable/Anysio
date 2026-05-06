<?php

namespace App\Http\Controllers;

use App\Models\Owners;
use App\Models\User;
use App\Models\Lease;
use App\Models\Room;
use App\Models\Unit;
use App\Models\Property;
use App\Models\Tenants;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;

class OwnersController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $query = Owners::with('user');

        if (!Gate::allows('super-admin')) {
            if ($userId) {
                $query->where('agent_id', $userId);
            } else {
                // 如果该用户在 owners 表里竟然没有记录，为了安全，让他什么都搜不到
                $query->whereRaw('1 = 0'); 
            }
        }

        // Search by User Name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        // Sorting
        $sort = $request->get('sort');
        switch ($sort) {
            case 'name_asc':
            case 'name_desc':
                $direction = ($sort === 'name_asc') ? 'asc' : 'desc';
                $query->join('users', 'owners.user_id', '=', 'users.id')
                      ->select('owners.*') 
                      ->orderBy('users.name', $direction);
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'asc');
                break;
        }

        $owners = $query->paginate(5)->withQueryString();
        return view('adminSide.owners.index', compact('owners'));
    }

    public function create()
    {
        $users = User::where('role', 'owner')->whereDoesntHave('owner')->get(); 
        return view('adminSide.owners.create', compact('users'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'random_email' => $request->has('random_email') ? true : false,
        ]);

        // 1. Validate the incoming data (excluding user_id since we create it here)
        $validatedData = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => $request->random_email ? 'nullable' : 'required|email|unique:users,email',
            'random_email' => 'required|boolean',
            'company_name' => 'nullable|string|max:255',
            'ic_number'    => 'nullable|string|max:20',
            'phone'        => 'required|string|max:20',
            'gender'       => 'required|string|in:Male,Female',
        ]);

        if ($validatedData['random_email']) {
            $validatedData['email'] = Str::random(10) . '@example.com';
            $validatedData['password'] = Hash::make(Str::random(10));
        }

        try {
            // 2. Start a transaction to ensure both records are created safely
            DB::beginTransaction();

            // 3. Create the User record with random Email and Password
            $user = User::create([
                'name'     => $validatedData['name'],
                'email'    => $validatedData['email'],
                'password' => $validatedData['password'] ?? Hash::make('defaultPassword123'),
                'role'     => 'owner',
            ]);

            // 4. Create the Owner record using the new $user->id
           $ownerData = [
                'user_id'      => $user->id,
                'company_name' => $validatedData['company_name'],
                'ic_number'    => $validatedData['ic_number'],
                'phone'        => $validatedData['phone'],
                'gender'       => $validatedData['gender'],
            ];

            if (Auth::user()->role === 'agentAdmin') {
                $ownerData['agent_id'] = Auth::id();
            }

            Owners::create($ownerData);

            DB::commit();

            return redirect()->route('admin.owners.index')->with('success', 'Owner and User account created successfully.');

        } catch (\Exception $e) {
            // Rollback if anything goes wrong
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create owner: ' . $e->getMessage()]);
        }
    }

    public function edit(Owners $owner)
    {
        return view('adminSide.owners.edit', compact('owner'));
    }

    public function update(Request $request, Owners $owner)
    {
        $validatedData = $request->validate([
            // Use the $owner->id to ignore the current record in the unique check
            'user_id'             => 'required|exists:users,id|unique:owners,user_id,' . $owner->id,
            'email'               => 'required|email|unique:users,email,' . $owner->id,
            'company_name'        => 'nullable|string|max:255',
            'ic_number'           => 'nullable|string|max:20',
            'phone'               => 'required|string|max:20',
            'referred_by'         => 'nullable|string|max:255',
            'gender'              => 'required|string|in:Male,Female',
            'subscription_status' => 'nullable|string|in:Active,Inactive',
            'discount_rate'       => 'nullable|numeric',
            'usage_count'         => 'nullable|integer'
        ]);

        // ACTUAL UPDATE LOGIC
        $owner->update($validatedData);

        return redirect()->route('admin.owners.index')->with('success', 'Owner updated successfully.');
    }

    public function destroy(Owners $owner)
    {
        $owner->user()->delete(); 
        $owner->delete();
        return redirect()->route('admin.owners.index')->with('success', 'Owner deleted successfully.');
    }

    public function show(Owners $owner)
    {
        $owner->load(['user']);

        return view('adminSide.owners.details', compact('owner'));
    }

    public function dashboard()
    {
        $user = Auth::user();

        // 先找到当前登录用户对应的 Owner 业务记录
        $ownerProfile = Owners::where('user_id', $user->id)->first();

        // 如果该用户甚至不是一个登记的业主，直接返回 0
        if (!$ownerProfile) {
            return view('adminSide.owners.dashboard', [
                'ownersCount' => 0, 'tenantsCount' => 0, 'roomsCount' => 0, 
                'leasesCount' => 0, 'roomStatusStats' => collect(), 'payments' => collect()
            ]);
        }

        $owner_id = $ownerProfile->id; // 获取 Owners 表的 ULID

        // 1. 统计租客 (通过房间的 owner_id 匹配)
        $tenantsCount = Tenants::whereHas('leases.room', function ($query) use ($owner_id) {
            $query->where('owner_id', $owner_id);
        })->count();

        // 2. 统计房间
        $roomsCount = Room::whereHas('unit', function ($query) use ($owner_id) {
            $query->where('owner_id', $owner_id);
        })->count();

        // 3. 统计租约
        $leasesCount = Lease::where(function ($query) use ($owner_id) {
            // 1. 如果租的是 Room，通过 Room -> Unit -> Owner 找
            $query->whereHasMorph('leasable', [Room::class], function ($q) use ($owner_id) {
                $q->whereHas('unit', function ($sq) use ($owner_id) {
                    $sq->where('owner_id', $owner_id);
                });
            })
            // 2. 或者：如果租的是 Unit，通过 Unit -> Owner 找
            ->orWhereHasMorph('leasable', [Unit::class], function ($q) use ($owner_id) {
                $q->where('owner_id', $owner_id);
            })
            // 3. 或者：如果租的是 Property，通过 Property -> Owner 找
            ->orWhereHasMorph('leasable', [Property::class], function ($q) use ($owner_id) {
                $q->where('owner_id', $owner_id);
            });
        })
        ->where('status', 'active') // 记得只算 active 的，这才是占用名额的
        ->count();

        // 4. 饼图：房间状态
        $roomStatusStats = Room::whereHas('unit', function ($query) use ($owner_id) {
            $query->where('owner_id', $owner_id);
        })
        ->select('status', DB::raw('count(*) as total'))
        ->groupBy('status')
        ->pluck('total', 'status');

        // 5. 支付动态
        $payments = Payment::with('tenant')
            ->whereHas('tenant.leases.room', function ($query) use ($owner_id) {
                $query->where('owner_id', $owner_id);
            })
            ->whereDate('created_at', now())
            ->latest()
            ->limit(5)
            ->get();

        return view('adminSide.owners.dashboard', compact(
            'tenantsCount', 'roomsCount', 'leasesCount', 'roomStatusStats', 'payments'
        ));
    }
}