<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Owners;
use App\Models\User;
use App\Models\UserManagement;
use App\Models\Room;
use App\Models\Unit;
use App\Traits\RoleBasedDataTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyController extends Controller
{
    use RoleBasedDataTrait;
    public function index(Request $request)
    {
        $search = $request->input('search');
        $user = Auth::user();

        // 1. 构建基础查询，带上必要的 Join
        $query = Property::query()
            ->leftJoin('users as owners', 'properties.owner_id', '=', 'owners.id')
            ->leftJoin('users as creators', 'properties.created_by', '=', 'creators.id')
            ->select('properties.*', 'owners.name as owner_name', 'creators.name as creator_name')
            ->withCount('units');

        $query->where(function ($q) use ($user) {
            if (Gate::allows('super-admin')) {
                return; // 超管不过滤
            }

            if (Gate::allows('agent-admin')) {
                // Agent 只能看所属 Owner 的房源
                $ownerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
                $q->where(function ($sub) use ($ownerIds, $user) {
                    $sub->whereIn('properties.owner_id', $ownerIds)
                        ->orWhere('properties.created_by', $user->id); 
                });
            } elseif (Gate::allows('owner-admin')) {
                // Owner 只能看自己创建的 OR 自己是 Owner 的
                $q->where(function ($sub) use ($user) {
                    $sub->where('properties.created_by', $user->id)
                        ->orWhere('properties.owner_id', $user->id);
                });
            } else {
                $q->whereRaw('1 = 0'); // 没权限就返回空
            }
        });

        // 💡 重点：严格对齐 index.blade.php 中的 6 列 (Name, Type, Status, Owner, Address, Created)
        $sortMapping = [
            'n'  => 'properties.name',
            't'  => 'properties.type',
            'st' => 'properties.status',
            'o'  => 'owner_name',
            'a'  => 'properties.address',
            'cr' => 'properties.created_at',
        ];

        // 💡 重点：搜索只搜表格里显示的列 (Address 包含了 City, Postcode, State 所以一起搜)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('properties.name', 'like', "%{$search}%")
                    ->orWhere('properties.type', 'like', "%{$search}%")
                    ->orWhere('properties.status', 'like', "%{$search}%")
                    ->orWhere('properties.address', 'like', "%{$search}%")
                    ->orWhere('properties.city', 'like', "%{$search}%")
                    ->orWhere('properties.postcode', 'like', "%{$search}%")
                    ->orWhere('properties.state', 'like', "%{$search}%")
                    ->orWhere('owners.name', 'like', "%{$search}%");
            });
        }

        $sortParam = $request->query('sort');
        $field = Str::beforeLast($sortParam, '_');
        $direction = Str::afterLast($sortParam, '_');

        if (array_key_exists($field, $sortMapping) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy($sortMapping[$field], $direction);
        } else {
            $query->orderBy('properties.id', 'desc');
        }

        $properties = $query->paginate(10)->onEachSide(1)->appends($request->query());

        return view('adminSide.rooms.property.index', compact('properties'));
    }

    public function create()
    {
        $user = Auth::user();

        $isOwnerAdmin = $user->role === 'ownerAdmin';
        $isAgentAdmin = $user->role === 'agentAdmin';
        $isSuperAdmin = Gate::allows('super-admin');

        $owners = $this->getAuthorizedOwners(); 
        
        $currentOwner = $isOwnerAdmin ? $user : null;

        return view('adminSide.rooms.property.create', compact(
            'owners',
            'isOwnerAdmin',
            'isAgentAdmin',
            'isSuperAdmin',
            'currentOwner'
        ));
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
            'type'     => 'required',
            'owner_id'  => 'nullable|required_if:has_owner,1|exists:users,id'
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
            ->select('units.*', 'owners.name as owner_name');

        // 💡 重点：搜索逻辑严格限制在 Unit No, Status 和 Owner Name
        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('units.unit_no', 'like', "%{$search}%")
                    ->orWhere('units.status', 'like', "%{$search}%")
                    ->orWhere('owners.name', 'like', "%{$search}%");
            });
        }
        
        $user = Auth::user();

        if (!Gate::allows('super-admin')) {
            if (Gate::allows('owner-admin')) {
                if ($property->created_by !== $user->id && $property->owner_id !== $user->id) {
                    return redirect()->route('admin.properties.index')->with('error', 'You are not authorized to view that property.');
                }
            } elseif (Gate::allows('agent-admin')) {
                $allowedOwnerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
                if (!$allowedOwnerIds->contains($property->owner_id)) {
                    return redirect()->route('admin.properties.index')->with('error', 'You are not authorized to view that property.');
                }
            } else {
                return redirect()->route('admin.properties.index')->with('error', 'You are not authorized to view that property.');
            }
        }

        $property->load(['units' => function ($query) use ($user) {
            if (!Gate::allows('super-admin')) {
                if (Gate::allows('agent-admin')) {
                    $allowedOwnerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
                    $query->whereIn('owner_id', $allowedOwnerIds);
                } elseif (Gate::allows('owner-admin')) {
                    $query->where('owner_id', $user->id);
                }
            }
        }]);

        // 💡 重点：排序白名单，严格对齐 show.blade.php 里的 4 个 TH
        $sortMapping = [
            'u' => 'units.unit_no',
            'o' => 'owner_name',    
            's' => 'units.status',
        ];

        $sortParam = $request->query('sort');
        $field = Str::beforeLast($sortParam, '_');
        $direction = Str::afterLast($sortParam, '_');

        if (array_key_exists($field, $sortMapping) && in_array($direction, ['asc', 'desc'])) {
            $query->orderBy($sortMapping[$field], $direction);
        } else {
            $query->orderBy('units.unit_no', 'asc');
        }

        $units = $query->paginate(10)->onEachSide(1)->appends($request->query());

        return view('adminSide.rooms.property.show', compact('property', 'units'));
    }

    public function edit(Property $property)
    {
        $user = Auth::user();

        // 2. 调用 Trait 获取统一权限下的 Owners 列表
        $owners = $this->getAuthorizedOwners();

        // 3. 校验是否有 Owner 数据
        if ($owners->isEmpty()) {
            return redirect()->back()->with('error', 'Owner profile not found.');
        }

        // 4. 定义前端需要的状态标志
        $isOwnerAdmin = $user->role === 'ownerAdmin';
        $currentOwner = $isOwnerAdmin ? $user : null;

        // 注意：这里删掉了之前那个硬编码的 $owners = User::whereIn(...) 
        // 直接用上面 trait 返回的 $owners
        return view('adminSide.rooms.property.edit', compact('property', 'isOwnerAdmin', 'currentOwner', 'owners'));
    }

    public function update(Request $request, Property $property)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'address'  => 'required|string',
            'city'     => 'required|string|max:100',
            'postcode' => 'required|digits:5',
            'state'    => 'required|string|max:100',
            'type'     => 'required',
            // 💡 修复点 1：去掉 required_if:has_owner,1，因为 edit 页面根本没这个字段
            'owner_id' => 'nullable|exists:users,id'
        ]);

        // 💡 修复点 2：彻底删掉 if ($request->has_owner == 0) 的弱类型判断Bug
        // 💡 修复点 3：直接接收前端传来的 owner_id。如果有值就存，没值（没选）就是 null
        $validated['owner_id'] = $request->owner_id ?: null;

        $property->update($validated);

        return redirect()->route('admin.properties.index')
            ->with('success', 'Property updated successfully!');
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $property = Property::findOrFail($id);

            Property::where('id', $id)->update(['status' => 'Removed']);
            Unit::where('property_id', $id)->update(['status' => 'Removed']);
            Room::whereIn('unit_id', function ($query) use ($id) {
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

            Property::where('id', $id)->update(['status' => 'Vacant']);

            Unit::where('property_id', $id)
                ->where('status', 'removed')
                ->update(['status' => 'Vacant']);

            Room::whereIn('unit_id', function ($query) use ($id) {
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
