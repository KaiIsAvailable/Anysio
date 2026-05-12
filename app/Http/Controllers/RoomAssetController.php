<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Models\RoomAsset;
use App\Models\User;
use App\Models\Asset;
use Illuminate\Validation\Rule;
use App\Models\Unit;
use App\Models\Room;
use App\Models\Owners;

class RoomAssetController extends Controller
{
    public function index(Request $request) // 记得注入 Request
    {
        $user = Auth::user();
        $search = $request->input('search');

        // 1. 初始化查询并预加载 user 关联
        $query = Asset::with(['user']);

        // 2. 权限过滤逻辑
        if ($user->role === 'agentAdmin') {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('agent_id', $user->id);
            });
        } else if ($user->role === 'ownerAdmin') {
            $query->where('user_id', $user->id);
        }

        // 3. 搜索逻辑实现
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")       // 搜索资产名称
                ->orWhere('category', 'like', "%{$search}%") // 搜索资产类别
                ->orWhereHas('user', function ($u) use ($search) { // 搜索创建者姓名
                    $u->where('name', 'like', "%{$search}%");
                });
            });
        }

        // 4. 排序并分页
        $assets = $query->latest()->paginate(10)->withQueryString(); 
        // withQueryString() 确保翻页时搜索关键词不丢失

        return view('adminSide.rooms.roomAsset.index', compact('assets'));
    }

    public function create(Request $request)
    {
        Gate::authorize('owner-admin');

        $users = User::whereIn('role', ['ownerAdmin', 'owner', 'agentAdmin'])->get();
        $assetLibrary = Asset::select('name', 'category', 'status')
            ->distinct()
            ->get();
        $selectedUserId = $request->query('user_id');

        if (!$selectedUserId && Auth::user()->role === 'ownerAdmin') {
            $selectedUserId = Auth::id();
        }

        return view('adminSide.rooms.roomAsset.create', compact(
            'users', 
            'assetLibrary', 
            'selectedUserId' 
        ));
    }

    public function store(Request $request)
    {
        $targetUserId = $request->input('target_user_id');

        $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'assets' => 'required|array|min:1',
            'assets.*.name' => [
                'required',
                'string',
                'max:255',
                // 核心逻辑：复合唯一验证
                Rule::unique('assets', 'name')->where(function ($query) use ($targetUserId) {
                    return $query->where('user_id', $targetUserId);
                }),
            ],
            'assets.*.category' => 'required|string',
        ], [
            // 自定义错误提示
            'assets.*.name.unique' => 'This user already has item named ":input". Please change an item ',
        ]);

        // 验证通过后的逻辑...
        DB::transaction(function () use ($request, $targetUserId) {
            foreach ($request->assets as $assetData) {
                Asset::create([
                    'user_id'  => $targetUserId,
                    'name'     => $assetData['name'],
                    'category' => $assetData['category'],
                ]);
            }
        });

        return redirect()->route('admin.roomAsset.index')->with('success', 'New Asset Created Successfully!');
    }

    public function edit($id)
    {
        // 1. 找到这条资产，如果找不到直接报 404
        // 建议用 with('user') 顺便把所属人拿出来显示，UI 更好看
        $asset = Asset::with('user')->findOrFail($id);

        // 2. 返回 edit.blade.php，并把 $asset 传过去
        return view('adminSide.rooms.roomAsset.edit', compact('asset'));
    }

    public function update(Request $request, $id)
    {
        // 1. 先找到这条要修改的数据
        $asset = Asset::findOrFail($id);
        $targetUserId = $asset->user_id; // 锁定当前资产所属的用户

        // 2. 执行验证
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                // 核心：在 unique 验证中排除当前的 $id
                Rule::unique('assets', 'name')
                    ->where(function ($query) use ($targetUserId) {
                        return $query->where('user_id', $targetUserId);
                    })
                    ->ignore($id), // 关键：忽略掉自己，避免“自己跟自己重复”的报错
            ],
            'category' => 'required|string',
        ], [
            // 自定义错误提示
            'name.unique' => 'This user already has an item named ":input".',
        ]);

        // 3. 更新数据
        $asset->update([
            'name' => $request->name,
            'category' => $request->category,
        ]);

        return redirect()->route('admin.roomAsset.index')->with('success', 'Asset Updated Successfully!');
    }

    // 软删除资产及其在所有房间的记录
    public function destroy($id)
    {
        Gate::authorize('owner-admin');

        DB::beginTransaction();
        try {
            // 1. 更新主资产表
            $asset = Asset::findOrFail($id);
            $asset->update(['status' => 'Inactive']);

            // 2. 更新关联的所有房间资产记录
            DB::table('asset_room')
                ->where('asset_id', $id)
                ->update(['status' => 'Inactive']);

            DB::commit();
            return redirect()->back()->with('success', 'Asset and its records marked as Inactive.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to remove asset: ' . $e->getMessage());
        }
    }

    // 恢复资产及其在房间的记录
    public function restore($id)
    {
        Gate::authorize('owner-admin');

        DB::beginTransaction();
        try {
            // 1. 恢复主资产表
            $asset = Asset::findOrFail($id);
            $asset->update(['status' => 'Active']);

            // 2. 仅恢复那些之前被设为 Inactive 的房间关联记录
            DB::table('asset_room')
                ->where('asset_id', $id)
                ->where('status', 'Inactive') 
                ->update(['status' => 'Active']);

            DB::commit();
            return redirect()->back()->with('success', 'Asset has been successfully restored.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Restore failed: ' . $e->getMessage());
        }
    }
}
