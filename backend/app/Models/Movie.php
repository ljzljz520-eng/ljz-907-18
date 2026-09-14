<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'translated_title',
        'director',
        'writer',
        'actors',
        'year',
        'release_date',
        'country',
        'language',
        'runtime',
        'genre',
        'rating',
        'imdb_rating',
        'imdb_link',
        'douban_link',
        'poster_url',
        'description',
        'awards',
        'screenshots',
    ];

    protected $casts = [
        'year' => 'integer',
        'rating' => 'decimal:1',
        'screenshots' => 'array',
    ];

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * 已审核通过、可公开展示的观众反馈
     */
    public function approvedReviews()
    {
        return $this->reviews()->approved()->latest();
    }

    /**
     * 观众评分汇总 —— 仅统计已审核通过的反馈，
     * 待审核 / 已隐藏(隐私、广告)的评分一律排除。
     */
    public function ratingSummary(): array
    {
        $aggregate = $this->reviews()
            ->approved()
            ->selectRaw('COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS average_rating')
            ->first();

        $count = (int) ($aggregate->review_count ?? 0);
        $average = $count > 0 ? round((float) $aggregate->average_rating, 1) : 0;

        // 分布（各星级条数），同样只统计通过审核的内容
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $rows = $this->reviews()->approved()
            ->selectRaw('rating, COUNT(*) AS total')
            ->groupBy('rating')
            ->pluck('total', 'rating');
        foreach ($rows as $star => $total) {
            $distribution[(int) $star] = (int) $total;
        }

        return [
            'average_rating' => $average,
            'review_count' => $count,
            'distribution' => $distribution,
        ];
    }
}