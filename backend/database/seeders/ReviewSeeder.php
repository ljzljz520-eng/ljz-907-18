<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $movie = Movie::first();
        if (!$movie) {
            return;
        }

        $samples = [
            [
                'author' => '社区观众·李阿姨',
                'rating' => 5,
                'content' => '公益放映活动办得很好，影片很感人，希望以后多放一些这样的经典作品。',
                'status' => Review::STATUS_APPROVED,
                'admin_reply' => '感谢您的支持！下个月还有公益放映专场，欢迎再来。',
            ],
            [
                'author' => '志愿者小王',
                'rating' => 4,
                'content' => '片子质量不错，现场秩序也很好。建议开场前能加一点影片背景介绍。',
                'status' => Review::STATUS_APPROVED,
                'admin_reply' => '建议已收到，后续场次会安排映前导赏。',
            ],
            [
                'author' => '匿名观众',
                'rating' => 5,
                'content' => '带孩子一起看的，很有教育意义。',
                'status' => Review::STATUS_APPROVED,
                'admin_reply' => null,
            ],
            [
                'author' => '待审核用户',
                'rating' => 3,
                'content' => '这条留言还在等待管理员审核。',
                'status' => Review::STATUS_PENDING,
                'admin_reply' => null,
            ],
            [
                'author' => '代开发票13800000000',
                'contact' => 'spam@example.com',
                'rating' => 1,
                'content' => '低价代理各类发票，加VX：xxxxx（广告内容，应被隐藏）',
                'status' => Review::STATUS_HIDDEN,
                'hide_reason' => '广告/垃圾信息',
                'admin_reply' => null,
            ],
        ];

        foreach ($samples as $sample) {
            Review::firstOrCreate(
                ['movie_id' => $movie->id, 'content' => $sample['content']],
                array_merge($sample, [
                    'reviewed_at' => in_array($sample['status'], [Review::STATUS_APPROVED, Review::STATUS_HIDDEN], true)
                        ? now() : null,
                ])
            );
        }
    }
}
