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

        if (!Gate::allows('super-admin')) {
            if (Gate::allows('agent-admin')) {
                $ownerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
                $query->whereIn('properties.owner_id', $ownerIds);
            } elseif (Gate::allows('owner-admin')) {
                $query->where('properties.created_by', $user->id);
            } else {
                $query->whereRaw('1 = 0'); 
            }
        }

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

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('properties.name', 'like', "%{$search}%")
                ->orWhere('properties.address', 'like', "%{$search}%")
                ->orWhere('properties.city', 'like', "%{$search}%")
                ->orWhere('properties.postcode', 'like', "%{$search}%")
                ->orWhere('properties.state', 'like', "%{$search}%")
                ->orWhere('properties.type', 'like', "%{$search}%")
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

        $properties = $query->paginate(10)->appends($request->query());

        return view('adminSide.rooms.property.index', compact('properties'));
    }

    public function create()
    {
        $user = Auth::user();

        $isOwnerAdmin = $user->role === 'ownerAdmin';
        $isAgentAdmin = $user->role === 'agentAdmin';
        $isSuperAdmin = Gate::allows('super-admin');

        $owners = collect();
        $currentOwner = null;

        if ($isOwnerAdmin) {
            $currentOwner = $user;
        } elseif ($isAgentAdmin) {
            $ownerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
            $owners = User::whereIn('id', $ownerIds)
                ->where('role', 'owner')
                ->get(['id', 'name']);
        } elseif ($isSuperAdmin) {
            $owners = User::whereIn('role', ['owner', 'ownerAdmin', 'agentAdmin', 'admin'])
                ->get(['id', 'name']);
        }

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
            ->select('units.*', 'owners.name as owner_name'); // 获取业主名用于排序

        // 搜索逻辑
        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('units.unit_no', 'like', "%{$search}%")
                ->orWhere('owners.name', 'like', "%{$search}%");
            });
        }
        $user = Auth::user();

        if (!Gate::allows('super-admin')) {
            if (Gate::allows('owner-admin')) {
                if ($property->created_by !== $user->id) {
                    return redirect()->route('admin.properties.index')
                        ->with('error', 'You are not authorized to view that property.');
                }
            } elseif (Gate::allows('agent-admin')) {
                $allowedOwnerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
                if (!$allowedOwnerIds->contains($property->owner_id)) {
                    return redirect()->route('admin.properties.index')
                        ->with('error', 'You are not authorized to view that property.');
                }
            } else {
                return redirect()->route('admin.properties.index')
                    ->with('error', 'You are not authorized to view that property.');
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

    public function edit(Property $property)
    {
        $user = Auth::user();
        
        if (!Gate::allows('super-admin')) {
            if (Gate::allows('owner-admin')) {
                if ($property->created_by !== $user->id) {
                    return redirect()->route('admin.properties.index')->with('error', 'Unauthorized.');
                }
            } elseif (Gate::allows('agent-admin')) {
                $allowedOwnerIds = Owners::where('agent_id', $user->id)->pluck('user_id');
                if (!$allowedOwnerIds->contains($property->owner_id)) {
                    return redirect()->route('admin.properties.index')->with('error', 'Unauthorized.');
                }
            } else {
                return redirect()->route('admin.properties.index')->with('error', 'Unauthorized.');
            }
        }

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

    public function update(Request $request, Property $property)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'address'  => 'required|string',
            'city'     => 'required|string|max:100',
            'postcode' => 'required|digits:5',
            'state'    => 'required|string|max:100',
            'type'     => 'required',
            'owner_id'  => 'nullable|required_if:has_owner,1|exists:users,id'
        ]);

        if ($request->has_owner == 0) {
            $validated['owner_id'] = null;
        }

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

            Property::where('id', $id)->update(['status' => 'Vacant']);

            Unit::where('property_id', $id)
                ->where('status', 'removed')
                ->update(['status' => 'Vacant']);

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