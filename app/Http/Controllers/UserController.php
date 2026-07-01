<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
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
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function verifyPassword(Request $request)
    {
        // 1. 检查 Gate 权限
        if (!Gate::allows('super-admin')) {
            return response()->json(['message' => 'Unauthorized: 仅限超级管理员'], 403);
        }

        // 2. 获取当前认证的用户 (不管它是 web 还是 admin)
        // auth()->user() 会自动匹配当前 session 登录的那个 Guard
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => '未授权的请求'], 401);
        }

        // 3. 手动校验密码
        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid password'], 422);
        }

        return response()->json([
            'success' => true, 
            'message' => '验证成功'
        ]);
    }
}
