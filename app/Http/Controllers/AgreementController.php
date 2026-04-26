<?php

namespace App\Http\Controllers;
use App\Models\Owners;
use App\Models\Agreements;
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;

class AgreementController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $query = Agreements::with(['owner.user']);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                ->orWhere('version', 'like', "%{$search}%");
            });
        }

        $agreements = $query->latest()->paginate(10);

        // 关键修正：因为 $agreements 是一个集合，需要循环处理每一项
        $agreements->getCollection()->transform(function ($agreement) {
            // 将 3 个或以上的换行符缩减为 2 个，保持段落感但去除冗余空白
            $agreement->content = preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", $agreement->content);
            return $agreement;
        });

        return view('adminSide.setting.agreement.index', compact('agreements'));
    }

    public function create()
    {
        // 1. 抓取所有房东数据，只需要 ID 和 Name 即可
        $user = Auth::user();
        $isOwnerAgentAdmin = false;
        $owners = Owners::with('user:id,name')->get(['id', 'user_id']);

        if ($user->role === 'ownerAdmin' || $user->role === 'agentAdmin'){
            $isOwnerAgentAdmin = true;
        }

        // 2. 用 compact 把变量传给视图
        return view('adminSide.setting.agreement.create', compact('owners', 'isOwnerAgentAdmin'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'owner_id' => 'nullable|exists:owners,id', // 只有 Lease Agreement 才会有这个
            'title' => 'required|string|max:255',
            'version' => 'nullable|string',
            'content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        // 如果类型是 TOS，强制把 owner_id 设为 null，防止前端传错
        if ($validated['type'] === 'tos') {
            $validated['owner_id'] = null;
        }

        Agreements::create($validated);

        return redirect()->route('admin.agreements.index')->with('success', 'Agreement created successfully!');
    }
}
