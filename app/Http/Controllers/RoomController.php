<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\Owners;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort   = $request->input('sort');

        $query = Room::query()
            ->with(['owner.user', 'assets'])
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

    public function create()
    {
        Gate::authorize('owner-admin');
    
        $owners = Owners::with('user')->orderBy('created_at', 'desc')->get();

        // 筛选只属于当前登录 Admin 的资产库
        if (Gate::allows('super-admin')) {
            $assetLibrary = Asset::orderBy('name', 'asc')->get();
        } else {
            $assetLibrary = Asset::where('user_id', Auth::id())
                                ->orderBy('name', 'asc')
                                ->get();
        }

        return view('adminSide.rooms.create', compact('owners', 'assetLibrary'));
    }

    public function store(Request $request)
    {
        Gate::authorize('owner-admin');
        
        // 记得更新 validateRoomForm，确保 assets.*.name 是必须的字符串
        $data = $this->validateRoomForm($request);

        DB::transaction(function () use ($data) {
            // 1. 创建房间
            $room = Room::create([
                'owner_id'  => $data['owner_id'],
                'room_no'   => $data['room_no'],
                'room_type' => $data['room_type'],
                'status'    => $data['status'],
                'address'   => $data['address'],
            ]);

            $assets = $data['assets'] ?? [];
            foreach ($assets as $a) {
                // 跳过空值或标记删除的项
                if (!isset($a['name']) || trim((string) $a['name']) === '' || !empty($a['_delete'])) {
                    continue;
                }

                // 2. 关键逻辑：firstOrCreate
                // 在资产字典表里找这个名字，如果没有就为当前登录用户创建一个新的
                $assetName = Str::upper(trim((string) $a['name']));
                $owner = Owners::findOrFail($data['owner_id']);
                $asset = Asset::firstOrCreate(
                    [
                        'name'    => $assetName, 
                        'user_id' => $owner->user_id,// 锁定资产属于当前 Admin/User
                    ]
                );

                // 3. 建立多对多关联 (使用 attach)
                $room->assets()->attach($asset->id, [
                    'id'               => (string) Str::ulid(), // 手动生成中间表 ULID
                    'condition'        => $a['condition'] ?? 'Good',
                    'last_maintenance' => $a['last_maintenance'] ?? null,
                    'remark'           => $a['remark'] ?? null,
                ]);
            }
        });

        return redirect()->route('admin.rooms.index')->with('success', 'Room and assets created successfully.');
    }

    public function show(Room $room)
    {
        $room->load(['owner.user', 'assets', 'leases']);

        $tenantsById = [];
        if ($room->leases->count() > 0) {
            $tenantIds = $room->leases->pluck('tenant_id')->filter()->unique()->values();
            if ($tenantIds->count() > 0) {
                $tenantsById = \App\Models\Tenants::with('user')
                    ->whereIn('id', $tenantIds)
                    ->get()
                    ->keyBy('id')
                    ->all();
            }
        }

        return view('adminSide.rooms.show', compact('room', 'tenantsById'));
    }

    public function edit(Room $room)
    {
        Gate::authorize('owner-admin');
        $room->load(['assets', 'owner.user']);
        $owners = \App\Models\Owners::with('user')->orderBy('created_at', 'desc')->get();

        return view('adminSide.rooms.edit', compact('room', 'owners'));
    }

    public function update(Request $request, Room $room)
    {
        Gate::authorize('owner-admin');
        $data = $this->validateRoomForm($request, true, $room);

        DB::transaction(function () use ($data, $room) {
            $room->update([
                'owner_id'  => $data['owner_id'],
                'room_no'   => $data['room_no'],
                'room_type' => $data['room_type'],
                'status'    => $data['status'],
                'address'   => $data['address'],
            ]);

            $assets = $data['assets'] ?? [];

            foreach ($assets as $a) {
                // 空行跳过
                $name = isset($a['name']) ? trim((string) $a['name']) : '';
                $isDelete = !empty($a['_delete']);

                // 有 id：更新/删除现有
                if (!empty($a['id'])) {
                    $asset = $room->assets()->where('id', $a['id'])->first();

                    // asset 不属于这个 room 就忽略（或你也可以 abort）
                    if (!$asset) {
                        continue;
                    }

                    if ($isDelete) {
                        $asset->delete();
                        continue;
                    }

                    // name 为空时不允许把已有 asset 更新成空（这里选择跳过）
                    if ($name === '') {
                        continue;
                    }

                    $asset->update([
                        'name'             => $name,
                        'condition'        => $a['condition'] ?? null,
                        'last_maintenance' => $a['last_maintenance'] ?? null,
                        'remark'           => $a['remark'] ?? null,
                    ]);

                    continue;
                }

                // 无 id：新增
                if ($isDelete) {
                    continue; // 新增行打勾 delete 就忽略
                }
                if ($name === '') {
                    continue;
                }

                $room->assets()->create([
                    'name'             => $name,
                    'condition'        => $a['condition'] ?? null,
                    'last_maintenance' => $a['last_maintenance'] ?? null,
                    'remark'           => $a['remark'] ?? null,
                ]);
            }
        });

        return redirect()->route('admin.rooms.index')->with('success', 'Room updated successfully.');
    }

    public function destroy(Room $room)
    {
        Gate::authorize('owner-admin');
        DB::transaction(function () use ($room) {
            $room->assets()->delete();
            $room->delete();
        });

        return redirect()->route('admin.rooms.index')->with('success', 'Room deleted successfully.');
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
            'owner_id'  => ['required', 'string'],
            'room_no'   => ['required', 'string', 'max:50', $roomNoRule],
            'room_type' => ['required', 'string', 'max:100'],
            'status'    => ['required', Rule::in(['Occupied', 'Vacant', 'Maintenance', 'Available'])],
            'address'   => ['required', 'string', 'max:255'],

            'assets'                        => ['nullable', 'array'],
            'assets.*.id'                   => ['nullable', 'string'],
            'assets.*.name'                 => ['nullable', 'string', 'max:255'],
            'assets.*.condition'            => ['nullable', Rule::in(['Good', 'Broken', 'Maintaining'])],
            'assets.*.last_maintenance'     => ['nullable', 'date', 'before_or_equal:today'],
            'assets.*.remark'               => ['nullable', 'string', 'max:1000'],
            'assets.*._delete'              => ['nullable', 'boolean'],
        ]);
    }
}
