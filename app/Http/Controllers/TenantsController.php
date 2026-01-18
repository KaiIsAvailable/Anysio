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
        $query = Tenants::with(['user', 'emergencyContacts']);

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
        // 1. Pre-processing for Random Email
        if ($request->has('random_email') && $request->random_email == '1') {
            $request->merge(['email' => 'tenant_' . time() . '_' . Str::random(5) . '@anysio.local']);
        }

        // 2. Validation
        $request->validate([
            // User details
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            
            // Tenant details
            'phone' => 'required|string|max:20',
            'identity_type' => 'required|in:ic,passport',
            'ic_number' => 'nullable|string|max:20|required_if:identity_type,ic',
            'passport' => 'nullable|string|max:20|required_if:identity_type,passport',
            'nationality' => 'required|string|max:100',
            'gender' => 'required|string|in:Male,Female',
            'occupation' => 'nullable|string|max:100',
            'ic_photo_path' => 'nullable|image|max:2048|mimes:jpeg,png,jpg,gif',
        ]);

        // 3. Create User (Auto-generate Password)
        $msgPassword = '';
        $password = Str::random(10); // Generate nice random password
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => 'tenant',
        ]);

        // 4. Prepare Tenant Data
        $data = $request->except(['ic_photo_path', 'name', 'email', 'password', 'password_confirmation', 'random_email', 'identity_type']);
        $data['user_id'] = $user->id;

        // Ensure clean data based on identity type
        if ($request->identity_type === 'ic') {
            $data['passport'] = null; // Clear passport if IC selected (though UI hides it)
        } else {
            $data['ic_number'] = null; // Clear IC if Passport selected
        }

        if ($request->hasFile('ic_photo_path')) {
            $file = $request->file('ic_photo_path');
            $filename = time() . '_' . $file->getClientOriginalName();
            $destinationPath = resource_path('views/adminSide/tenants/ic_path');
            
            // Ensure directory exists
            if (!\Illuminate\Support\Facades\File::exists($destinationPath)) {
                \Illuminate\Support\Facades\File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $filename);
            $data['ic_photo_path'] = 'resources/views/adminSide/tenants/ic_path/' . $filename;
        }

        // 5. Create Tenant
        $tenant = Tenants::create($data);

        // 6. Handle Emergency Contacts
        if ($request->has('emergency_contacts') && is_array($request->emergency_contacts)) {
            foreach ($request->emergency_contacts as $contact) {
                // Skip if name or phone is empty
                if (empty($contact['name']) || empty($contact['phone'])) {
                    continue;
                }

                $tenant->emergencyContacts()->create([
                    'name' => $contact['name'],
                    'phone' => $contact['phone'],
                    'relationship' => $contact['relationship'] ?? 'Friend',
                ]);
            }
        }

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant created successfully. Password: ' . $password);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tenants $tenant)
    {
        // No need to fetch users list as the user field is disabled/readonly
        $tenant->load('emergencyContacts'); // Eager load contacts
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
            // Password update removed as requested "Edit no need edit password"

            // Tenant details
            'phone' => 'required|string|max:20',
            'identity_type' => 'required|in:ic,passport',
            'ic_number' => 'nullable|string|max:20|required_if:identity_type,ic',
            'passport' => 'nullable|string|max:20|required_if:identity_type,passport',
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
        // Password update logic removed

        $tenant->user->update($userData);

        // 2. Update Tenant
        $data = $request->except(['ic_photo_path', 'name', 'email', 'identity_type', 'emergency_contacts']);

        // Clean data based on identity type
        if ($request->identity_type === 'ic') {
            $data['passport'] = null;
        } else {
            $data['ic_number'] = null;
        }

        if ($request->hasFile('ic_photo_path')) {
            // Delete old photo if exists
            if ($tenant->ic_photo_path) {
                // Assuming database stores "resources/views/adminSide/tenants/ic_path/filename"
                $oldPath = base_path($tenant->ic_photo_path);
                if (\Illuminate\Support\Facades\File::exists($oldPath)) {
                    \Illuminate\Support\Facades\File::delete($oldPath);
                } 
                elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($tenant->ic_photo_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($tenant->ic_photo_path);
                }
            }
            
            $file = $request->file('ic_photo_path');
            $filename = time() . '_' . $file->getClientOriginalName();
            $destinationPath = resource_path('views/adminSide/tenants/ic_path');
             
            // Ensure directory exists
            if (!\Illuminate\Support\Facades\File::exists($destinationPath)) {
                \Illuminate\Support\Facades\File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $filename);
            $data['ic_photo_path'] = 'resources/views/adminSide/tenants/ic_path/' . $filename;
        }

        $tenant->update($data);

        // 3. Handle Emergency Contacts (Update/Delete/Create)
        if ($request->has('emergency_contacts') && is_array($request->emergency_contacts)) {
            foreach ($request->emergency_contacts as $contact) {
                $name = trim($contact['name'] ?? ''); 
                $isDelete = !empty($contact['_delete']);

                // Existing contact (has ID)
                if (!empty($contact['id'])) {
                    $existingContact = $tenant->emergencyContacts()->find($contact['id']);
                    
                    if (!$existingContact) continue;

                    if ($isDelete) {
                        $existingContact->delete();
                    } else {
                         // Only update if name and phone are valid
                        if ($name === '') continue;
                        
                        $existingContact->update([
                            'name' => $name,
                            'phone' => $contact['phone'] ?? $existingContact->phone,
                            'relationship' => $contact['relationship'] ?? $existingContact->relationship,
                        ]);
                    }
                    continue;
                }

                // New contact
                if ($isDelete || $name === '') continue; // Skip if marked delete or empty name

                $tenant->emergencyContacts()->create([
                    'name' => $name,
                    'phone' => $contact['phone'] ?? '',
                    'relationship' => $contact['relationship'] ?? '',
                ]);
            }
        }

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant and User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tenants $tenant)
    {
        // Delete IC Photo if exists
        if ($tenant->ic_photo_path) {
            $path = base_path($tenant->ic_photo_path);
            if (\Illuminate\Support\Facades\File::exists($path)) {
                \Illuminate\Support\Facades\File::delete($path);
            }
        }

        $tenant->delete();

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant deleted successfully.');
    }

    public function dashboard()
    {
        return view('adminSide.tenants.dashboard');
    }

    public function show(Tenants $tenant)
    {
        $tenant->load(['emergencyContacts', 'user', 'payments' => function($q) {
            $q->latest(); // Latest on top
        }]);
        return view('adminSide.tenants.show', compact('tenant'));
    }

    public function showIcPhoto($filename)
    {
        $path = resource_path('views/adminSide/tenants/ic_path/' . $filename);

        if (!\Illuminate\Support\Facades\File::exists($path)) {
            abort(404);
        }

        $file = \Illuminate\Support\Facades\File::get($path);
        $type = \Illuminate\Support\Facades\File::mimeType($path);

        $response = \Illuminate\Support\Facades\Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }
}
