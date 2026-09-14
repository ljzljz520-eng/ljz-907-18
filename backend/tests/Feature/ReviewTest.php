<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeMovie(): Movie
    {
        return Movie::create(['title' => '测试影片', 'year' => 2026]);
    }

    public function test_visitor_can_submit_review_and_it_is_pending_by_default(): void
    {
        $movie = $this->makeMovie();

        $response = $this->postJson("/api/movies/{$movie->id}/reviews", [
            'author' => '观众甲',
            'rating' => 5,
            'content' => '非常感人的公益放映。',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'movie_id' => $movie->id,
            'status' => Review::STATUS_PENDING,
        ]);
    }

    public function test_pending_and_hidden_reviews_are_excluded_from_public_list_and_rating_summary(): void
    {
        $movie = $this->makeMovie();

        Review::create(['movie_id' => $movie->id, 'author' => 'A', 'rating' => 5,
            'content' => '通过', 'status' => Review::STATUS_APPROVED]);
        Review::create(['movie_id' => $movie->id, 'author' => 'B', 'rating' => 1,
            'content' => '待审', 'status' => Review::STATUS_PENDING]);
        Review::create(['movie_id' => $movie->id, 'author' => 'C', 'rating' => 1,
            'content' => '广告', 'status' => Review::STATUS_HIDDEN,
            'hide_reason' => '广告/垃圾信息']);

        $response = $this->getJson("/api/movies/{$movie->id}/reviews")->assertOk();

        // 只有 approved 的 1 条公开
        $this->assertCount(1, $response->json('data'));
        // 平均分只统计通过的 5 星
        $this->assertEquals(5.0, $response->json('summary.average_rating'));
        $this->assertEquals(1, $response->json('summary.review_count'));
    }

    public function test_public_payload_never_exposes_private_fields(): void
    {
        $movie = $this->makeMovie();
        Review::create(['movie_id' => $movie->id, 'author' => 'A', 'rating' => 4,
            'content' => '不错', 'contact' => 'secret@example.com',
            'ip_address' => '127.0.0.1', 'status' => Review::STATUS_APPROVED]);

        $json = $this->getJson("/api/movies/{$movie->id}/reviews")->assertOk()->json();

        $this->assertArrayNotHasKey('contact', $json['data'][0]);
        $this->assertArrayNotHasKey('ip_address', $json['data'][0]);
        $this->assertArrayNotHasKey('hide_reason', $json['data'][0]);
        $this->assertArrayNotHasKey('status', $json['data'][0]);
    }

    public function test_admin_endpoints_require_token(): void
    {
        $movie = $this->makeMovie();
        $review = Review::create(['movie_id' => $movie->id, 'author' => 'A',
            'rating' => 3, 'content' => 'x', 'status' => Review::STATUS_PENDING]);

        $this->postJson("/api/admin/reviews/{$review->id}/approve")->assertStatus(401);
        $this->getJson('/api/admin/reviews')->assertStatus(401);
    }

    public function test_admin_can_approve_hide_and_reply(): void
    {
        config(['admin.token' => 'test-token']);
        $headers = ['X-Admin-Token' => 'test-token'];
        $movie = $this->makeMovie();

        $review = Review::create(['movie_id' => $movie->id, 'author' => 'A',
            'rating' => 5, 'content' => '好评', 'status' => Review::STATUS_PENDING]);

        // 回复（自动通过）
        $this->postJson("/api/admin/reviews/{$review->id}/reply",
            ['admin_reply' => '感谢参与！'], $headers)->assertOk();

        $review->refresh();
        $this->assertEquals(Review::STATUS_APPROVED, $review->status);

        $public = $this->getJson("/api/movies/{$movie->id}/reviews")->assertOk()->json();
        $this->assertEquals('感谢参与！', $public['data'][0]['admin_reply']);

        // 隐藏：从前台消失且不计入评分
        $this->postJson("/api/admin/reviews/{$review->id}/hide",
            ['hide_reason' => 'privacy'], $headers)->assertOk();
        $review->refresh();
        $this->assertEquals(Review::STATUS_HIDDEN, $review->status);

        $afterHide = $this->getJson("/api/movies/{$movie->id}/reviews")->assertOk()->json();
        $this->assertCount(0, $afterHide['data']);
        $this->assertEquals(0, $afterHide['summary.review_count']);
    }

    public function test_invalid_rating_is_rejected(): void
    {
        $movie = $this->makeMovie();

        $this->postJson("/api/movies/{$movie->id}/reviews", [
            'author' => 'A', 'rating' => 6, 'content' => '无效评分',
        ])->assertStatus(422);
    }
}
