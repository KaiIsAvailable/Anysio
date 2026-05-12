<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Unit;
use App\Models\RoomAsset;
use App\Models\Asset;
use App\Models\Room;
use App\Models\Property;
use App\Models\Owners;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // 1. 获取选中的 Property
        $selectedPropertyId = $request->query('property_id');
        $targetProperty = $selectedPropertyId ? Property::with('owner.user')->find($selectedPropertyId) : null;
        $properties = $targetProperty ? collect([$targetProperty]) : Property::all();

        // 2. 确定 targetOwner (增加 Property 继承逻辑)
        $selectedOwnerId = $request->query('owner_id');
        
        if ($selectedOwnerId) {
            // 如果 URL 明确传了 owner_id，直接查
            $targetOwner = Owners::with('user')->find($selectedOwnerId);
        } elseif ($targetProperty && $targetProperty->owner_id) {
            // 如果 URL 没传，但 Property 自己有业主，直接拿 Property 的
            $targetOwner = $targetProperty->owner;
        } else {
            $targetOwner = null;
        }

        // 3. 其他数据加载
        $owners = Owners::with('user')->get();
        
        // 建议：资产库根据业主过滤 (如果 targetOwner 存在)
        $query = Asset::select('id', 'name', 'user_id', 'status');
        if ($targetOwner) {
            $query->where('user_id', $targetOwner->user_id);
        }
        $assetLibrary = $query->get();
        
        return view('adminSide.rooms.unit.create', compact(
            'properties', 
            'owners', 
            'targetProperty', 
            'assetLibrary', 
            'targetOwner'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. 验证数据 (建议根据你的需求完善验证规则)
        $request->validate([
            // --- Unit 验证 ---
            'property_id' => 'required',
            'unit_no' => [
                'required',
                'string',
                // 核心逻辑：在 properties 关联下，unit_no 必须唯一
                Rule::unique('units')->where(fn ($query) => $query->where('property_id', $request->property_id))
            ],
            'has_rooms' => 'required|boolean',
            'total_rooms' => 'nullable|integer|min:0',

            // --- Rooms 数组验证 ---
            'rooms' => 'required_if:has_rooms,1|array',
            // 验证数组里的每一个 room_no
            'rooms.*.room_no' => [
                'required_if:has_rooms,1',
                'distinct', // 确保这次提交的数组里，room_no 没有重复的（比如两个 A-01）
            ],
            'rooms.*.room_type' => 'required_if:has_rooms,1',
            
            // --- Assets 验证 ---
            'unit_assets.*.qty' => 'integer|min:0',
            'rooms.*.assets.*.qty' => 'integer|min:0',
        ], [
            // 自定义错误信息
            'unit_no.unique' => 'This unit number already exists in this property.',
            'rooms.*.room_no.distinct' => 'You have duplicate room numbers in your list.',
            'rooms.*.room_no.required_if' => 'Each room must have a room number.',
        ]);

        try {
            DB::beginTransaction();

            // --- 步骤 1: 存储 Unit Table ---
            $unit = new Unit();
            $unit->id = (string) Str::ulid();
            $unit->property_id = $request->property_id;
            $unit->owner_id = $request->owner_id;
            $unit->unit_no = $request->unit_no;
            $unit->block = $request->block;
            $unit->floor = $request->floor;
            $unit->sqft = $request->sqft;
            $unit->electricity_acc_no = $request->electricity_acc_no;
            $unit->water_acc_no = $request->water_acc_no;
            $unit->status = $request->status;
            $unit->has_rooms = $request->has_rooms; 
            $unit->total_rooms = $request->has_rooms ? count($request->rooms) : 0;
            $unit->created_by = Auth::id();
            $unit->save();

            // --- 步骤 2: 处理 Unit 级别的 Assets (Common Area) ---
            // 对应你的逻辑：has_unit_asset == 1，room_id = null
            if ($request->has_unit_assets == 1 && isset($request->unit_assets)) {
                foreach ($request->unit_assets as $assetData) {
                    if ($assetData['qty'] > 0) {
                        RoomAsset::create([
                            'asset_id' => $assetData['id'],
                            'unit_id'  => $unit->id,
                            'room_id'  => null,
                            'quantity' => $assetData['qty'],
                            'condition' => 'Good',
                        ]);
                    }
                }
            }

            // --- 步骤 3: 处理 Rooms 和 Room 级别的 Assets ---
            if ($request->has_rooms && isset($request->rooms)) {
                foreach ($request->rooms as $roomIndex => $roomData) {
                    // 存储 Room
                    $room = new Room();
                    $room->id = (string) Str::ulid();
                    $room->unit_id = $unit->id;
                    $room->room_no = $roomData['room_no'];
                    $room->room_type = $roomData['room_type'];
                    $room->status = 'Vacant';
                    $room->save();

                    // 存储该 Room 下的 Assets
                    // 对应你的逻辑：room_id = 刚刚生成的id, unit_id = null
                    if (isset($roomData['assets'])) {
                        foreach ($roomData['assets'] as $assetData) {
                            if ($assetData['qty'] > 0) {
                                RoomAsset::create([
                                    'id'        => (string) Str::ulid(),
                                    'asset_id'  => $assetData['id'],
                                    'room_id'   => $room->id,
                                    'unit_id'   => null,
                                    'quantity'  => $assetData['qty'],
                                    'condition' => 'Good',
                                ]);
                            }
                        }
                    }
                }
            }

            DB::commit();
            return redirect()->route('admin.properties.show', ['property' => $request->property_id])
                 ->with('success', 'Unit created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Unit $unit)
    {
        $query = $unit->rooms()
            ->with([
                'unit.owner:id,user_id,company_name', 
                'unit.owner.user:id,name,email',
                'assets'
            ]);

        // 1. 处理搜索逻辑 (参考你之前的 room index)
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('room_no', 'like', "%{$search}%")
                ->orWhereHas('assets', function($aq) use ($search) {
                    $aq->where('name', 'like', "%{$search}%");
                });
            });
        }

        // 2. 处理排序逻辑
        $sort = $request->get('sort');
        switch ($sort) {
            case 'room_no_asc':
                $query->orderBy('room_no', 'asc');
                break;
            case 'room_no_desc':
                $query->orderBy('room_no', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            default:
                $query->orderBy('room_no', 'asc');
                break;
        }

        // 3. 获取处理后的房间列表
        // 注意：这里为了保持你的页面逻辑，我们直接通过 $unit->setRelation 加载过滤后的结果
        // 这样在 Blade 里调用 $unit->rooms 就会是过滤后的数据
        $rooms = $query->get();
        $unit->setRelation('rooms', $rooms);

        return view('adminSide.rooms.unit.show', compact('unit'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        // 预加载关联：获取 Unit、关联的 Rooms，以及所有 RoomAssets
        // 注意：RoomAsset 包含 unit_level 的（room_id 为 null）和 room_level 的
        $unit = Unit::with(['rooms', 'roomAssets'])->findOrFail($id);

        // 获取所有属性列表，方便在编辑页切换或显示
        $properties = Property::all();
        $targetProperty = Property::find($unit->property_id);

        // 获取业主资料
        $owners = Owners::with('user')->get();
        $hasRoomsCount = $unit->rooms()->count() > 0 ? 1 : 0;

        // 资产库
        $assetLibrary = Asset::select('id', 'name', 'user_id', 'status')->get();

        return view('adminSide.rooms.unit.edit', compact(
            'unit', 
            'properties', 
            'owners', 
            'targetProperty', 
            'assetLibrary',
            'hasRoomsCount'
        ));
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $unit = Unit::findOrFail($id);

        // 1. 验证数据
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'unit_no' => [
                'required',
                'string',
                Rule::unique('units')
                    ->where(fn ($query) => $query->where('property_id', $request->property_id))
                    ->ignore($unit->id) // 排除当前 unit 自身
            ],
            'owner_id'       => 'nullable|exists:owners,id',
            'management_fee' => 'nullable|numeric|min:0',
            'sqft'           => 'nullable|numeric|min:0',
            'has_rooms'      => 'required|Boolean',
        ]);

        try {
            DB::beginTransaction();

            // --- 仅更新 Unit 基本资料 ---
            // 使用 $request->only() 或明确列出字段，防止恶意注入
            $unit->update([
                'property_id'         => $request->property_id,
                'owner_id'            => $request->owner_id,
                'unit_no'             => $request->unit_no,
                'block'               => $request->block,
                'floor'               => $request->floor,
                'sqft'                => $request->sqft,
                'electricity_acc_no'  => $request->electricity_acc_no,
                'water_acc_no'        => $request->water_acc_no,
                'status'              => $request->status, // 别忘了 status 字段
                'has_rooms'           => $request->has_rooms,
            ]);

            // --- 步骤 2: 移除清理逻辑 ---
            // 既然 Edit 页面不处理 Room 和 Assets，这里绝对不能执行 delete()
            // 否则每次点 Save，房间资产就全没了。

            DB::commit();
            
            return redirect()->route('admin.properties.show', $request->property_id)
                            ->with('success', 'Unit updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Update Failed: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            // 1. 查找 Unit (确保它存在)
            $unit = Unit::findOrFail($id);

            // 2. 更新 Unit 自身状态为 Removed
            $unit->update(['status' => 'Removed']);

            // 3. 批量更新属于该 Unit 的所有 Rooms 状态为 Removed
            // 建议加上 where('status', '!=', 'Removed') 避免重复操作
            Room::where('unit_id', $id)->update(['status' => 'Removed']);

            DB::commit();
            
            return redirect()->back()
                ->with('success', 'Unit and all its rooms have been marked as removed.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error while removing unit: ' . $e->getMessage());
        }
    }

    public function restore($id)
    {
        try {
            DB::beginTransaction();

            // 1. 查找 Unit
            $unit = Unit::findOrFail($id);

            // 2. 恢复 Unit 自身状态为 Vacant
            $unit->update(['status' => 'Vacant']);

            // 3. 恢复该 Unit 下所有被标记为 Removed 的 Rooms
            // 这里特别加上 where('status', 'Removed') 是为了防止误触，只恢复之前被删掉的
            Room::where('unit_id', $id)
                ->where('status', 'Removed')
                ->update(['status' => 'Vacant']);

            DB::commit();
            return redirect()->back()->with('success', 'Unit and its rooms have been restored to Vacant.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }
}
