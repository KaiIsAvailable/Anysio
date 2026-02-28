<?php

namespace App\Http\Controllers;

use App\Models\Owners;
use App\Models\User;
use App\Models\Lease;
use App\Models\Room;
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
        $User_id = Auth::user()->id;

        // Use ->count() to get a single number for each
        $ownersCount = Owners::where('user_id', $User_id)->count();

        $tenantsCount = Tenants::whereHas('leases.room.owner', function ($query) use ($User_id) {
            $query->where('user_id', $User_id);
        })->count();

        $roomsCount = Room::whereHas('owner', function ($query) use ($User_id) {
            $query->where('user_id', $User_id);
        })->count();

        $leasesCount = Lease::whereHas('room.owner', function ($query) use ($User_id) {
            $query->where('user_id', $User_id);
        })->count();

        // Data for the Pie Chart (Grouping by room status)
        $roomStatusStats = Room::whereHas('owner', function ($query) use ($User_id) {
                $query->where('user_id', $User_id);
            })
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status'); 

        $payments = Payment::with('tenant')
            ->whereHas('tenant.owner', function ($query) use ($User_id) {
                $query->where('user_id', $User_id);
            })
        ->whereDate('created_at', now()->today()) 
        ->latest()
        ->limit(5)
        ->get();

        return view('adminSide.owners.dashboard', compact(
            'ownersCount', 'tenantsCount', 'roomsCount', 'leasesCount', 'roomStatusStats', 'payments'
        ));
    }
}