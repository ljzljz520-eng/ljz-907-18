<script setup>
import { ref } from 'vue';
import { Star } from 'lucide-vue-next';

const props = defineProps({
  modelValue: { type: Number, default: 0 },
  max: { type: Number, default: 5 },
  readonly: { type: Boolean, default: false },
  size: { type: String, default: 'h-5 w-5' }
});

const emit = defineEmits(['update:modelValue']);

const hover = ref(0);

const setRating = (value) => {
  if (!props.readonly) emit('update:modelValue', value);
};
</script>

<template>
  <div class="flex items-center gap-1" :class="readonly ? '' : 'cursor-pointer'">
    <button
      v-for="star in max"
      :key="star"
      type="button"
      :disabled="readonly"
      :tabindex="readonly ? -1 : 0"
      :class="readonly ? 'cursor-default' : 'hover:scale-125 focus:outline-none transition-transform'"
      @click="setRating(star)"
      @mouseenter="!readonly && (hover = star)"
      @mouseleave="!readonly && (hover = 0)"
    >
      <Star
        :class="[size, star <= (hover || modelValue) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-600']"
      />
    </button>
  </div>
</template>
