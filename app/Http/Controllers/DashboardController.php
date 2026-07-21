<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Models\Invoice;
use App\Models\Owners;
use App\Services\SetupCheckerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Traits\RoleBasedDataTrait;

class DashboardController extends Controller
{
    use RoleBasedDataTrait;
    public function index(SetupCheckerService $checker)
    {
        $user = Auth::user();

        $statsQuery = DB::table('properties')
            ->leftJoin('units', 'properties.id', '=', 'units.property_id' )
            ->leftJoin('rooms', 'units.id', '=', 'rooms.unit_id' );

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
                /*
                |--------------------------------------------------------------------------
                | Property Statistics
                |--------------------------------------------------------------------------
                */
                COUNT(DISTINCT properties.id) AS total_properties,
                COUNT(DISTINCT CASE WHEN units.status = 'Vacant' THEN properties.id END) AS vacant_properties,
                COUNT(DISTINCT CASE WHEN units.status = 'Occupied' THEN properties.id END) AS occ_properties,
                COUNT(DISTINCT CASE WHEN units.status = 'Maintenance' THEN properties.id END) AS main_properties,
                COUNT(DISTINCT CASE WHEN units.status = 'Cleaning' THEN properties.id END) AS clean_properties,

                /*
                |--------------------------------------------------------------------------
                | Unit Statistics
                |--------------------------------------------------------------------------
                */
                COUNT(DISTINCT units.id) AS total_units,
                COUNT(DISTINCT CASE WHEN units.status = 'Vacant' THEN units.id END) AS vacant_units,
                COUNT(DISTINCT CASE WHEN units.status = 'Occupied' THEN units.id END) AS occ_units,
                COUNT(DISTINCT CASE WHEN units.status = 'Maintenance' THEN units.id END) AS main_units,
                COUNT(DISTINCT CASE WHEN units.status = 'Cleaning' THEN units.id END) AS clean_units,

                /*
                |--------------------------------------------------------------------------
                | Room Statistics
                |--------------------------------------------------------------------------
                */
                COUNT(DISTINCT rooms.id) AS total_rooms,
                COUNT(DISTINCT CASE WHEN rooms.status = 'Vacant' THEN rooms.id END) AS vacant_rooms,
                COUNT(DISTINCT CASE WHEN rooms.status = 'Occupied' THEN rooms.id END) AS occ_rooms,
                COUNT(DISTINCT CASE WHEN rooms.status = 'Maintenance' THEN rooms.id END) AS main_rooms,
                COUNT(DISTINCT CASE WHEN rooms.status = 'Cleaning' THEN rooms.id END) AS clean_rooms
            ")
            ->first();
        /*
        |--------------------------------------------------------------------------
        | Convert Statistics To Array
        |--------------------------------------------------------------------------
        */
        $counts = (array) $stats;

        /*
        |--------------------------------------------------------------------------
        | Overdue Invoices
        |--------------------------------------------------------------------------
        */
        $overdueInvoices = Invoice::with([
            'lease.tenant.user',
            'lease.unit',
            'lease.room',
            'items',
        ])
            ->where('status', 'unpaid')
            ->where('due_date', '<', now())
            ->whereHas('lease.tenant', function ($query) use ($user) {
                $query->where('created_by', $user->id);
            })
            ->orderBy('due_date', 'asc')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Setup Checks
        |--------------------------------------------------------------------------
        */
        $checks = $checker->check(['property', 'tenant', 'template', 'owner', 'asset',], 'exists');

        return view('dashboard', compact('overdueInvoices', 'checks', 'counts'));
    }
}