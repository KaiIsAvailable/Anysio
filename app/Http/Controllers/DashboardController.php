<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Tenants;
use App\Models\Agreements;
use Illuminate\Support\Facades\Gate;
use App\Models\Owners;
use App\Models\Payment;
use App\Services\SetupCheckerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(SetupCheckerService $checker)
    {
        $user = Auth::user();

        $stats = DB::table('properties')
            ->leftJoin('units', 'properties.id', '=', 'units.property_id')
            ->leftJoin('rooms', 'units.id', '=', 'rooms.unit_id')
            // 核心：合并你的权限过滤逻辑
            ->where(function ($q) use ($user) {
                if (Gate::allows('super-admin')) return;
                
                if (Gate::allows('agent-admin')) {
                    $ownerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
                    $q->whereIn('properties.owner_id', $ownerIds);
                } else {
                    // Owner-Admin 的权限逻辑
                    $q->where('properties.created_by', $user->id)
                    ->orWhere('properties.owner_id', $user->id);
                }
            })
            ->selectRaw("
                -- Property 统计 (使用 distinct 避免 join 导致计数膨胀)
                count(DISTINCT properties.id) as total_properties,
                count(DISTINCT CASE WHEN units.status = 'Vacant' THEN properties.id END) as vacant_properties,
                count(DISTINCT CASE WHEN units.status = 'Occupied' THEN properties.id END) as occ_properties,
                count(DISTINCT CASE WHEN units.status = 'Maintenance' THEN properties.id END) as main_properties,
                count(DISTINCT CASE WHEN units.status = 'Cleaning' THEN properties.id END) as clean_properties,

                -- Unit 统计
                count(DISTINCT units.id) as total_units,
                count(DISTINCT CASE WHEN units.status = 'Vacant' THEN units.id END) as vacant_units,
                count(DISTINCT CASE WHEN units.status = 'Occupied' THEN units.id END) as occ_units,
                count(DISTINCT CASE WHEN units.status = 'Maintenance' THEN units.id END) as main_units,
                count(DISTINCT CASE WHEN units.status = 'Cleaning' THEN units.id END) as clean_units,

                -- Room 统计
                count(DISTINCT rooms.id) as total_rooms,
                count(DISTINCT CASE WHEN rooms.status = 'Vacant' THEN rooms.id END) as vacant_rooms,
                count(DISTINCT CASE WHEN rooms.status = 'Occupied' THEN rooms.id END) as occ_rooms,
                count(DISTINCT CASE WHEN rooms.status = 'Maintenance' THEN rooms.id END) as main_rooms,
                count(DISTINCT CASE WHEN rooms.status = 'Cleaning' THEN rooms.id END) as clean_rooms
            ")
            ->first();

        // 转换对象为数组以兼容你之前的视图逻辑
        $counts = (array) $stats;

        //Payment
        $overduePayments = Payment::with([
                'tenant.user',
                'lease.unit', 
                'lease.room'  
            ])
            ->where('status', 'unpaid')
            ->where('period', '<', now())
            // 直接在这里过滤 tenant 表
            ->whereHas('tenant', function ($query) {
                $query->where('created_by', Auth::id());
            })
            ->orderBy('period', 'asc')
            ->get();

        $checks = $checker->check(['property', 'tenant', 'template', 'owner', 'asset'], 'exists');

        return view('dashboard', compact('overduePayments', 'checks', 'counts'));
    }
}