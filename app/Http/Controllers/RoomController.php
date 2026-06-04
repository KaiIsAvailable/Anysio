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
    // 💡 新增一個專門的權限檢查方法，精準判斷 Agent 和 Owner 的權限
    private function checkUnitAccess($user, $unit)
    {
        if (Gate::allows('super-admin')) {
            return;
        }

        if ($user->role === 'agentAdmin' || $user->role === 'agent') {
            // Agent 必須負責管理這個 Unit 的 Owner
            $isManaging = Owners::where('agent_id', $user->id)
                                ->where('user_id', $unit->owner_id)
                                ->exists();
            if (!$isManaging) {
                abort(403, 'Unauthorized action: You do not manage the owner of this unit.');
            }
        } elseif ($user->role === 'ownerAdmin' || $user->role === 'owner') {
            if ($unit->owner_id !== $user->id) {
                abort(403, 'Unauthorized action: You do not own this unit.');
            }
        } else {
            abort(403, 'Unauthorized role.');
        }
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort   = $request->input('sort');

        $query = Room::query()
            ->with(['unit.owner', 'assets'])
            ->withCount(['assets', 'leases']);

        if (!Gate::allows('super-admin')) {
            $user = Auth::user();

            if ($user->role === 'owner' || $user->role === 'ownerAdmin') {
                $query->where('owner_id', $user->id);
            } elseif ($user->role === 'agent' || $user->role === 'agentAdmin') {
                $managedOwnerIds = Owners::where('agent_id', $user->id)
                                    ->pluck('user_id');

                if ($managedOwnerIds->isNotEmpty()) {
                    $query->whereIn('owner_id', $managedOwnerIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
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
                    ->orWhereHas('owner', function ($uq) use ($search) {
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
        // 💡 已經刪除 Gate::authorize('owner-admin');
        $user = Auth::user();

        $unitId = $request->query('unit_id');
        $unit = Unit::with(['property', 'owner'])->findOrFail($unitId);

        // 💡 使用新的權限檢查方法
        $this->checkUnitAccess($user, $unit);

        $assetLibrary = Asset::where('user_id', $unit->owner->id)
            ->orderBy('name', 'asc')
            ->get();

        return view('adminSide.rooms.create', compact('unit', 'assetLibrary'));
    }

    public function store(Request $request)
    {
        // 💡 已經刪除 Gate::authorize('owner-admin');
        $user = Auth::user();

        $data = $request->validate([
            'unit_id'   => 'required|exists:units,id',
            'room_no'   => 'required|string|max:255',
            'room_type' => 'required|string|max:255',
            'status'    => 'required|',
            'address'   => 'nullable|string',
            'assets'    => 'nullable|array',
            'assets.*.id'  => 'required_with:assets|exists:assets,id',
            'assets.*.qty' => 'required_with:assets|integer|min:0',
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        
        // 💡 使用新的權限檢查方法
        $this->checkUnitAccess($user, $unit);

        DB::transaction(function () use ($data) {
            $room = Room::create([
                'unit_id'   => $data['unit_id'],
                'room_no'   => $data['room_no'],
                'room_type' => $data['room_type'],
                'status'    => $data['status'],
                'created_by'=> Auth::id(),
            ]);

            if (!empty($data['assets'])) {
                foreach ($data['assets'] as $assetData) {
                    $quantity = (int)$assetData['qty'];

                    if ($quantity > 0) {
                        RoomAsset::create([
                            'asset_id'         => $assetData['id'],
                            'room_id'          => $room->id,
                            'unit_id'          => null,
                            'quantity'         => $quantity,
                            'condition'        => 'Good', 
                            'last_maintenance' => null,
                            'remark'           => null,
                        ]);
                    }
                }
            }

            // 触发同步
            $unit = $room->unit; // 获取所属单位
            $unit->unsetRelation('rooms'); // 清除缓存，确保能读到刚插入的这间房
            $unit->syncStatus();
        });

        return redirect()->route('admin.units.show', $data['unit_id'])
                        ->with('success', 'Room and assets created successfully.');
    }

    public function show(Room $room)
    {
        $room->load(['unit.property', 'unit.owner', 'assets', 'leases.tenant.user']);

        $property = $room->unit->property;
        $fullAddress = "{$property->address}, {$property->postcode} {$property->city}, {$property->state}";

        $tenantsById = $room->leases->map(fn($lease) => $lease->tenant)
                                    ->filter()
                                    ->keyBy('id')
                                    ->all();

        return view('adminSide.rooms.show', compact('room', 'tenantsById', 'fullAddress'));
    }

    public function edit(Room $room)
    {
        // 💡 已經刪除 Gate::authorize('owner-admin');
        $user = Auth::user();

        $room->load(['unit.property', 'unit.owner']);
        $unit = $room->unit;

        // 💡 使用新的權限檢查方法
        $this->checkUnitAccess($user, $unit);

        $assetLibrary = Asset::where('user_id', $unit->owner->id)
            ->orderBy('name', 'asc')
            ->get();

        $currentAssets = RoomAsset::where('room_id', $room->id)
            ->where('status', 'Active')
            ->pluck('quantity', 'asset_id')
            ->toArray();

        return view('adminSide.rooms.edit', compact('room', 'unit', 'assetLibrary', 'currentAssets'));
    }

    public function update(Request $request, Room $room)
    {
        // 💡 已經刪除 Gate::authorize('owner-admin');
        $user = Auth::user();

        // 💡 使用新的權限檢查方法
        $this->checkUnitAccess($user, $room->unit);

        $data = $request->validate([
            'room_no'   => 'required|string|max:255',
            'room_type' => 'required|string|max:255',
            'status'    => 'required|in:VACANT,OCCUPIED,MAINTENANCE',
            'assets'       => 'nullable|array',
            'assets.*.id'  => 'required_with:assets|exists:assets,id',
            'assets.*.qty' => 'required_with:assets|integer|min:0',
        ]);

        DB::transaction(function () use ($data, $room) {
            $room->update([
                'room_no'   => $data['room_no'],
                'room_type' => $data['room_type'],
                'status'    => $data['status'],
            ]);

            RoomAsset::where('room_id', $room->id)
                ->where('status', 'Active')
                ->update(['status' => 'Inactive']);

            if (!empty($data['assets'])) {
                foreach ($data['assets'] as $assetData) {
                    $quantity = (int)$assetData['qty'];

                    if ($quantity > 0) {
                        RoomAsset::create([
                            'room_id'          => $room->id,
                            'asset_id'         => $assetData['id'],
                            'quantity'         => $quantity,
                            'condition'        => 'Good',
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
        // 💡 已經刪除 Gate::authorize('owner-admin');
        $user = Auth::user();

        // 💡 使用新的權限檢查方法
        $this->checkUnitAccess($user, $room->unit);

        DB::transaction(function () use ($room) {
            RoomAsset::where('room_id', $room->id)
                ->where('status', 'Active')
                ->update(['status' => 'Inactive']);
            $room->update(['status' => 'Inactive']);
        });

        return redirect()->route('admin.units.show', $room->unit_id)->with('success', 'Room marked Inactive and related assets preserved.');
    }

    public function restore(Room $room)
    {
        // 💡 已經刪除 Gate::authorize('owner-admin');
        $user = Auth::user();

        // 💡 使用新的權限檢查方法
        $this->checkUnitAccess($user, $room->unit);

        DB::transaction(function () use ($room) {
            $room->update(['status' => 'Vacant']);
            RoomAsset::where('room_id', $room->id)
                ->where('status', 'Inactive')
                ->update(['status' => 'Active']);
        });

        return redirect()->route('admin.units.show', $room->unit_id)->with('success', 'Room has been restored successfully.');
    }

    // ===============================
    // Assets actions (optional)
    // ===============================

    public function assetStore(Request $request, Room $room)
    {
        $user = Auth::user();
        $this->checkUnitAccess($user, $room->unit);

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
        $user = Auth::user();
        $this->checkUnitAccess($user, $room->unit);

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
        $user = Auth::user();
        $this->checkUnitAccess($user, $room->unit);

        abort_unless($asset->room_id === $room->id, 404);
        $asset->update(['status' => 'inactive']);

        return back()->with('success', 'Asset marked inactive.');
    }

    // ===============================
    // Validation
    // ===============================

    private function validateRoomForm(Request $request, bool $isUpdate = false, ?Room $room = null): array
    {
        $roomNoRule = $isUpdate
            ? Rule::unique('rooms', 'room_no')->ignore($room?->id)
            : Rule::unique('rooms', 'room_no');

        return $request->validate([
            'room_no'   => ['required', 'string', 'max:50', $roomNoRule],
            'room_type' => ['required', 'string', 'max:100'],
            'status'    => ['required', Rule::in(['OCCUPIED', 'VACANT', 'MAINTENANCE', 'AVAILABLE'])],
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