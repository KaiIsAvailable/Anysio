<?php

namespace App\Http\Controllers;

use App\Models\Tenants;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Tenants::with('user');

        // Search
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        // Sorting (default to null, which we handle as oldest)
        $sort = $request->get('sort');

        switch ($sort) {
            case 'name_asc':
                $query->join('users', 'tenants.user_id', '=', 'users.id')
                      ->select('tenants.*') // Avoid column collision
                      ->orderBy('users.name', 'asc');
                break;
            case 'name_desc':
                $query->join('users', 'tenants.user_id', '=', 'users.id')
                      ->select('tenants.*')
                      ->orderBy('users.name', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                // Default: Oldest first
                $query->orderBy('created_at', 'asc');
                break;
        }

        $tenants = $query->paginate(5)->withQueryString();

        return view('adminSide.tenants.index', compact('tenants'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('adminSide.tenants.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            // User details
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            
            // Tenant details
            'phone' => 'required|string|max:20',
            'ic_number' => 'nullable|string|max:20|required_without:passport', // Either IC or Passport must be present ideally, or at least one
            'passport' => 'nullable|string|max:20|required_without:ic_number',
            'nationality' => 'required|string|max:100',
            'gender' => 'required|string|in:Male,Female',
            'occupation' => 'nullable|string|max:100',
            'ic_photo_path' => 'nullable|image|max:2048|mimes:jpeg,png,jpg,gif',
        ]);

        // 1. Create User
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role' => 'tenant',
        ]);

        // 2. Prepare Tenant Data
        $data = $request->except(['ic_photo_path', 'name', 'email', 'password', 'password_confirmation']);
        $data['user_id'] = $user->id;

        if ($request->hasFile('ic_photo_path')) {
            $path = $request->file('ic_photo_path')->store('tenants/ic_photos', 'public');
            $data['ic_photo_path'] = $path;
        }

        // 3. Create Tenant
        Tenants::create($data);

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant and User created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tenants $tenant)
    {
        // No need to fetch users list as the user field is disabled/readonly
        return view('adminSide.tenants.edit', compact('tenant'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tenants $tenant)
    {
        $request->validate([
            // User details
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $tenant->user_id,
            'password' => 'nullable|string|min:8|confirmed',

            // Tenant details
            'phone' => 'required|string|max:20',
            'ic_number' => 'nullable|string|max:20|required_without:passport',
            'passport' => 'nullable|string|max:20|required_without:ic_number',
            'nationality' => 'required|string|max:100',
            'gender' => 'required|string|in:Male,Female',
            'occupation' => 'nullable|string|max:100',
            'ic_photo_path' => 'nullable|image|max:2048|mimes:jpeg,png,jpg,gif',
        ]);

        // 1. Update User
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $userData['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        $tenant->user->update($userData);

        // 2. Update Tenant
        $data = $request->except(['ic_photo_path', 'name', 'email']);

        if ($request->hasFile('ic_photo_path')) {
            // Delete old photo if exists? For now just overwrite reference.
            $path = $request->file('ic_photo_path')->store('tenants/ic_photos', 'public');
            $data['ic_photo_path'] = $path;
        }

        $tenant->update($data);

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant and User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tenants $tenant)
    {
        $tenant->delete();

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant deleted successfully.');
    }
}
