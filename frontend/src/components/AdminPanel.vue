<script setup>
import { ref, onMounted, computed } from 'vue';
import { adminApi, getAdminToken, setAdminToken, clearAdminToken } from '../lib/api.js';
import StarRating from './StarRating.vue';
import {
  Film, LogIn, LogOut, Loader2, CircleCheck, EyeOff, Trash2, Reply,
  ShieldCheck, Clock, X, ChevronLeft, ChevronRight, ArrowLeft
} from 'lucide-vue-next';

const emit = defineEmits(['back-home']);

const isAuthed = ref(!!getAdminToken());
const loginToken = ref('');
const loginError = ref('');
const loggingIn = ref(false);

const reviews = ref([]);
const loading = ref(false);
const loadError = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);

const filterStatus = ref('pending'); // pending | approved | hidden | ''
const search = ref('');

const stats = ref({ pending_count: 0, approved_count: 0, hidden_count: 0, total_count: 0 });
const actionMsg = ref('');

// 回复弹窗
const replyTarget = ref(null);
const replyText = ref('');
const replySaving = ref(false);

// 隐藏确认
const hideTarget = ref(null);
const hideReason = ref('privacy');

const statusMeta = {
  pending: { label: '待审核', cls: 'bg-amber-500/15 text-amber-300' },
  approved: { label: '已通过', cls: 'bg-green-500/15 text-green-300' },
  hidden: { label: '已隐藏', cls: 'bg-gray-500/15 text-gray-400' },
};

const movieTitle = (movie) => {
  if (!movie) return '-';
  return movie.translated_title || movie.title;
};

const login = async () => {
  loginError.value = '';
  if (!loginToken.value.trim()) {
    loginError.value = '请输入管理员口令';
    return;
  }
  loggingIn.value = true;
  try {
    const { data } = await adminApi.post('/admin/login', { token: loginToken.value.trim() });
    setAdminToken(data.token);
    isAuthed.value = true;
    await reloadAll();
  } catch (e) {
    loginError.value = e.response?.data?.error || '登录失败';
  } finally {
    loggingIn.value = false;
  }
};

const logout = () => {
  clearAdminToken();
  isAuthed.value = false;
  loginToken.value = '';
};

const fetchStats = async () => {
  try {
    const { data } = await adminApi.get('/admin/reviews/stats');
    stats.value = data;
  } catch (e) {
    // 忽略统计错误
  }
};

const fetchReviews = async (p = 1) => {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await adminApi.get('/admin/reviews', {
      params: {
        page: p,
        status: filterStatus.value || undefined,
        search: search.value || undefined,
      },
    });
    reviews.value = data.data;
    page.value = data.current_page;
    lastPage.value = data.last_page;
    total.value = data.total;
  } catch (e) {
    loadError.value = e.response?.data?.error || '加载失败';
  } finally {
    loading.value = false;
  }
};

const reloadAll = async () => {
  await Promise.all([fetchStats(), fetchReviews(1)]);
};

let searchTimer;
const onSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchReviews(1), 300);
};

const flash = (msg) => {
  actionMsg.value = msg;
  setTimeout(() => (actionMsg.value = ''), 2500);
};

const approve = async (review) => {
  try {
    const { data } = await adminApi.post(`/admin/reviews/${review.id}/approve`);
    flash(data.message);
    await Promise.all([fetchStats(), fetchReviews(page.value)]);
  } catch (e) {
    flash(e.response?.data?.error || '操作失败');
  }
};

const openHide = (review) => {
  hideTarget.value = review;
  hideReason.value = 'privacy';
};

const confirmHide = async () => {
  if (!hideTarget.value) return;
  try {
    const { data } = await adminApi.post(`/admin/reviews/${hideTarget.value.id}/hide`, {
      hide_reason: hideReason.value,
    });
    flash(data.message);
    hideTarget.value = null;
    await Promise.all([fetchStats(), fetchReviews(page.value)]);
  } catch (e) {
    flash(e.response?.data?.error || '操作失败');
  }
};

const openReply = (review) => {
  replyTarget.value = review;
  replyText.value = review.admin_reply || '';
};

const saveReply = async () => {
  if (!replyTarget.value) return;
  if (!replyText.value.trim()) {
    flash('请填写回复内容');
    return;
  }
  replySaving.value = true;
  try {
    const { data } = await adminApi.post(`/admin/reviews/${replyTarget.value.id}/reply`, {
      admin_reply: replyText.value.trim(),
      approve: replyTarget.value.status !== 'approved',
    });
    flash(data.message);
    replyTarget.value = null;
    await Promise.all([fetchStats(), fetchReviews(page.value)]);
  } catch (e) {
    flash(e.response?.data?.error || '回复失败');
  } finally {
    replySaving.value = false;
  }
};

