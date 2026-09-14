<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 公益放映观众反馈（留言 + 评分）
     * - status: pending 待审核 / approved 已通过(公开展示) / hidden 已隐藏(隐私或广告)
     * - 评分汇总只统计 approved 的记录
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('movie_id');
            $table->string('author', 50);
            $table->string('contact', 100)->nullable(); // 联系方式，仅供管理员后台查看
            $table->unsignedTinyInteger('rating');      // 1-5 星
            $table->text('content');                    // 观后留言
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('hide_reason', 255)->nullable(); // 隐藏原因(广告/隐私等)，仅后台可见
            $table->text('admin_reply')->nullable();    // 管理员回复，随留言在前台展示
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['movie_id', 'status']);
            $table->foreign('movie_id')->references('id')->on('movies')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
