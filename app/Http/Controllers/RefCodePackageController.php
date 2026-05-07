<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RefCodePackage;

class RefCodePackageController extends Controller
{
    public function index(Request $request)
    {
        $query = RefCodePackage::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('ref_code', 'like', '%' . $request->search . '%');
        }

        $packages = $query->orderBy('ref_code')->get();

        return view('adminSide.packages.index', compact('packages'));
    }

    public function create(Request $request)
    {
        return view('adminSide.packages.create');
    }

    public function store(Request $request)
    {
        // 1. 验证数据
        $request->validate([
            'name' => 'required|string|max:255|unique:ref_code_packages,name',
            'ref_code' => 'required|string|unique:ref_code_packages,ref_code',
            'price_mode' => 'required|in:MONTHLY,YEARLY',
            'comm_type' => 'required|in:percentage,fixed',
            // 验证逻辑：选哪个就必填哪个
            'commission_display' => 'required_if:comm_type,percentage|nullable|numeric|min:0',
            'price_display' => 'required_if:comm_type,fixed|nullable|numeric|min:0',
            
            'base_lease' => 'required|integer|min:0|max:99999999999',
            'add_on_price_display' => 'required|numeric|min:0',
            'add_on_limit' => 'required|integer|min:0',
        ]);

        // 2. 准备入库数据
        $data = [
            'name' => toupper($request->name),
            'ref_code' => toupper($request->ref_code),
            'price_mode' => $request->price_mode,
            'base_lease' => $request->base_lease,
            'max_lease_limit' => $request->add_on_limit,
            'extra_lease_price' => $request->add_on_price_display * 100,
        ];

        // 3. 根据 Revenue Model 决定 Price 的性质
        if ($request->comm_type === 'percentage') {
            // 如果是 1%，price 存 0 或者 null，commission_rate 存 1.00
            $data['price'] = 0;
            $data['commission_rate'] = $request->commission_display * 100;
        } else {
            // 如果是固定 RM 100，price 存 100，commission_rate 存 null
            $data['price'] = $request->price_display * 100;
            $data['commission_rate'] = 0;
        }

        RefCodePackage::create($data);

        return redirect()->route('admin.packages.index')
            ->with('success', "Package Created Successfully.");
    }

    public function edit($id)
    {
        $package = RefCodePackage::findOrFail($id);
        
        // 如果你数据库存的是分，这里除以 100 转换回 RM 显示给用户
        // $package->price_display = $package->price / 100; 

        return view('adminSide.packages.edit', compact('package'));
    }

    public function update(Request $request, $id)
    {
        $package = RefCodePackage::findOrFail($id);

        // 1. 验证数据 (注意：unique 验证需要排除当前 ID)
        $request->validate([
            'name' => 'required|string|max:255|unique:ref_code_packages,name,' . $package->id,
            'price_mode' => 'required|in:MONTHLY,YEARLY',
            'comm_type' => 'required|in:percentage,fixed',
            
            // 验证逻辑：选哪个就必填哪个
            'commission_display' => 'required_if:comm_type,percentage|nullable|numeric|min:0',
            'price_display' => 'required_if:comm_type,fixed|nullable|numeric|min:0',
            
            'base_lease' => 'required|integer|min:0|max:99999999999',
            'add_on_price_display' => 'required|numeric|min:0',
            'add_on_limit' => 'required|integer|min:0',
        ]);

        // 2. 准备更新数据 (保持和 store 一样的倍增逻辑)
        $data = [
            'name' => strtoupper($request->name),
            'price_mode' => $request->price_mode,
            'base_lease' => $request->base_lease,
            'max_lease_limit' => $request->add_on_limit,
            'extra_lease_price' => $request->add_on_price_display * 100, // 存入分为单位
        ];

        // 3. 根据 Revenue Model 决定 Price 的性质 (逻辑与 store 镜像)
        if ($request->comm_type === 'percentage') {
            // 如果切换到了百分比模式，清空固定价格
            $data['price'] = 0;
            $data['commission_rate'] = $request->commission_display * 100; // 存入分为单位
        } else {
            // 如果切换到了固定金额模式，清空百分比
            $data['price'] = $request->price_display * 100; // 存入分为单位
            $data['commission_rate'] = 0;
        }

        // 4. 执行更新
        $package->update($data);

        return redirect()->route('admin.packages.index')
            ->with('success', "Package Updated Successfully.");
    }
}
