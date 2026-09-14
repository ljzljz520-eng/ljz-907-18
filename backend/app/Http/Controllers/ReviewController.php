<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 前台接口（观众）
    |--------------------------------------------------------------------------
    */

    /**
     * 获取某部影片的公开反馈：评分汇总 + 已审核通过的留言
     */
    public function index(Request $request, $movieId)
    {
        $movie = Movie::find($movieId);
        if (!$movie) {
            return response()->json(['error' => '影片不存在'], 404);
        }

        $perPage = min((int) $request->input('per_page', 10), 50);

        // 仅查询已审核通过的留言，待审核/已隐藏内容不返回前台
        $paginator = $movie->approvedReviews()->paginate($perPage);

        return response()->json([
            'summary' => $movie->ratingSummary(),
            'data' => array_map(fn ($r) => $r->toPublicArray(), $paginator->items()),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * 观众提交观后留言与评分（默认进入待审核状态）
     */
    public function store(Request $request, $movieId)
    {
        $movie = Movie::find($movieId);
        if (!$movie) {
            return response()->json(['error' => '影片不存在'], 404);
        }

        $validator = Validator::make($request->all(), [
            'author' => 'required|string|max:50',
            'contact' => 'nullable|string|max:100',
            'rating' => 'required|integer|min:1|max:5',
            'content' => 'required|string|min:2|max:2000',
        ], [
            'author.required' => '请填写您的称呼',
            'author.max' => '称呼不能超过 50 个字符',
            'rating.required' => '请为影片打分',
            'rating.integer' => '评分格式不正确',
            'rating.min' => '评分需在 1 到 5 星之间',
            'rating.max' => '评分需在 1 到 5 星之间',
            'content.required' => '请填写观后留言',
            'content.min' => '留言内容过短',
            'content.max' => '留言不能超过 2000 个字符',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $review = $movie->reviews()->create([
            'author' => $this->clean($request->input('author'), 50),
            'contact' => $this->clean($request->input('contact'), 100),
            'rating' => (int) $request->input('rating'),
            'content' => $this->clean($request->input('content'), 2000),
            'ip_address' => $request->ip(),
            'status' => Review::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => '感谢您的反馈！留言将在管理员审核后公开展示。',
            'id' => $review->id,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | 后台接口（管理员）
    |--------------------------------------------------------------------------
    */

    /**
     * 管理员登录（校验访问令牌）
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'token' => 'required|string',
        ]);

        $configured = (string) config('admin.token', '');

        // 未配置管理员口令时禁止登录（不再提供可猜测的默认口令）
        if ($configured === '') {
            return response()->json(['error' => '后台未配置管理员口令，请联系站点管理员设置 ADMIN_TOKEN'], 503);
        }

        if (!hash_equals($configured, (string) $credentials['token'])) {
            return response()->json(['error' => '管理员口令不正确'], 401);
        }

        return response()->json([
            'message' => '登录成功',
            'token' => $configured,
        ]);
    }

    /**
     * 后台反馈列表（含待审核/已隐藏，及联系方式等内部信息）
     */
    public function adminIndex(Request $request)
    {
        $query = Review::with('movie:id,title,translated_title,year');

        if ($status = $request->input('status')) {
            if (in_array($status, [Review::STATUS_PENDING, Review::STATUS_APPROVED, Review::STATUS_HIDDEN], true)) {
                $query->where('status', $status);
            }
        }

        if ($movieId = $request->input('movie_id')) {
            $query->where('movie_id', $movieId);
        }

        if ($keyword = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($keyword) {
                $q->where('author', 'like', "%{$keyword}%")
                  ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginator = $query->orderByRaw("FIELD(status, 'pending') DESC")
                           ->latest()
                           ->paginate($perPage);

        return response()->json($paginator);
    }

    /**
     * 后台统计：各状态数量
     */
    public function adminStats()
    {
        return response()->json([
            'pending_count' => Review::where('status', Review::STATUS_PENDING)->count(),
            'approved_count' => Review::where('status', Review::STATUS_APPROVED)->count(),
            'hidden_count' => Review::where('status', Review::STATUS_HIDDEN)->count(),
            'total_count' => Review::count(),
        ]);
    }

    /**
     * 审核通过：公开展示并计入评分汇总
     */
    public function approve($id)
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['error' => '反馈不存在'], 404);
        }

        $review->update([
            'status' => Review::STATUS_APPROVED,
            'hide_reason' => null,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => '已通过审核，留言现已公开展示']);
    }

    /**
     * 隐藏反馈：涉及个人隐私/广告等，不在前台展示且不计入评分汇总
     */
    public function hide(Request $request, $id)
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['error' => '反馈不存在'], 404);
        }

        $validator = Validator::make($request->all(), [
            'hide_reason' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $reasonMap = [
            'privacy' => '涉及个人隐私',
            'ad' => '广告/垃圾信息',
            'other' => '其他不当内容',
        ];
        $reasonInput = $request->input('hide_reason', 'privacy');
        $reason = $reasonMap[$reasonInput] ?? $this->clean($reasonInput, 255) ?? '内容不符合展示要求';

        $review->update([
            'status' => Review::STATUS_HIDDEN,
            'hide_reason' => $reason,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => '已隐藏该反馈，不会在前台展示']);
    }

    /**
     * 管理员回复（随留言一起在前台展示）
     */
    public function reply(Request $request, $id)
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['error' => '反馈不存在'], 404);
        }

        $validator = Validator::make($request->all(), [
            'admin_reply' => 'required|string|min:1|max:1000',
            'approve' => 'nullable|boolean',
        ], [
            'admin_reply.required' => '请填写回复内容',
            'admin_reply.max' => '回复不能超过 1000 个字符',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $update = [
            'admin_reply' => $this->clean($request->input('admin_reply'), 1000),
        ];

        // 回复时可一并通过审核，确保回复能在前台随留言展示
        if ($request->boolean('approve') || $review->status === Review::STATUS_PENDING) {
            $update['status'] = Review::STATUS_APPROVED;
            $update['hide_reason'] = null;
        }
        $update['reviewed_at'] = now();

        $review->update($update);

        return response()->json(['message' => '回复成功，将展示在前台对应留言下方']);
    }

    /**
     * 删除反馈
     */
    public function destroy($id)
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['error' => '反馈不存在'], 404);
        }

        $review->delete();

        return response()->json(['message' => '反馈已删除']);
    }

    /*
    |--------------------------------------------------------------------------
    | 辅助方法
    |--------------------------------------------------------------------------
    */

    private function clean($value, ?int $maxLength = null)
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($cleaned === false) {
            $cleaned = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        // 移除控制字符（保留换行与制表符）
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned);
        $cleaned = trim($cleaned);

        if ($maxLength !== null && mb_strlen($cleaned) > $maxLength) {
            $cleaned = mb_substr($cleaned, 0, $maxLength);
        }

        return $cleaned !== '' ? $cleaned : null;
    }
}
