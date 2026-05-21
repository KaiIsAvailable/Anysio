<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\Property;
use App\Models\Owners;
use App\Models\User;
use App\Models\UserManagement;
use App\Models\Room;
use App\Models\Unit;
use Faker\Guesser\Name;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $user = Auth::user();

        // 1. 构建基础查询，带上必要的 Join
        $query = Property::query()
            ->leftJoin('users as owners', 'properties.owner_id', '=', 'owners.id')
            ->leftJoin('users as creators', 'properties.created_by', '=', 'creators.id')
            ->select('properties.*', 'owners.name as owner_name', 'creators.name as creator_name');

        // 2. 权限过滤：如果是 ownerAdmin 或 agentAdmin，限制为只能看到自己创建的房源
        // 这里使用 when 逻辑，既清晰又符合 Laravel 链式调用习惯
        $query->when(in_array($user->role, ['ownerAdmin', 'agentAdmin']), function ($q) use ($user) {
            return $q->where('properties.created_by', $user->id);
        });

        $sortMapping = [
            'n'   => trim('properties.name'),
            'a'   => trim('properties.address'),
            'c'   => trim('properties.city'),
            'p'   => trim('properties.postcode'),
            's'   => trim('properties.state'),
            't'   => trim('properties.type'),
            'cr'  => trim('properties.created_at'), 
            'st'  => trim('properties.status'),
            'o'   => trim('owner_name'), 
            'cre' => trim('creator_name'),       
        ];

        // 3. 搜索和排序 (保持不变)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('properties.name', 'like', "%{$search}%")
                ->orWhere('properties.address', 'like', "%{$search}%")
                ->orWhere('properties.city', 'like', "%{$search}%")
                ->orWhere('properties.postcode', 'like', "%{$search}%")
                ->orWhere('properties.state', 'like', "%{$search}%")
                ->orWhere('properties.type', 'like', "%{$search}%")
                ->orWhere('owners.name', 'like', "%{$search}%");
                //->orWhere('creators.name', 'like', "%{$search}%");
            });
        }

        $sortParam = $request->query('sort'); // 例如 'o_asc'
    
        // 2. 拆分参数
        $field = Str::beforeLast($sortParam, '_'); 
        $direction = Str::afterLast($sortParam, '_');

        // 3. 核心安全判断：只有在白名单内的字段才允许执行 orderBy
        if (array_key_exists($field, $sortMapping) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy($sortMapping[$field], $direction);
        } else {
            // 如果用户随意乱改 URL，默认按 ID 降序，保证系统运行且安全
            $query->orderBy('properties.id', 'desc');
        }

        $properties = $query->paginate(10)->appends($request->query());

        return view('adminSide.rooms.property.index', compact('properties'));
    }

        public function create()
        {
            $user = Auth::user();

            $isOwnerAdmin = $user->role === 'ownerAdmin';
            $owners = User::whereIn('role', ['owner', 'ownerAdmin'])->get(['id', 'name']);

            $currentOwner = null;
            if ($isOwnerAdmin) {
                $currentOwner = $user;
            }

            if ($owners->isEmpty()) {
                return redirect()->back()->with('error', 'Owner profile not found. Please contact admin.');
            }

            // 将 $isOwnerAdmin 传给 Blade
            return view('adminSide.rooms.property.create', compact('owners', 'isOwnerAdmin', 'currentOwner')); 
        }

    public function store(Request $request)
    {
        $user = Auth::id();
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'address'  => 'required|string',
            'city'     => 'required|string|max:100',
            'postcode' => 'required|digits:5',
            'state'    => 'required|string|max:100',
            'type'     => 'required', // 根据你的需求定义
            'owner_id'  => 'nullable|exists:users,id'
        ]);

        if ($request->has_owner === '0') {
            $validated['owner_id'] = null;
        } else {
            $validated['owner_id'] = $request->owner_id ?? Auth::id();
        }

        $validated['created_by'] = Auth::id();

        Property::create($validated);

        return redirect()->route('admin.properties.index')
                        ->with('success', 'Property created successfully!');
    }

    public function show(Request $request, Property $property)
    {
        // 从当前 Property 下的单位开始查询
        $query = Unit::query()
            ->where('property_id', $property->id)
            ->leftJoin('users as owners', 'units.owner_id', '=', 'owners.id')
            ->select('units.*', 'owners.name as owner_name'); // 获取业主名用于排序

        // 搜索逻辑
        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('units.unit_no', 'like', "%{$search}%")
                ->orWhere('owners.name', 'like', "%{$search}%");
            });
        }

        // 排序白名单
        $sortMapping = [
            'u' => 'units.unit_no',
            'o' => 'owner_name',    // 对应上面 join 进来的字段
            's' => 'units.status',
            'p' => 'units.management_fee', // 假设 Price 指的是管理费
        ];

        $sortParam = $request->query('sort'); 
        $field = Str::beforeLast($sortParam, '_');
        $direction = Str::afterLast($sortParam, '_');

        if (array_key_exists($field, $sortMapping) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy($sortMapping[$field], $direction);
        } else {
            $query->orderBy('units.unit_no', 'asc');
        }

        $units = $query->paginate(10)->appends($request->query());

        return view('adminSide.rooms.property.show', compact('property', 'units'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Property $property)
    {
        // $property 会根据 URL 中的 ID 自动查询（Route Model Binding）
        $user = Auth::user();
        $isOwnerAdmin = $user->role === 'ownerAdmin';
        $owners = User::whereIn('role', ['owner', 'ownerAdmin'])->get(['id', 'name']);

        $currentOwner = null;
        if ($isOwnerAdmin) {
            $currentOwner = $user;
        }

        if ($owners->isEmpty()) {
            return redirect()->back()->with('error', 'Owner profile not found. Please contact admin.');
        }

        return view('adminSide.rooms.property.edit', compact('property', 'isOwnerAdmin', 'currentOwner', 'owners'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Property $property)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'address'  => 'required|string',
            'city'     => 'required|string|max:100',
            'postcode' => 'required|digits:5',
            'state'    => 'required|string|max:100',
            'type'     => 'required', // 根据你的需求定义
            'owner_id'  => 'nullable|required_if:has_owner,1|exists:users,id'
        ]);

        if ($request->has_owner == 0) {
            $validated['owner_id'] = null;
        }
        $validated['created_by'] = Auth::id();

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
