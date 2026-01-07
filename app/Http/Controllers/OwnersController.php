<?php

namespace App\Http\Controllers;

use App\Models\Owners; // Ensure this matches your filename in app/Models
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $validatedData = $request->validate([
            'user_id'             => 'required|exists:users,id|unique:owners,user_id',
            'company_name'        => 'nullable|string|max:255',
            'ic_number'           => 'nullable|string|max:20',
            'phone'               => 'required|string|max:20',
            'referred_by'         => 'nullable|string|max:255',
            'gender'              => 'required|string|in:Male,Female',
            'subscription_status' => 'nullable|string|in:Active,Inactive',
            'discount_rate'       => 'nullable|numeric',
            'usage_count'         => 'nullable|integer'
        ]);

        // ACTUAL CREATION LOGIC
        Owners::create($validatedData);

        return redirect()->route('admin.owners.index')->with('success', 'Owner created successfully.');
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
        $owner->delete();
        return redirect()->route('admin.owners.index')->with('success', 'Owner deleted successfully.');
    }
}