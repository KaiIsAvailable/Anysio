<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RefCodePackage;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function index(): View
    {
        // 只获取状态为 active 的套餐
        $packages = RefCodePackage::where('status', 'active')->get();

        return view('welcome', compact('packages'));
    }
}
