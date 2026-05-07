<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException; // 加上这个更规范
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 修改这里
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            // 如果是 AJAX 请求，返回状态码让前端知道 Session 挂了
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired.'], 419);
            }

            // 如果是普通页面请求，跳转回登录页
            return redirect()->route('login')->with('message', 'Session expired, please login again.');
        });
    })->create();
