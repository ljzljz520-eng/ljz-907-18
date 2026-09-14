<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\ReviewController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/movies', [MovieController::class, 'index']);
Route::get('/movies/{id}', [MovieController::class, 'show']);
Route::post('/upload', [MovieController::class, 'upload']);
Route::get('/proxy-image', [MovieController::class, 'proxyImage']);

/*
|--------------------------------------------------------------------------
| 公益放映反馈（观后留言 + 评分）
|--------------------------------------------------------------------------
*/

// 前台：查看公开反馈 / 提交反馈（限频防刷）
Route::get('/movies/{movieId}/reviews', [ReviewController::class, 'index']);
Route::post('/movies/{movieId}/reviews', [ReviewController::class, 'store'])
    ->middleware('throttle:10,1');

// 后台：管理员登录
Route::post('/admin/login', [ReviewController::class, 'login']);

// 后台：反馈审核、隐藏、回复、删除（令牌鉴权）
Route::middleware('admin')->prefix('admin/reviews')->group(function () {
    Route::get('/', [ReviewController::class, 'adminIndex']);
    Route::get('/stats', [ReviewController::class, 'adminStats']);
    Route::post('/{id}/approve', [ReviewController::class, 'approve']);
    Route::post('/{id}/hide', [ReviewController::class, 'hide']);
    Route::post('/{id}/reply', [ReviewController::class, 'reply']);
    Route::delete('/{id}', [ReviewController::class, 'destroy']);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
});