<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('admin.token', '');
        $provided = (string) $request->header('X-Admin-Token', '');

        // 使用 PHP 原生的 hash_equals 进行时序安全比较
        // （Hash::equals 不存在，调用会抛出异常导致 500）
        if ($configured === '' || $provided === '' || !hash_equals($configured, $provided)) {
            return response()->json(['error' => '未授权访问，请先登录管理员账号'], 401);
        }

        return $next($request);
    }
}
