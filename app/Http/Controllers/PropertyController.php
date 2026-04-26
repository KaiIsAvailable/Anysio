<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\Property;
use App\Models\Owners;
use App\Models\UserManagement;
use App\Models\Room;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort   = $request->input('sort');
        $user   = Auth::user();
        $query = Property::query(); 
        $accessiblePropertyIds = [];
        
        $accessiblePropertyIds = Unit::where('owner_id', $user->owner?->id)
            ->select('property_id') // 明确只选这一列
            ->pluck('property_id')
            ->unique();

        if ($user->role === 'owner') {
            $accessiblePropertyIds = Unit::where('owner_id', $user->owner?->id)
                ->pluck('property_id')
                ->unique();
        } elseif ($user->role === 'agent' || $user->role === 'agentAdmin') {
            $managedOwnerIds = Owners::where('agent_id', $user->id)->pluck('id');
            $accessiblePropertyIds = Unit::whereIn('owner_id', $managedOwnerIds)
                ->pluck('property_id')
                ->unique();
        } elseif ($user->role === 'ownerAdmin' || $user->role === 'agentAdmin') {
            // 逻辑：查找 Property 表中所有由我创建的房产 ID
            $accessiblePropertyIds = Property::where('created_by', Auth::id()) // 注意字段名是 create_by 还是 created_by
                ->pluck('id'); // 这里拿主键 id
        }
        if (!Gate::allows('super-admin')) {
            $query->whereIn('id', $accessiblePropertyIds);
        }

        $query->withCount('units');

        // 3. 搜索和排序 (保持不变)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%");
            });
        }

        $sortData = [
            'name' => [
                'isActive' => str_contains($sort, 'property_no'),
                'isAsc'    => $sort === 'property_no_asc',
                'next'     => ($sort === 'property_no_asc') ? 'property_no_desc' : 'property_no_asc'
            ],
            'date' => [
                'isActive' => is_null($sort) || $sort === 'newest' || $sort === 'oldest',
                'isAsc'    => is_null($sort) || $sort === 'oldest', // 默认 oldest 为 asc
                'next'     => ($sort === 'newest') ? 'oldest' : 'newest'
            ]
        ];

        // 处理你的排序逻辑
        $sortField = match($sort) {
            'property_no_asc' => 'name',
            'property_no_desc' => 'name',
            'newest' => 'created_at',
            default => 'created_at'
        };
        $sortOrder = ($sort === 'property_no_desc' || $sort === 'newest') ? 'desc' : 'asc';
        $query->orderBy($sortField, $sortOrder);

        $properties = $query->paginate(10)->appends($request->query());

        return view('adminSide.rooms.property.index', compact('properties', 'sortData'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        
        // 1. 判断身份
        $isOwnerAdmin = ($user->role === 'ownerAdmin');

        if ($isOwnerAdmin) {
            $owners = UserManagement::where('user_id', $user->id)->with('user')->get();
            if ($owners->isEmpty()) {
                return redirect()->back()->with('error', 'Owner profile not found. Please contact admin.');
            }
        } else {
            $owners = Owners::with('user')->get();
        }

        // 将 $isOwnerAdmin 传给 Blade
        return view('adminSide.rooms.property.create', compact('owners', 'isOwnerAdmin')); 
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'address'  => 'required|string',
            'city'     => 'required|string|max:100',
            'postcode' => 'required|string|max:10',
            'state'    => 'required|string|max:100',
            'type'     => 'required|in:Condo,Landed,Commercial', // 根据你的需求定义
            'owner_id'  => 'nullable|required_if:has_owner,1|exists:owners,id'
        ]);

        if ($request->has_owner == 0) {
            $validated['owner_id'] = null;
        }
        $validated['created_by'] = Auth::id();

        Property::create($validated);

        return redirect()->route('admin.properties.index')
                        ->with('success', 'Property created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Property $property)
    {

        $property->load('units');

        return view('adminSide.rooms.property.show', compact('property'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Property $property)
    {
        // $property 会根据 URL 中的 ID 自动查询（Route Model Binding）
        return view('adminSide.rooms.property.edit', compact('property'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Property $property)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'type'     => 'required|string',
            'address'  => 'required|string',
            'city'     => 'required|string',
            'postcode' => 'required|string',
            'state'    => 'required|string',
        ]);

        $property->update($validated);

        return redirect()->route('admin.properties.index')
                        ->with('success', 'Property updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            // 1. 查找 Property (确保它存在)
            $property = Property::findOrFail($id);

            // 2. 更新 Property 状态
            // 直接在 Query Builder 上调用 update，不依赖对象实例
            Property::where('id', $id)->update(['status' => 'Removed']);

            // 3. 批量更新该 Property 下所有的 Units
            // 这行代码不会产生 stdClass 错误，因为它直接操作数据库表
            Unit::where('property_id', $id)->update(['status' => 'Removed']);

            // 4. 批量更新该 Property 下所有 Unit 拥有的 Rooms
            // 使用子查询：更新所有 unit_id 属于该 property 的房间
            Room::whereIn('unit_id', function($query) use ($id) {
                $query->select('id')->from('units')->where('property_id', $id);
            })->update(['status' => 'Removed']);

            DB::commit();
            
            return redirect()->route('admin.properties.index')
                ->with('success', 'Property and all associated units/rooms have been marked as removed.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error while removing property: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        try {
            DB::beginTransaction();

            // 1. 恢复 Property 状态
            Property::where('id', $id)->update(['status' => 'Vacant']);

            // 2. 恢复该 Property 下所有 Units 的状态
            Unit::where('property_id', $id)
                ->where('status', 'removed') // 只恢复那些被标记为 removed 的
                ->update(['status' => 'Vacant']);

            // 3. 恢复所有相关的 Rooms
            Room::whereIn('unit_id', function($query) use ($id) {
                $query->select('id')->from('units')->where('property_id', $id);
            })
            ->where('status', 'removed')
            ->update(['status' => 'Vacant']);

            DB::commit();
            return redirect()->back()->with('success', 'Property and its units/rooms have been restored.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }
}
