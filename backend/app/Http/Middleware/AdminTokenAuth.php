<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AdminTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('admin.token', '');
        $provided = (string) $request->header('X-Admin-Token', '');

        if ($configured === '' || $provided === '' || !Hash::equals(sha1($configured), sha1($provided))) {
            return response()->json(['error' => '未授权访问，请先登录管理员账号'], 401);
        }

        return $next($request);
    }
}
