<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use App\Models\Lease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    /**
     * Display a listing of maintenance requests with search and filters
     */
    public function index(Request $request)
    {
        $query = Maintenance::with(['lease.room', 'lease.tenant.user', 'asset']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('desc', 'like', "%{$search}%")
                    ->orWhereHas('lease.tenant.user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('lease.room', function ($q) use ($search) {
                        $q->where('room_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('asset', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by paid_by
        if ($request->filled('paid_by')) {
            $query->where('paid_by', $request->paid_by);
        }

        // Sort by created_at descending (newest first)
        $maintenances = $query->orderBy('created_at', 'desc')->paginate(10);

        // Append query string to pagination links
        $maintenances->appends($request->all());

        return view('adminSide.maintenance.index', compact('maintenances'));
    }

    /**
     * Show the form for creating a new maintenance request
     */
    public function create(Request $request)
    {
        // Get all leases with room and tenant information
        $leases = Lease::with(['room', 'tenant.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Status options
        $statusOptions = ['Pending', 'Fixing', 'Resolved'];

        // Paid by options
        $paidByOptions = ['Owner', 'Tenant'];

        // Pre-select lease if provided via query param
        $selectedLeaseId = $request->query('lease_id');
        $selectedAssets = collect();

        if ($selectedLeaseId) {
            $selectedLease = Lease::with('room.assets')->find($selectedLeaseId);
            if ($selectedLease) {
                $selectedAssets = $selectedLease->room->assets;
            }
        }

        return view('adminSide.maintenance.create', compact(
            'leases',
            'statusOptions',
            'paidByOptions',
            'selectedLeaseId',
            'selectedAssets'
        ));
    }

    /**
     * Store a newly created maintenance request
     */
    public function store(Request $request)
    {
        // Validation
        $request->validate([
            'lease_id' => ['required', 'exists:leases,id'],
            'asset_id' => ['nullable', 'exists:room_assets,id'],
            'title' => ['required', 'string', 'max:255'],
            'desc' => ['required', 'string'],
            'photo_path' => ['nullable', 'image', 'max:5120', 'mimes:jpeg,png,jpg,gif'],
            'status' => ['required', 'in:Pending,Fixing,Resolved'],
            'cost' => ['required', 'numeric', 'min:0'],
            'paid_by' => ['required', 'in:Owner,Tenant'],
        ]);

        // Custom validation: verify asset belongs to lease's room if provided
        if ($request->filled('asset_id')) {
            $lease = Lease::with('room.assets')->findOrFail($request->lease_id);
            if (!$lease->room->assets->contains('id', $request->asset_id)) {
                return back()->withErrors(['asset_id' => 'Selected asset does not belong to this lease\'s room'])->withInput();
            }
        }

        // Prepare data
        $data = $request->only(['lease_id', 'asset_id', 'title', 'desc', 'status', 'paid_by']);

        // Handle photo upload
        if ($request->hasFile('photo_path')) {
            $file = $request->file('photo_path');
            $filename = time() . '_' . $file->getClientOriginalName();
            $destinationPath = resource_path('views/adminSide/maintenance/photos');

            // Create directory if it doesn't exist
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $filename);
            $data['photo_path'] = 'resources/views/adminSide/maintenance/photos/' . $filename;
        }

        // Convert cost to cents
        $data['cost'] = $this->toCents($request->cost);

        // Create maintenance record in transaction
        DB::transaction(function () use ($data) {
            Maintenance::create($data);
        });

        return redirect()->route('admin.maintenance.index')->with('success', 'Maintenance request created successfully!');
    }

    /**
     * Display the specified maintenance request
     */
    public function show(Maintenance $maintenance)
    {
        // Eager load relationships
        $maintenance->load(['lease.room.owner.user', 'lease.tenant.user', 'asset']);

        return view('adminSide.maintenance.show', compact('maintenance'));
    }

    /**
     * Show the form for editing the specified maintenance request
     */
    public function edit(Maintenance $maintenance)
    {
        // Load relationships
        $maintenance->load(['lease.room.assets', 'lease.tenant.user', 'asset']);

        // Get all leases
        $leases = Lease::with(['room', 'tenant.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get current lease's assets for initial display
        $selectedAssets = $maintenance->lease->room->assets;

        // Status options
        $statusOptions = ['Pending', 'Fixing', 'Resolved'];

        // Paid by options
        $paidByOptions = ['Owner', 'Tenant'];

        return view('adminSide.maintenance.edit', compact(
            'maintenance',
            'leases',
            'selectedAssets',
            'statusOptions',
            'paidByOptions'
        ));
    }

    /**
     * Update the specified maintenance request
     */
    public function update(Request $request, Maintenance $maintenance)
    {
        // Validation
        $request->validate([
            'lease_id' => ['required', 'exists:leases,id'],
            'asset_id' => ['nullable', 'exists:room_assets,id'],
            'title' => ['required', 'string', 'max:255'],
            'desc' => ['required', 'string'],
            'photo_path' => ['nullable', 'image', 'max:5120', 'mimes:jpeg,png,jpg,gif'],
            'status' => ['required', 'in:Pending,Fixing,Resolved'],
            'cost' => ['required', 'numeric', 'min:0'],
            'paid_by' => ['required', 'in:Owner,Tenant'],
        ]);

        // Custom validation: verify asset belongs to lease's room if provided
        if ($request->filled('asset_id')) {
            $lease = Lease::with('room.assets')->findOrFail($request->lease_id);
            if (!$lease->room->assets->contains('id', $request->asset_id)) {
                return back()->withErrors(['asset_id' => 'Selected asset does not belong to this lease\'s room'])->withInput();
            }
        }

        // Prepare data
        $data = $request->only(['lease_id', 'asset_id', 'title', 'desc', 'status', 'paid_by']);

        // Handle photo replacement
        if ($request->hasFile('photo_path')) {
            // Delete old photo if exists
            if ($maintenance->photo_path) {
                $oldPath = base_path($maintenance->photo_path);
                if (File::exists($oldPath)) {
                    File::delete($oldPath);
                }
            }

            // Upload new photo
            $file = $request->file('photo_path');
            $filename = time() . '_' . $file->getClientOriginalName();
            $destinationPath = resource_path('views/adminSide/maintenance/photos');

            // Create directory if it doesn't exist
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file->move($destinationPath, $filename);
            $data['photo_path'] = 'resources/views/adminSide/maintenance/photos/' . $filename;
        }

        // Convert cost to cents
        $data['cost'] = $this->toCents($request->cost);

        // Update maintenance record in transaction
        DB::transaction(function () use ($maintenance, $data) {
            $maintenance->update($data);
        });

        return redirect()->route('admin.maintenance.show', $maintenance)->with('success', 'Maintenance request updated successfully!');
    }

    /**
     * AJAX endpoint to get assets by lease ID
     */
    public function assetsByLease(Request $request)
    {
        $leaseId = trim((string) $request->query('lease_id', ''));

        if ($leaseId === '') {
            return response()->json([]);
        }

        $lease = Lease::with('room.assets')->find($leaseId);

        if (!$lease) {
            return response()->json([]);
        }

        $assets = $lease->room->assets->map(function ($asset) {
            return [
                'id' => $asset->id,
                'name' => $asset->name,
                'condition' => $asset->condition,
            ];
        })->values();

        return response()->json($assets);
    }

    /**
     * Serve maintenance photo files
     */
    public function showPhoto($filename)
    {
        $path = resource_path('views/adminSide/maintenance/photos/' . $filename);

        if (!File::exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    /**
     * Convert RM amount to cents for storage
     */
    private function toCents($value): int
    {
        $sanitized = preg_replace('/[^0-9.]/', '', (string) $value);
        if ($sanitized === '') {
            return 0;
        }

        return (int) round(((float) $sanitized) * 100);
    }
}
