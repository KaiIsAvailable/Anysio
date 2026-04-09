<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\Owners;
use App\Models\Asset;
use App\Models\Tenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use App\Models\Unit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort   = $request->input('sort');

        $query = Room::query()
            ->with(['unit.owner.user', 'assets'])
            ->withCount(['assets', 'leases']);

        if (!Gate::allows('super-admin')) {
            $user = Auth::user();

            if ($user->role === 'owner' || $user->role === 'ownerAdmin') {
                // 如果是房东，只看自己的房
                $ownerId = $user->owner?->id;
                if ($ownerId) {
                    $query->where('owner_id', $ownerId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($user->role === 'agent' || $user->role === 'agentAdmin') {
                // 如果是 Agent，看他名下所有 Owner 的房
                // 假设 Owners 表里有 agent_user_id 或者是通过关联获取
                $managedOwnerIds = Owners::where('agent_id', $user->id)
                                    ->pluck('id');

                if ($managedOwnerIds->isNotEmpty()) {
                    $query->whereIn('owner_id', $managedOwnerIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
                // 其他角色（如 Tenant）默认不给看，或者根据你的需求调整
                $query->whereRaw('1 = 0');
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('room_no', 'like', "%{$search}%")
                    ->orWhere('room_type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhereHas('assets', function ($aq) use ($search) {
                        $aq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('owner.user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

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
            default:
                $query->orderBy('created_at', 'asc');
                break;
        }

        $rooms = $query->paginate(10)->appends($request->query());

        return view('adminSide.rooms.index', compact('rooms'));
    }

    public function create(Request $request)
    {
        Gate::authorize('owner-admin');
        $user = Auth::user();

        // 1. 获取当前要关联的 Unit
        // 使用 failIf，确保如果 unit_id 不存在或不合法，直接报错
        $unitId = $request->query('unit_id');
        $unit = Unit::with(['property', 'owner.user'])->findOrFail($unitId);

        // 2. 安全检查：如果不是超级管理员，检查该 Unit 是否属于该 Agent
        if (!Gate::allows('super-admin')) {
            if ($unit->agent_id !== $user->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        // 3. 获取资产库 (逻辑保持不变)
        // 根据该 Unit 所属的 Owner 过滤资产库，这样推荐更精准
        $assetLibrary = Asset::where('user_id', $unit->owner->user_id)
            ->orderBy('name', 'asc')
            ->get();

        // 返回视图，把 $unit 传过去
        return view('adminSide.rooms.create', compact('unit', 'assetLibrary'));
    }

    public function store(Request $request)
    {
        Gate::authorize('owner-admin');

        // 1. 验证数据
        $data = $request->validate([
            'unit_id'   => 'required|exists:units,id',
            'room_no'   => 'required|string|max:255',
            'room_type' => 'required|string|max:255',
            'status'    => 'required|in:Vacant,Occupied,Maintenance',
            'address'   => 'nullable|string',
            
            // 匹配前端的 assets[index][id] 和 assets[index][qty]
            'assets'    => 'nullable|array',
            'assets.*.id'  => 'required_with:assets|exists:assets,id',
            'assets.*.qty' => 'required_with:assets|integer|min:0',
        ]);

        // 2. 数据库事务处理
        DB::transaction(function () use ($data) {
            // 创建 Room
            $room = Room::create([
                'unit_id'   => $data['unit_id'],
                'room_no'   => $data['room_no'],
                'room_type' => $data['room_type'],
                'status'    => $data['status'],
            ]);

            // 3. 处理 Assets (仅处理数量大于 0 的)
            if (!empty($data['assets'])) {
                foreach ($data['assets'] as $assetData) {
                    $quantity = (int)$assetData['qty'];

                    // 只有当数量大于 0 时才创建关联记录
                    if ($quantity > 0) {
                        RoomAsset::create([
                            'asset_id'         => $assetData['id'], // 直接使用前端传来的 ID
                            'room_id'          => $room->id,
                            'unit_id'          => null, // 如果 RoomAsset 需要 unit_id 也可以填入
                            'quantity'         => $quantity,
                            'condition'        => 'Good', 
                            'last_maintenance' => null,
                            'remark'           => null,
                        ]);
                    }
                }
            }
        });

        // 4. 重定向
        return redirect()->route('admin.units.show', $data['unit_id'])
                        ->with('success', 'Room and assets created successfully.');
    }

    public function show(Room $room)
    {
        $room->load(['unit.property', 'unit.owner.user', 'assets', 'leases']);
        $property = $room->unit->property;
        $fullAddress = "{$property->address}, {$property->postcode} {$property->city}, {$property->state}";

        $tenantsById = [];
        if ($room->leases->count() > 0) {
            $tenantIds = $room->leases->pluck('tenant_id')->filter()->unique()->values();
            if ($tenantIds->count() > 0) {
                $tenantsById = Tenants::with('user')
                    ->whereIn('id', $tenantIds)
                    ->get()
                    ->keyBy('id')
                    ->all();
            }
        }

        return view('adminSide.rooms.show', compact('room', 'tenantsById', 'fullAddress'));
    }

    public function edit(Room $room)
    {
        Gate::authorize('owner-admin');
        $user = Auth::user();

        // 1. 加载 Unit 及其关联信息（用于页面展示）
        $room->load(['unit.property', 'unit.owner.user']);
        $unit = $room->unit;

        // 2. 安全检查：确保当前用户有权限编辑这个房间所属的 Unit
        if (!Gate::allows('super-admin')) {
            if ($unit->agent_id !== $user->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        // 3. 获取该房东名下的资产库
        $assetLibrary = Asset::where('user_id', $unit->owner->user_id)
            ->orderBy('name', 'asc')
            ->get();

        // 4. 获取当前房间已经存在的资产及其数量映射 [asset_id => quantity]
        // 假设你的关联表模型是 RoomAsset
        $currentAssets = RoomAsset::where('room_id', $room->id)
            ->pluck('quantity', 'asset_id')
            ->toArray();

        return view('adminSide.rooms.edit', compact('room', 'unit', 'assetLibrary', 'currentAssets'));
    }

    public function update(Request $request, Room $room)
    {
        Gate::authorize('owner-admin');

        // 1. 验证数据
        $data = $request->validate([
            'room_no'   => 'required|string|max:255',
            'room_type' => 'required|string|max:255',
            'status'    => 'required|in:Vacant,Occupied,Maintenance',
            
            // 资产验证逻辑与 store 一致
            'assets'       => 'nullable|array',
            'assets.*.id'  => 'required_with:assets|exists:assets,id',
            'assets.*.qty' => 'required_with:assets|integer|min:0',
        ]);

        // 2. 数据库事务
        DB::transaction(function () use ($data, $room) {
            // 更新 Room 基础信息
            $room->update([
                'room_no'   => $data['room_no'],
                'room_type' => $data['room_type'],
                'status'    => $data['status'],
            ]);

            // 3. 处理 Assets 更新 (采用 "删除并重新插入" 策略，最简单稳健)
            // 首先移除该房间所有旧的资产关联
            RoomAsset::where('room_id', $room->id)->delete();

            // 重新插入前端提交的、数量大于 0 的资产
            if (!empty($data['assets'])) {
                foreach ($data['assets'] as $assetData) {
                    $quantity = (int)$assetData['qty'];

                    if ($quantity > 0) {
                        RoomAsset::create([
                            'room_id'          => $room->id,
                            'asset_id'         => $assetData['id'],
                            'quantity'         => $quantity,
                            'condition'        => 'Good', // 默认 Good，或从前端传值
                            'last_maintenance' => null,
                            'remark'           => 'Updated via room edit',
                        ]);
                    }
                }
            }
        });

        return redirect()->route('admin.units.show', $room->unit_id)
                        ->with('success', 'Room and assets updated successfully.');
    }

    public function destroy(Room $room)
    {
        Gate::authorize('owner-admin');
        DB::transaction(function () use ($room) {
            $room->assets()->delete();
            $room->delete();
        });

        return redirect()->route('admin.units.show', $room->unit_id)->with('success', 'Room deleted successfully.');
    }

    // ===============================
    // Assets actions (optional)
    // ===============================

    public function assetStore(Request $request, Room $room)
    {
        Gate::authorize('owner-admin');
        $payload = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'condition'        => ['nullable', Rule::in(['Good', 'Broken', 'Maintaining'])],
            'last_maintenance' => ['nullable', 'date', 'before_or_equal:today'],
            'remark'           => ['nullable', 'string', 'max:1000'],
        ]);

        $room->assets()->create($payload);

        return back()->with('success', 'Asset added.');
    }

    public function assetUpdate(Request $request, Room $room, RoomAsset $asset)
    {
        Gate::authorize('owner-admin');
        abort_unless($asset->room_id === $room->id, 404);

        $payload = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'condition'        => ['nullable', Rule::in(['Good', 'Broken', 'Maintaining'])],
            'last_maintenance' => ['nullable', 'date', 'before_or_equal:today'],
            'remark'           => ['nullable', 'string', 'max:1000'],
        ]);

        $asset->update($payload);

        return back()->with('success', 'Asset updated.');
    }

    public function assetDestroy(Room $room, RoomAsset $asset)
    {
        Gate::authorize('owner-admin');
        abort_unless($asset->room_id === $room->id, 404);
        $asset->delete();

        return back()->with('success', 'Asset deleted.');
    }

    // ===============================
    // Validation
    // ===============================

    private function validateRoomForm(Request $request, bool $isUpdate = false, ?Room $room = null): array
    {
        Gate::authorize('owner-admin');
        $roomNoRule = $isUpdate
            ? Rule::unique('rooms', 'room_no')->ignore($room?->id)
            : Rule::unique('rooms', 'room_no');

        return $request->validate([
            'room_no'   => ['required', 'string', 'max:50', $roomNoRule],
            'room_type' => ['required', 'string', 'max:100'],
            'status'    => ['required', Rule::in(['Occupied', 'Vacant', 'Maintenance', 'Available'])],
            'address'   => ['required', 'string', 'max:255'],

            'assets'                        => ['nullable', 'array'],
            'assets.*.id'                   => ['nullable', 'string'],
            'assets.*.name'                 => ['nullable', 'string', 'max:255'],
            'assets.*.quantity'             => 'required|integer|min:1',
            'assets.*.condition'            => ['nullable', Rule::in(['Good', 'Broken', 'Maintaining'])],
            'assets.*.last_maintenance'     => ['nullable', 'date', 'before_or_equal:today'],
            'assets.*.remark'               => ['nullable', 'string', 'max:1000'],
            'assets.*._delete'              => ['nullable', 'boolean'],
        ]);
    }
}
