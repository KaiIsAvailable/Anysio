<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, Owners, UserManagement, User, Lease, Property, Unit, Room};
use App\Services\SetupCheckerService;
use Illuminate\Support\Facades\{Auth, File, DB, Gate};
use App\Traits\RoleBasedDataTrait;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use RoleBasedDataTrait;

    public function index(SetupCheckerService $checker, Request $request)
    {
        $user = get_effective_user();
        $actualUserId = Auth::id();
        $effectiveUser = get_effective_user();
        $effectiveUserId = $effectiveUser?->id;

        // 1. Base query for Leases needing attention (Pending Renewal or Ended)
        // Fixed: Removed 'property' which doesn't exist on the Lease model.
        // Now 'leasable' and 'tenant.user' will load properly.
        $pendingOrEndedLeasesQuery = Lease::with([
            'leasable', 
            'tenant.user'
        ]);

        // 2. Apply authorization/ownership filtering
        if (!Gate::allows('super-admin')) {
            $pendingOrEndedLeasesQuery->where(function ($q) use ($effectiveUserId, $actualUserId) {
                $q->whereHasMorph('leasable', [Room::class, Unit::class, Property::class], function ($mq, $type) use ($effectiveUserId, $actualUserId) {
                    if ($type === Room::class) {
                        $mq->whereHas('unit.owner', function ($oq) use ($effectiveUserId, $actualUserId) {
                            $oq->where(function ($q) use ($effectiveUserId, $actualUserId) {
                                $q->where('created_by', $effectiveUserId)
                                    ->orWhere('created_by', $actualUserId)
                                    ->orWhere('owner_id', $effectiveUserId);
                            });
                        });
                    } else {
                        $mq->whereHas('owner', function ($oq) use ($effectiveUserId, $actualUserId) {
                            $oq->where(function ($q) use ($effectiveUserId, $actualUserId) {
                                $q->where('created_by', $effectiveUserId)
                                    ->orWhere('created_by', $actualUserId)
                                    ->orWhere('owner_id', $effectiveUserId);
                            });
                        });
                    }
                })
                ->orWhereHas('tenant', function ($tq) use ($effectiveUserId, $actualUserId) {
                    $tq->where('created_by', $effectiveUserId)
                        ->orWhere('created_by', $actualUserId);
                });
            });
        }

        // 3. Filter specifically for "Leases Needing Attention" 
        $pendingOrEndedLeases = $pendingOrEndedLeasesQuery
            ->where('is_current', true) // 👈 Applies to everything below
            ->where(function ($q) {
                $q->where('is_pending_renewal', true)
                ->orWhere('status', 'End');
            })
            ->orderBy('end_date', 'asc')
            ->paginate(5)
            ->appends($request->query());

        // 4. Property Statistics Query
        $statsQuery = DB::table('properties')
            ->leftJoin('units', 'properties.id', '=', 'units.property_id')
            ->leftJoin('rooms', 'units.id', '=', 'rooms.unit_id');

        if (!Gate::allows('super-admin')) {
            $statsQuery->where(function ($q) use ($user) {
                if ($user->role === 'ownerAdmin') {
                    $q->where('properties.owner_id', $user->id)
                      ->orWhere('units.owner_id', $user->id);
                } elseif ($user->role === 'agentAdmin') {
                    $managedOwnerIds = Owners::where('agent_id', $user->id)
                        ->select('user_id');

                    $q->where(function ($sub) use ($user, $managedOwnerIds) {
                        $sub->where('properties.owner_id', $user->id)
                            ->orWhereIn('properties.owner_id', $managedOwnerIds)
                            ->orWhere('units.owner_id', $user->id)
                            ->orWhereIn('units.owner_id', $managedOwnerIds);
                    });
                }
            });
        }

        $stats = $statsQuery
            ->selectRaw("
                COUNT(DISTINCT properties.id) AS total_properties,
                COUNT(DISTINCT CASE WHEN units.status = 'Vacant' THEN properties.id END) AS vacant_properties,
                COUNT(DISTINCT CASE WHEN units.status = 'Occupied' THEN properties.id END) AS occ_properties,
                COUNT(DISTINCT CASE WHEN units.status = 'Maintenance' THEN properties.id END) AS main_properties,
                COUNT(DISTINCT CASE WHEN units.status = 'Cleaning' THEN properties.id END) AS clean_properties,

                COUNT(DISTINCT units.id) AS total_units,
                COUNT(DISTINCT CASE WHEN units.status = 'Vacant' THEN units.id END) AS vacant_units,
                COUNT(DISTINCT CASE WHEN units.status = 'Occupied' THEN units.id END) AS occ_units,
                COUNT(DISTINCT CASE WHEN units.status = 'Maintenance' THEN units.id END) AS main_units,
                COUNT(DISTINCT CASE WHEN units.status = 'Cleaning' THEN units.id END) AS clean_units,

                COUNT(DISTINCT rooms.id) AS total_rooms,
                COUNT(DISTINCT CASE WHEN rooms.status = 'Vacant' THEN rooms.id END) AS vacant_rooms,
                COUNT(DISTINCT CASE WHEN rooms.status = 'Occupied' THEN rooms.id END) AS occ_rooms,
                COUNT(DISTINCT CASE WHEN rooms.status = 'Maintenance' THEN rooms.id END) AS main_rooms,
                COUNT(DISTINCT CASE WHEN rooms.status = 'Cleaning' THEN rooms.id END) AS clean_rooms
            ")
            ->first();

        $counts = (array) $stats;

        // 5. Overdue Invoices
        $overdueInvoices = Invoice::with([
            'lease.tenant.user',
            'lease.leasable', // Fixed to use polymorphic relation instead of direct unit/room methods if they aren't standard relationships
            'items',
        ])
            ->where('status', 'unpaid')
            ->where('due_date', '<', now())
            ->whereHas('lease.tenant', function ($query) use ($user) {
                $query->where('created_by', $user->id);
            })
            ->orderBy('due_date', 'asc')
            ->get();

        // 6. Setup Checks & Seeders
        $checks = $checker->check(['property', 'tenant', 'template', 'owner', 'asset'], 'exists');

        $seederPath = database_path('seeders');
        $seeders = collect(File::exists($seederPath) ? File::files($seederPath) : [])
            ->map(fn ($file) => $file->getFilenameWithoutExtension())
            ->reject(fn ($name) => $name === 'DatabaseSeeder')
            ->mapWithKeys(fn ($name) => [$name => $name])
            ->toArray();

        return view('dashboard', compact('overdueInvoices', 'checks', 'counts', 'seeders', 'pendingOrEndedLeases'));
    }
}