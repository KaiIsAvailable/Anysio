<?php

namespace App\Http\Controllers;

use App\Models\{Owners, User, Lease, Room, Unit, Property, Tenants, Payment, UserManagement};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use App\WorldCountries;
use App\Http\Requests\Owner\{StoreOwnerRequest, UpdateOwnerRequest};

class OwnersController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::denies('agent-admin') && Gate::denies('owner.tab')) {
            return view('errors.403');
        }

        $userId = get_effective_user();
        $users = UserManagement::with('user')->get()->pluck('user');
        $query = Owners::with('user');

        if (!Gate::allows('super-admin')) {
            if ($userId) {
                $query->where('agent_id', $userId->id);
            } else {
                // 如果该用户在 owners 表里竟然没有记录，为了安全，让他什么都搜不到
                $query->whereRaw('1 = 0');
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('users.name', 'like', '%' . $search . '%')
                        ->orWhere('users.email', 'like', '%' . $search . '%');
                })
                    ->orWhere('owners.company_name', 'like', '%' . $search . '%')
                    ->orWhere('owners.phone', 'like', '%' . $search . '%')
                    ->orWhere('owners.ic_number', 'like', '%' . $search . '%')
                    ->orWhere('owners.gender', 'like', '%' . $search . '%');
            });
        }

        // Sorting
        $sort = $request->get('sort');
        if ($sort) {
            if (str_starts_with($sort, 'n_')) {
                $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
                $query->join('users', 'owners.user_id', '=', 'users.id')
                    ->select('owners.*')
                    ->orderBy('users.name', $direction);
            } elseif (str_starts_with($sort, 'c_')) {
                $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
                $query->orderBy('owners.company_name', $direction);
            } elseif (str_starts_with($sort, 'jd_')) {
                $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
                $query->orderBy('owners.created_at', $direction);
            } else {
                $query->orderBy('owners.created_at', 'asc');
            }
        } else {
            $query->orderBy('owners.created_at', 'asc');
        }

        $owners = $query->paginate(10)->withQueryString()->onEachSide(1);
        return view('adminSide.owners.index', compact('owners', 'users'));
    }

    public function create()
    {
        if (Gate::denies('agent-admin') && Gate::denies('owner.create')) {
            return view('errors.403');
        }

        $users = User::where('role', 'owner')->whereDoesntHave('owner')->get();
        $worldCountries = WorldCountries::worldCountries();
        return view('adminSide.owners.create', compact('users', 'worldCountries'));
    }

    public function store(StoreOwnerRequest $request)
    {
        if (Gate::denies('agent-admin') && Gate::denies('owner.create')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        $validatedData = $request->validated();

        $request->merge([
            'random_email' => $request->has('random_email') ? true : false,
        ]);

        if ($validatedData['random_email']) {
            $validatedData['email'] = Str::random(10) . '@example.com';
            $validatedData['password'] = Hash::make(Str::random(10));
        }

        try {
            // Start a transaction to ensure both records are created safely
            DB::beginTransaction();

            // Create the User record with random Email and Password
            $user = User::create([
                'name'     => $validatedData['name'],
                'email'    => $validatedData['email'],
                'password' => $validatedData['password'] ?? Hash::make('defaultPassword123'),
                'role'     => 'owner',
            ]);

            // Create the Owner record using the new $user->id
            $ownerData = [
                'user_id'      => $user->id,
                'company_name' => $validatedData['company_name'],
                'ic_number'    => $validatedData['ic_number'],
                'phone'        => $validatedData['phone'],
                'gender'       => $validatedData['gender'],
                'address'      => $validatedData['address'] ?? null,
                'postcode'     => $validatedData['postcode'] ?? null,
                'city'         => $validatedData['city'] ?? null,
                'state'        => $validatedData['state'] ?? null,
            ];

            $effectiveUser = get_effective_user();
            $ownerData['agent_id'] = $effectiveUser ? $effectiveUser->id : Auth::id();

            Owners::create($ownerData);

            DB::commit();

            return redirect()->route('admin.owners.index')->with('success', 'Owner and User account created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create owner: ' . $e->getMessage()]);
        }
    }

    public function edit(Owners $owner)
    {
        if (Gate::denies('agent-admin') && Gate::denies('owner.edit')) {
            return view('errors.403');
        }

        $worldCountries = WorldCountries::worldCountries();
        return view('adminSide.owners.edit', compact('owner', 'worldCountries'));
    }

    public function update(UpdateOwnerRequest $request, Owners $owner)
    {

        if (Gate::denies('agent-admin') && Gate::denies('owner.edit')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        // 1. Update the associated User's name and email
        if ($owner->user) {
            $userData = [
                'name'  => $request->input('name'),
                'email' => $request->input('email'),
            ];

            $userData['email_verified_at'] = $owner->user->email === $request->input('email')
                ? $owner->user->email_verified_at
                : null;

            $owner->user->update($userData);
        }

        // 2. Update the Owner's specific details (including the new address fields)
        $owner->update($request->only([
            'company_name',
            'ic_number',
            'phone',
            'gender',
            'address',
            'postcode',
            'city',
            'state'
        ]));

        return redirect()->route('admin.owners.index')->with('success', 'Owner updated successfully.');
    }

    public function destroy(Owners $owner)
    {
        if (Gate::denies('owner-admin') && Gate::denies('owner.delete')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }


        if ($owner->user) {
            $owner->user->update([
                'status' => 'inactive'
            ]);
        }

        return redirect()->route('admin.owners.index')->with('success', 'Owner marked as inactive successfully.');
    }

    public function restore($id)
    {
        if (Gate::denies('owner-admin') && Gate::denies('owner.delete')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        // Find the owner including inactive ones (just in case your global scopes filter them out)
        $owner = Owners::with('user')->findOrFail($id);

        if ($owner->user) {
            $owner->user->update([
                'status' => 'active' // Change back to active
            ]);
        }

        return redirect()->route('admin.owners.index')->with('success', 'Owner restored/activated successfully.');
    }

    public function show(Owners $owner)
    {
        if (Gate::denies('agent-admin') && Gate::denies('owner.show')) {
            return view('errors.403');
        }

        // Owner profile
        $owner->load('user');

        // Property / Unit 的 owner_id 都储存 users.id
        $ownerUserId = $owner->user_id;

        // 1. 直接属于这个 Owner 的 Properties
        $properties = Property::where('owner_id', $ownerUserId)
            ->orderBy('name')
            ->get();

        // 2. 直接属于这个 Owner 的 Units
        $units = Unit::with('property')
            ->where('owner_id', $ownerUserId)
            ->orderBy('unit_no')
            ->get();

        // 3. Room 本身没有 owner_id
        //    所以通过 Unit 的 owner_id 判断 Room 属于哪个 Owner
        $rooms = Room::with('unit.property')
            ->whereHas('unit', function ($query) use ($ownerUserId) {
                $query->where('owner_id', $ownerUserId);
            })
            ->orderBy('room_no')
            ->get();

        return view('adminSide.owners.details', compact(
            'owner',
            'properties',
            'units',
            'rooms'
        ));
    }

    public function dashboard()
    {
        $user = Auth::user();

        // 先找到当前登录用户对应的 Owner 业务记录
        $ownerProfile = Owners::where('user_id', $user->id)->first();

        // 如果该用户甚至不是一个登记的业主，直接返回 0
        if (!$ownerProfile) {
            return view('adminSide.owners.dashboard', [
                'ownersCount' => 0,
                'tenantsCount' => 0,
                'roomsCount' => 0,
                'leasesCount' => 0,
                'roomStatusStats' => collect(),
                'payments' => collect()
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
            'tenantsCount',
            'roomsCount',
            'leasesCount',
            'roomStatusStats',
            'payments'
        ));
    }
}
