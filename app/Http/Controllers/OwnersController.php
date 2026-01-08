<?php

namespace App\Http\Controllers;

use App\Models\Owners; // Ensure this matches your filename in app/Models
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OwnersController extends Controller
{
    public function index(Request $request)
    {
        $query = Owners::with('user');

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
        // 1. Validate the incoming data (excluding user_id since we create it here)
        $validatedData = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            'company_name' => 'nullable|string|max:255',
            'ic_number'    => 'nullable|string|max:20',
            'phone'        => 'required|string|max:20',
            'gender'       => 'required|string|in:Male,Female',
        ]);

        try {
            // 2. Start a transaction to ensure both records are created safely
            DB::beginTransaction();

            // 3. Create the User record with random Email and Password
            $user = User::create([
                'name'     => $validatedData['name'],
                'email'    => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role'     => 'owner',
            ]);

            // 4. Create the Owner record using the new $user->id
            Owners::create([
                'user_id'      => $user->id,
                'company_name' => $validatedData['company_name'],
                'ic_number'    => $validatedData['ic_number'],
                'phone'        => $validatedData['phone'],
                'gender'       => $validatedData['gender'],
            ]);

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
}