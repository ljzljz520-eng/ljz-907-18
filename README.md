# 影格 (CineVault)

**影格 (CineVault)** 是一款基于现代 Web 技术栈构建的高品质电影数据管理系统。它专注于极致的视觉展示与深度元数据管理，为影迷提供沉浸式的观影资料探索体验。

## 🛠 技术栈

- **前端**: Vue 3 + Tailwind CSS v4 + Lucide Icons + Headless UI
- **后端**: Laravel 10 + PHP 8.2 + Eloquent ORM
- **数据库**: MySQL 8.0
- **容器化**: Docker Compose

## 🚀 快速开始

### 1. 启动服务

```bash
cd cinevault
docker compose up -d --build
```

系统将自动：
- 启动前端、后端、数据库及 Nginx 服务
- 执行数据库迁移 (`migrate`)
- 从 `movies_crawled.csv` 导入初始电影数据 (`seed`)
- 初始化应用配置并预热（确保 CORS 等配置已加载）

**重要提示**：请等待后端容器完全启动，看到以下日志后再访问网页：
- `fpm is running, pid xxx`
- `ready to handle connections`

这些日志表示：
- ✅ 数据库迁移已完成
- ✅ 初始数据已导入完成
- ✅ 应用配置已初始化
- ✅ PHP-FPM 已准备好处理请求

可通过以下命令查看启动日志：

```bash
docker compose logs -f backend
```

等待看到类似以下完整日志后再访问：
```
✓ CORS configuration verified: 1 allowed origin(s)
✓ Application warmed up - CORS config loaded: 1 origin(s)
Starting PHP-FPM...
fpm is running, pid xxx
ready to handle connections
```

**注意**：如果过早访问网页（在初始化数据完成前），可能会遇到首次请求的跨域错误。

### 2. 访问服务