const remove = async (review) => {
  if (!window.confirm('确定删除这条反馈？删除后不可恢复。')) return;
  try {
    const { data } = await adminApi.delete(`/admin/reviews/${review.id}`);
    flash(data.message);
    await Promise.all([fetchStats(), fetchReviews(page.value)]);
  } catch (e) {
    flash(e.response?.data?.error || '删除失败');
  }
};

const formatDate = (iso) => (iso ? new Date(iso).toLocaleString('zh-CN') : '-');

onMounted(() => {
  if (isAuthed.value) reloadAll();
  window.addEventListener('admin-unauthorized', logout);
});
</script>

<template>
  <div class="min-h-screen bg-dark-900 pb-20">
    <!-- 顶栏 -->
    <nav class="sticky top-0 z-30 border-b border-white/5 bg-dark-900/90 backdrop-blur-md">
      <div class="container mx-auto flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
          <button
            class="flex items-center gap-1.5 rounded-full bg-white/5 px-3 py-1.5 text-sm text-gray-300 transition hover:bg-white/10"
            @click="emit('back-home')"
          >
            <ArrowLeft class="h-4 w-4" />
            <span class="hidden sm:inline">返回前台</span>
          </button>
          <div class="flex items-center gap-2">
            <ShieldCheck class="h-5 w-5 text-purple-500" />
            <span class="font-bold text-white">反馈审核后台</span>
          </div>
        </div>
        <button
          v-if="isAuthed"
          class="flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm text-white transition hover:bg-white/20"
          @click="logout"
        >
          <LogOut class="h-4 w-4" />
          退出
        </button>
      </div>
    </nav>

    <main class="container mx-auto px-4 py-8 sm:px-6 lg:px-8">
      <!-- 登录卡片 -->
      <div v-if="!isAuthed" class="mx-auto mt-16 max-w-md">
        <div class="rounded-3xl bg-dark-800 p-8 shadow-2xl ring-1 ring-white/5">
          <div class="mb-6 flex flex-col items-center text-center">
            <div class="rounded-2xl bg-purple-500/15 p-3">
              <ShieldCheck class="h-8 w-8 text-purple-400" />
            </div>
            <h1 class="mt-4 text-xl font-bold text-white">管理员登录</h1>
            <p class="mt-1 text-sm text-gray-500">登录后可审核观众留言、隐藏违规内容并回复</p>
          </div>
          <input
            v-model="loginToken"
            type="password"
            placeholder="请输入管理员口令"
            class="w-full rounded-xl border-none bg-white/5 px-4 py-3 text-white placeholder-gray-600 ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-purple-500"
            @keyup.enter="login"
          />
          <div v-if="loginError" class="mt-3 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">
            {{ loginError }}
          </div>
          <button
            :disabled="loggingIn"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-purple-600 py-3 font-medium text-white transition hover:bg-purple-500 disabled:opacity-50"
            @click="login"
          >
            <Loader2 v-if="loggingIn" class="h-4 w-4 animate-spin" />
            <LogIn v-else class="h-4 w-4" />
            登录
          </button>
          <p class="mt-4 text-center text-xs text-gray-600">
            口令由站点管理员通过后端环境变量 ADMIN_TOKEN 配置
          </p>
        </div>
      </div>

      <!-- 管理界面 -->
      <div v-else>
        <!-- 统计卡片 -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
          <div class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/5">
            <div class="flex items-center gap-2 text-amber-400">
              <Clock class="h-4 w-4" />
              <span class="text-sm">待审核</span>
            </div>
            <p class="mt-2 text-3xl font-bold text-white">{{ stats.pending_count }}</p>
          </div>
          <div class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/5">
            <div class="flex items-center gap-2 text-green-400">
              <CircleCheck class="h-4 w-4" />
              <span class="text-sm">已通过</span>
            </div>
            <p class="mt-2 text-3xl font-bold text-white">{{ stats.approved_count }}</p>
          </div>
          <div class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/5">
            <div class="flex items-center gap-2 text-gray-400">
              <EyeOff class="h-4 w-4" />
              <span class="text-sm">已隐藏</span>
            </div>
            <p class="mt-2 text-3xl font-bold text-white">{{ stats.hidden_count }}</p>
          </div>
          <div class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/5">
            <div class="flex items-center gap-2 text-purple-400">
              <Film class="h-4 w-4" />
              <span class="text-sm">反馈总数</span>
            </div>
            <p class="mt-2 text-3xl font-bold text-white">{{ stats.total_count }}</p>
          </div>
        </div>

        <!-- 筛选栏 -->
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex flex-wrap gap-2">
            <button
              v-for="tab in [
                { v: 'pending', l: '待审核' },
                { v: 'approved', l: '已通过' },
                { v: 'hidden', l: '已隐藏' },
                { v: '', l: '全部' },
              ]"
              :key="tab.v"
              class="rounded-full px-4 py-1.5 text-sm font-medium transition"
              :class="filterStatus === tab.v
                ? 'bg-purple-600 text-white'
                : 'bg-white/5 text-gray-400 hover:bg-white/10'"
              @click="filterStatus = tab.v; fetchReviews(1)"
            >
              {{ tab.l }}
            </button>
          </div>
          <input
            v-model="search"
            type="text"
            placeholder="搜索称呼或留言内容…"
            class="rounded-xl border-none bg-white/5 px-4 py-2 text-sm text-white placeholder-gray-600 ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-purple-500 sm:w-72"
            @input="onSearch"
          />
        </div>

        <!-- 提示 -->
        <div v-if="actionMsg" class="mt-4 rounded-xl bg-purple-500/10 px-4 py-3 text-sm text-purple-200 ring-1 ring-purple-500/20">
          {{ actionMsg }}
        </div>

        <!-- 列表 -->
        <div v-if="loading" class="mt-12 flex justify-center">
          <Loader2 class="h-8 w-8 animate-spin text-purple-500" />
        </div>

        <div v-else-if="loadError" class="mt-8 rounded-xl bg-red-500/10 px-4 py-3 text-sm text-red-300">
          {{ loadError }}
        </div>

        <div v-else-if="reviews.length === 0" class="mt-16 text-center text-gray-500">
          <CircleCheck class="mx-auto h-10 w-10 opacity-40" />
          <p class="mt-3">当前没有符合条件的反馈</p>
        </div>

        <ul v-else class="mt-6 space-y-4">
          <li
            v-for="review in reviews"
            :key="review.id"
            class="rounded-2xl bg-dark-800 p-5 ring-1 ring-white/5"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-medium text-white">{{ review.author }}</span>
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="statusMeta[review.status]?.cls"
                  >
                    {{ statusMeta[review.status]?.label || review.status }}
                  </span>
                  <StarRating :model-value="review.rating" readonly size="h-3.5 w-3.5" />
                </div>
                <p class="mt-1 flex items-center gap-1.5 text-xs text-gray-500">
                  <Film class="h-3 w-3" />
                  {{ movieTitle(review.movie) }}
                  <span class="text-gray-700">·</span>
                  {{ formatDate(review.created_at) }}
                </p>
                <p v-if="review.contact" class="mt-1 text-xs text-gray-500">
                  联系方式（仅后台可见）：<span class="text-gray-400">{{ review.contact }}</span>
                </p>
              </div>
            </div>

            <p class="mt-3 whitespace-pre-wrap rounded-xl bg-white/[0.03] p-3 text-sm leading-relaxed text-gray-300 ring-1 ring-white/5">
              {{ review.content }}
            </p>

            <p v-if="review.hide_reason" class="mt-2 text-xs text-gray-500">
              隐藏原因：<span class="text-gray-400">{{ review.hide_reason }}</span>
            </p>

            <!-- 已有回复 -->
            <div v-if="review.admin_reply" class="mt-3 rounded-xl bg-purple-500/10 p-3 ring-1 ring-purple-500/20">
              <p class="mb-1 flex items-center gap-1.5 text-xs font-bold text-purple-300">
                <ShieldCheck class="h-3.5 w-3.5" /> 管理员回复
              </p>
              <p class="whitespace-pre-wrap text-sm text-gray-300">{{ review.admin_reply }}</p>
            </div>

            <!-- 操作按钮 -->
            <div class="mt-4 flex flex-wrap gap-2">
              <button
                v-if="review.status !== 'approved'"
                class="flex items-center gap-1.5 rounded-lg bg-green-600/20 px-3 py-1.5 text-xs font-medium text-green-300 transition hover:bg-green-600/30"
                @click="approve(review)"
              >
                <CircleCheck class="h-3.5 w-3.5" /> 通过并公开
              </button>
              <button
                v-if="review.status !== 'hidden'"
                class="flex items-center gap-1.5 rounded-lg bg-gray-500/20 px-3 py-1.5 text-xs font-medium text-gray-300 transition hover:bg-gray-500/30"
                @click="openHide(review)"
              >
                <EyeOff class="h-3.5 w-3.5" /> 隐藏
              </button>
              <button
                class="flex items-center gap-1.5 rounded-lg bg-purple-500/20 px-3 py-1.5 text-xs font-medium text-purple-300 transition hover:bg-purple-500/30"
                @click="openReply(review)"
              >
                <Reply class="h-3.5 w-3.5" />
                {{ review.admin_reply ? '修改回复' : '回复' }}
              </button>
              <button
                class="flex items-center gap-1.5 rounded-lg bg-red-500/20 px-3 py-1.5 text-xs font-medium text-red-300 transition hover:bg-red-500/30"
                @click="remove(review)"
              >
                <Trash2 class="h-3.5 w-3.5" /> 删除
              </button>
            </div>
          </li>
        </ul>

        <!-- 分页 -->
        <div v-if="lastPage > 1" class="mt-8 flex items-center justify-center gap-2">
          <button
            :disabled="page <= 1"
            class="rounded-lg bg-white/5 p-2 text-gray-300 transition hover:bg-white/10 disabled:opacity-30"
            @click="fetchReviews(page - 1)"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>
          <span class="text-sm text-gray-400">{{ page }} / {{ lastPage }}（共 {{ total }} 条）</span>
          <button
            :disabled="page >= lastPage"
            class="rounded-lg bg-white/5 p-2 text-gray-300 transition hover:bg-white/10 disabled:opacity-30"
            @click="fetchReviews(page + 1)"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
        </div>
      </div>
    </main>

    <!-- 回复弹窗 -->
    <div
      v-if="replyTarget"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      @click.self="replyTarget = null"
    >
      <div class="w-full max-w-lg rounded-2xl bg-dark-800 p-6 ring-1 ring-white/10">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="flex items-center gap-2 font-bold text-white">
            <Reply class="h-4 w-4 text-purple-400" /> 回复观众留言
          </h3>
          <button class="text-gray-500 hover:text-white" @click="replyTarget = null">
            <X class="h-5 w-5" />
          </button>
        </div>
        <div class="mb-4 max-h-32 overflow-y-auto rounded-xl bg-white/[0.03] p-3 text-sm text-gray-400 ring-1 ring-white/5">
          {{ replyTarget.content }}
        </div>
        <textarea
          v-model="replyText"
          rows="4"
          maxlength="1000"
          placeholder="写下官方回复，将展示在前台对应留言下方…"
          class="w-full resize-y rounded-xl border-none bg-white/5 px-4 py-3 text-sm text-white placeholder-gray-600 ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-purple-500"
        ></textarea>
        <p v-if="replyTarget.status !== 'approved'" class="mt-2 text-xs text-gray-500">
          回复后该留言将自动通过审核，并在前台公开。
        </p>
        <div class="mt-4 flex justify-end gap-2">
          <button
            class="rounded-xl bg-white/5 px-4 py-2 text-sm text-gray-300 hover:bg-white/10"
            @click="replyTarget = null"
          >
            取消
          </button>
          <button
            :disabled="replySaving"
            class="flex items-center gap-2 rounded-xl bg-purple-600 px-5 py-2 text-sm font-medium text-white hover:bg-purple-500 disabled:opacity-50"
            @click="saveReply"
          >
            <Loader2 v-if="replySaving" class="h-4 w-4 animate-spin" />
            发送回复
          </button>
        </div>
      </div>
    </div>

    <!-- 隐藏确认弹窗 -->
    <div
      v-if="hideTarget"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      @click.self="hideTarget = null"
    >
      <div class="w-full max-w-md rounded-2xl bg-dark-800 p-6 ring-1 ring-white/10">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="flex items-center gap-2 font-bold text-white">
            <EyeOff class="h-4 w-4 text-gray-400" /> 隐藏该反馈
          </h3>
          <button class="text-gray-500 hover:text-white" @click="hideTarget = null">
            <X class="h-5 w-5" />
          </button>
        </div>
        <p class="text-sm text-gray-400">
          隐藏后该留言及其评分将不会在前台展示，也不计入评分汇总。请选择原因：
        </p>
        <div class="mt-4 space-y-2">
          <label
            v-for="opt in [
              { v: 'privacy', l: '涉及个人隐私' },
              { v: 'ad', l: '广告 / 垃圾信息' },
              { v: 'other', l: '其他不当内容' },
            ]"
            :key="opt.v"
            class="flex cursor-pointer items-center gap-3 rounded-xl bg-white/5 px-4 py-3 text-sm text-gray-200 ring-1 ring-white/10 hover:bg-white/10"
          >
            <input v-model="hideReason" type="radio" :value="opt.v" class="accent-purple-500" />
            {{ opt.l }}
          </label>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button
            class="rounded-xl bg-white/5 px-4 py-2 text-sm text-gray-300 hover:bg-white/10"
            @click="hideTarget = null"
          >
            取消
          </button>
          <button
            class="rounded-xl bg-gray-600 px-5 py-2 text-sm font-medium text-white hover:bg-gray-500"
            @click="confirmHide"
          >
            确认隐藏
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
