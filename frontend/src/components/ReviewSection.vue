<script setup>
import { ref, watch } from 'vue';
import { api } from '../lib/api.js';
import StarRating from './StarRating.vue';
import { MessageSquareText, Send, ShieldCheck, Loader2, ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
  movieId: { type: [Number, String], required: true }
});

const reviews = ref([]);
const summary = ref({ average_rating: 0, review_count: 0, distribution: {} });
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const submitting = ref(false);
const error = ref('');
const successMsg = ref('');

// 表单
const AUTHOR_KEY = 'cinevault_review_author';
const CONTACT_KEY = 'cinevault_review_contact';
const author = ref(localStorage.getItem(AUTHOR_KEY) || '');
const contact = ref(localStorage.getItem(CONTACT_KEY) || '');
const rating = ref(5);
const content = ref('');
const formError = ref('');

const formatDate = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const fetchReviews = async (p = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await api.get(`/movies/${props.movieId}/reviews`, { params: { page: p } });
    reviews.value = data.data;
    summary.value = data.summary;
    page.value = data.current_page;
    lastPage.value = data.last_page;
  } catch (e) {
    error.value = e.response?.data?.error || '反馈加载失败，请稍后重试';
  } finally {
    loading.value = false;
  }
};

const submit = async () => {
  formError.value = '';
  successMsg.value = '';

  if (!author.value.trim()) {
    formError.value = '请填写您的称呼';
    return;
  }
  if (!content.value.trim()) {
    formError.value = '请填写观后留言';
    return;
  }

  submitting.value = true;
  try {
    const { data } = await api.post(`/movies/${props.movieId}/reviews`, {
      author: author.value.trim(),
      contact: contact.value.trim() || null,
      rating: rating.value,
      content: content.value.trim(),
    });

    localStorage.setItem(AUTHOR_KEY, author.value.trim());
    if (contact.value.trim()) localStorage.setItem(CONTACT_KEY, contact.value.trim());

    successMsg.value = data.message || '感谢您的反馈！留言将在管理员审核后公开展示。';
    content.value = '';
    rating.value = 5;
  } catch (e) {
    formError.value = e.response?.data?.error || '提交失败，请稍后重试';
  } finally {
    submitting.value = false;
  }
};

watch(() => props.movieId, (id) => {
  if (id) {
    successMsg.value = '';
    formError.value = '';
    fetchReviews(1);
  }
}, { immediate: true });
</script>

