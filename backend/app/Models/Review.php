<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Review extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = [
        'movie_id',
        'author',
        'contact',
        'rating',
        'content',
        'ip_address',
        'status',
        'hide_reason',
        'admin_reply',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    /**
     * 前台可见的留言：已审核通过。
     * 被隐藏(隐私/广告)或待审核的内容不会出现在前台，也不计入评分汇总。
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * 前台展示结构：不暴露 contact / ip_address / hide_reason 等内部信息
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author,
            'rating' => (int) $this->rating,
            'content' => $this->content,
            'admin_reply' => $this->admin_reply,
            'created_at' => $this->created_at?->toIso8601String(),
            'replied_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
