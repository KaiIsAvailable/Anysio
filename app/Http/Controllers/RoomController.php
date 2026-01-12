<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort   = $request->input('sort');

        $query = Room::query()
            ->with(['owner.user', 'assets'])
            ->withCount(['assets', 'leases']);

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
        $owners = \App\Models\Owners::with('user')->orderBy('created_at', 'desc')->get();
        return view('adminSide.rooms.create', compact('owners'));
    }

    public function store(Request $request)
    {
        $data = $this->validateRoomForm($request);

        DB::transaction(function () use ($data) {
            $room = Room::create([
                'owner_id'  => $data['owner_id'],
                'room_no'   => $data['room_no'],
                'room_type' => $data['room_type'],
                'status'    => $data['status'],
                'address'   => $data['address'],
            ]);

            $assets = $data['assets'] ?? [];
            foreach ($assets as $a) {
                if (!isset($a['name']) || trim((string) $a['name']) === '') {
                    continue;
                }

                // 如果用户打勾 delete，就当作不创建
                if (!empty($a['_delete'])) {
                    continue;
                }

                $room->assets()->create([
                    'name'             => $a['name'],
                    'condition'        => $a['condition'] ?? null,
                    'last_maintenance' => $a['last_maintenance'] ?? null,
                    'remark'           => $a['remark'] ?? null,
                ]);
            }
        });

        return redirect()->route('admin.rooms.index')->with('success', 'Room created successfully.');
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
        $room->load(['assets', 'owner.user']);
        $owners = \App\Models\Owners::with('user')->orderBy('created_at', 'desc')->get();

        return view('adminSide.rooms.edit', compact('room', 'owners'));
    }

    public function update(Request $request, Room $room)
    {
        $data = $this->validateRoomForm($request, true);

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

        return redirect()->route('admin.rooms.show', $room->id)->with('success', 'Room updated successfully.');
    }

    public function destroy(Room $room)
    {
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
        abort_unless($asset->room_id === $room->id, 404);
        $asset->delete();

        return back()->with('success', 'Asset deleted.');
    }

    // ===============================
    // Validation
    // ===============================

    private function validateRoomForm(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'owner_id'  => ['required', 'string'],
            'room_no'   => ['required', 'string', 'max:50'],
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