<template>
  <section class="mt-10 border-t border-white/5 pt-8">
    <!-- 标题 + 评分汇总 -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div class="flex items-center gap-2 text-white">
        <div class="h-4 w-1 bg-purple-500 rounded-full"></div>
        <h3 class="text-lg font-bold">公益放映 · 观众反馈</h3>
      </div>
      <div class="flex items-center gap-3 rounded-2xl bg-white/5 px-4 py-2 ring-1 ring-white/10">
        <span class="text-3xl font-bold text-yellow-400">{{ summary.average_rating }}</span>
        <div>
          <StarRating :model-value="Math.round(summary.average_rating)" readonly size="h-3.5 w-3.5" />
          <p class="mt-0.5 text-xs text-gray-500">
            共 {{ summary.review_count }} 条已审核评分
          </p>
        </div>
      </div>
    </div>

    <!-- 留言列表 -->
    <div v-if="loading" class="flex justify-center py-10">
      <Loader2 class="h-6 w-6 animate-spin text-purple-500" />
    </div>

    <div v-else-if="error" class="rounded-xl bg-red-500/10 px-4 py-3 text-sm text-red-300">
      {{ error }}
    </div>

    <div v-else-if="reviews.length === 0" class="rounded-2xl border border-dashed border-white/10 py-10 text-center">
      <MessageSquareText class="mx-auto h-8 w-8 text-gray-600" />
      <p class="mt-3 text-sm text-gray-500">还没有公开反馈，来写下第一条观后留言吧</p>
    </div>

    <ul v-else class="space-y-4">
      <li
        v-for="review in reviews"
        :key="review.id"
        class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/5"
      >
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-purple-500/20 text-sm font-bold text-purple-300">
              {{ review.author.charAt(0) }}
            </div>
            <span class="text-sm font-medium text-gray-200">{{ review.author }}</span>
          </div>
          <div class="flex items-center gap-3">
            <StarRating :model-value="review.rating" readonly size="h-3.5 w-3.5" />
            <span class="text-xs text-gray-500">{{ formatDate(review.created_at) }}</span>
          </div>
        </div>

        <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-gray-300">{{ review.content }}</p>

        <!-- 管理员回复（前台展示） -->
        <div v-if="review.admin_reply" class="mt-4 rounded-xl bg-purple-500/10 p-4 ring-1 ring-purple-500/20">
          <div class="mb-1 flex items-center gap-1.5 text-xs font-bold text-purple-300">
            <ShieldCheck class="h-3.5 w-3.5" />
            <span>官方回复</span>
          </div>
          <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-300">{{ review.admin_reply }}</p>
        </div>
      </li>
    </ul>

    <!-- 分页 -->
    <div v-if="lastPage > 1" class="mt-6 flex items-center justify-center gap-2">
      <button
        :disabled="page <= 1"
        class="rounded-lg bg-white/5 p-2 text-gray-300 transition hover:bg-white/10 disabled:opacity-30"
        @click="fetchReviews(page - 1)"
      >
        <ChevronLeft class="h-4 w-4" />
      </button>
      <span class="text-sm text-gray-400">{{ page }} / {{ lastPage }}</span>
      <button
        :disabled="page >= lastPage"
        class="rounded-lg bg-white/5 p-2 text-gray-300 transition hover:bg-white/10 disabled:opacity-30"
        @click="fetchReviews(page + 1)"
      >
        <ChevronRight class="h-4 w-4" />
      </button>
    </div>

    <!-- 提交反馈表单 -->
    <div class="mt-8 rounded-2xl bg-white/[0.03] p-6 ring-1 ring-white/10">
      <h4 class="mb-4 flex items-center gap-2 text-sm font-bold text-white">
        <Send class="h-4 w-4 text-purple-400" />
        提交观后反馈
      </h4>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-xs text-gray-500">称呼 <span class="text-red-400">*</span></label>
          <input
            v-model="author"
            type="text"
            maxlength="50"
            placeholder="您希望展示的名称"
            class="w-full rounded-xl border-none bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-xs text-gray-500">联系方式（选填，不公开）</label>
          <input
            v-model="contact"
            type="text"
            maxlength="100"
            placeholder="手机/邮箱/微信，仅供工作人员联系"
            class="w-full rounded-xl border-none bg-white/5 px-4 py-2.5 text-sm text-white placeholder-gray-600 ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
        </div>
      </div>

      <div class="mt-4 flex items-center gap-3">
        <span class="text-xs text-gray-500">我的评分</span>
        <StarRating v-model="rating" size="h-7 w-7" />
        <span class="text-sm font-bold text-yellow-400">{{ rating }} 星</span>
      </div>

      <div class="mt-4">
        <label class="mb-1.5 block text-xs text-gray-500">观后留言 <span class="text-red-400">*</span></label>
        <textarea
          v-model="content"
          rows="4"
          maxlength="2000"
          placeholder="分享您的观影感受……（提交后需经管理员审核才会公开展示）"
          class="w-full resize-y rounded-xl border-none bg-white/5 px-4 py-3 text-sm text-white placeholder-gray-600 ring-1 ring-white/10 focus:outline-none focus:ring-2 focus:ring-purple-500"
        ></textarea>
        <div class="mt-1 text-right text-xs text-gray-600">{{ content.length }}/2000</div>
      </div>

      <p class="mt-2 flex items-start gap-1.5 text-xs leading-relaxed text-gray-600">
        <ShieldCheck class="mt-0.5 h-3.5 w-3.5 shrink-0" />
        您的联系方式仅工作人员可见，不会公开展示；涉及个人隐私或广告的留言将被隐藏。
      </p>

      <div v-if="formError" class="mt-3 rounded-lg bg-red-500/10 px-4 py-2.5 text-sm text-red-300">
        {{ formError }}
      </div>
      <div v-if="successMsg" class="mt-3 rounded-lg bg-green-500/10 px-4 py-2.5 text-sm text-green-300">
        {{ successMsg }}
      </div>

      <button
        :disabled="submitting"
        class="mt-4 flex items-center gap-2 rounded-xl bg-purple-600 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-purple-500 disabled:opacity-50"
        @click="submit"
      >
        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
        <Send v-else class="h-4 w-4" />
        提交反馈
      </button>
    </div>
  </section>
</template>