- **Web UI**: [http://localhost:3000](http://localhost:3000)
- **Backend API**: [http://localhost:8000](http://localhost:8000)
- **Database**: `localhost:3306` (库名: `1` / 用户: `1` / 密码: `1`)

## 📊 数据管理

### 初始数据

系统首次启动时会自动从 `storage/movies_crawled.csv` 导入电影数据。

### CSV 批量导入

支持通过 Web 界面上传 CSV 文件批量导入电影数据：

- 支持 19 个字段的完整数据导入
- 自动数据清理和验证（UTF-8 编码、字段长度、评分范围等）
- 基于"标题+年份"智能去重
- 流式处理，支持大文件导入

**测试导入**：项目根目录提供了 `example_movies_crawled.csv` 测试文件，您可以通过 Web 界面上传此文件来测试 CSV 导入功能。

## 💬 公益放映反馈（留言与评分）

公益放映结束后，观众可以在**影片详情页底部**提交观后留言与 1–5 星评分。

### 审核流程

1. 观众提交反馈后，留言默认为 **待审核（pending）** 状态，不会立即公开，也不计入评分。
2. 管理员在后台审核：
   - **通过（approved）**：留言在影片详情页公开展示，评分计入观众评分汇总（均分、人数、星级分布）。
   - **隐藏（hidden）**：涉及个人隐私、广告/垃圾信息等的反馈被隐藏，不出现在前台且不计入评分（需选择/记录隐藏原因）。
   - **删除**：彻底删除反馈。
3. 管理员可对留言进行**官方回复**，回复会展示在前台对应留言下方；对待审核留言回复时会自动一并通过审核。

> 评分汇总严格只统计「已通过」状态的反馈；待审核、已隐藏内容一律排除。
> 观众可选填联系方式（手机/邮箱/微信），该信息仅在管理后台可见，永远不会返回前台。

### 审核后台

- 入口：前台导航栏「反馈审核」按钮，或直接访问 `http://localhost:3000/#/admin`
- 系统**不提供默认管理员口令**。请通过后端环境变量 `ADMIN_TOKEN` 设置口令：

```bash
# docker-compose.yml 的 backend 服务中添加，或在 backend/.env 中配置
ADMIN_TOKEN=你的安全口令
```

- 若未配置 `ADMIN_TOKEN`，后端容器首次启动时会自动生成一个随机口令并打印到启动日志中（同时写入 `backend/.env` 持久保存），可通过以下命令查看：

```bash
docker compose logs backend | grep -A 8 "ADMIN_TOKEN was not set"
```

### 相关 API

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET | `/api/movies/{id}/reviews` | 公开反馈列表 + 评分汇总（仅含已通过内容） |
| POST | `/api/movies/{id}/reviews` | 观众提交留言评分（限频 10 次/分钟） |
| POST | `/api/admin/login` | 管理员口令登录 |
| GET | `/api/admin/reviews` | 后台反馈列表（可按 status/movie_id/search 筛选） |
| GET | `/api/admin/reviews/stats` | 各状态反馈数量统计 |
| POST | `/api/admin/reviews/{id}/approve` | 审核通过 |
| POST | `/api/admin/reviews/{id}/hide` | 隐藏（body: `hide_reason=privacy|ad|other`） |
| POST | `/api/admin/reviews/{id}/reply` | 管理员回复（body: `admin_reply`, 可选 `approve`） |
| DELETE | `/api/admin/reviews/{id}` | 删除反馈 |

后台接口（登录除外）需要在请求头携带 `X-Admin-Token: <管理员口令>`。

## 🎨 核心特性

- **极致详情展示**: 展示包括译名、年代、产地、语言、上映日期、IMDb/豆瓣双评分及外链、片长、编剧、主演、获奖情况等丰富信息
- **公益放映反馈**: 观众可在影片详情页提交 1–5 星评分与观后留言，经后台审核后公开展示；评分汇总（均分、人数、星级分布）只统计已审核通过的内容
- **反馈审核后台**: 管理员可通过/隐藏留言（涉及个人隐私、广告等）、删除内容并回复观众，管理员回复随留言在前台展示
- **实时智能搜索**: 支持对片名、导演、演员进行实时筛选（带防抖优化）
- **图片代理**: 自动处理图片 CORS 和 403 错误，确保海报正常显示
- **响应式暗黑布局**: 完美适配移动端与桌面端
- **稳定分页**: 支持大数据量的稳定分页展示

## 📁 目录结构

```
cinevault/
├── frontend/          # Vue 3 前端源码
├── backend/           # Laravel 后端源码
│   └── storage/       # 存储目录（包含 movies_crawled.csv）
├── nginx/             # Nginx 配置
├── example_movies_crawled.csv # CSV 测试文件（用于测试导入功能）
└── docker-compose.yml # Docker 编排配置
```

## 🔧 开发说明

### 重新导入数据

如果需要重新导入初始数据：

```bash
# 进入后端容器
docker exec -it cinevault_backend bash

# 清空并重新导入数据
php artisan db:seed --class=MovieSeeder
```

### 数据库迁移

```bash
docker exec -it cinevault_backend php artisan migrate
```

## 📝 数据字段说明

CSV 文件应包含以下字段（按顺序）：

- `title` - 原片名
- `translated_title` - 译名
- `year` - 年代
- `release_date` - 上映日期
- `country` - 产地
- `language` - 语言
- `runtime` - 片长
- `director` - 导演
- `writer` - 编剧
- `actors` - 主演
- `genre` - 类别
- `rating` - 豆瓣评分（0.0-10.0）
- `imdb_rating` - IMDb 评分
- `imdb_link` - IMDb 链接
- `douban_link` - 豆瓣链接
- `poster_url` - 封面海报 URL
- `description` - 剧情简介
- `awards` - 获奖情况
- `screenshots` - 电影截图 URL（逗号分隔）

## 🐛 故障排除

- **图片无法显示**: 系统已集成图片代理功能，自动处理跨域问题
- **CSV 导入失败**: 检查文件编码是否为 UTF-8，确保必填字段（title, year）存在
- **数据库连接失败**: 确认 Docker 容器正常运行，检查 `.env` 配置
- **前端无法访问 (ERR_CONNECTION_REFUSED)**:
  - 确认前端容器正在运行：`docker compose ps frontend`
  - 检查前端日志：`docker compose logs frontend`
  - 等待 Vite 服务器完全启动（看到 `VITE ready` 和 `Local: http://localhost:3000/` 日志）
  - 如果使用 WSL2，尝试使用 `127.0.0.1:3000` 而不是 `localhost:3000`
  - 重启前端服务：`docker compose restart frontend`