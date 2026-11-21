<template>
    <div class="grid grid-cols-4 gap-2">
        <button
            v-for="(s, key) in STAGES" :key="key" type="button"
            class="group relative rounded-xl border p-2.5 text-center transition"
            :class="modelValue === key ? 'border-ink-900 ring-2 ring-ink-900/10 bg-white' : 'border-ink-200 hover:border-ink-300 bg-white'"
            @click="$emit('update:modelValue', key)"
        >
            <svg viewBox="0 0 48 24" class="w-full h-7"><path d="M4 8c6 10 28 14 40 0-4 14-34 16-40 0z" :fill="s.hex" stroke="#1a1916" stroke-width="1.6" stroke-linejoin="round" /><path d="M43 8c1-2 2-4 1-6" stroke="#3f3d35" stroke-width="2" stroke-linecap="round" /></svg>
            <span class="block text-[11px] font-bold mt-1">{{ s.label }}</span>
            <span v-if="modelValue === key" class="absolute -top-1.5 -right-1.5 grid place-items-center size-5 rounded-full bg-ink-900 text-brand-400"><Icon name="check" :size="11" :stroke="3" /></span>
        </button>
    </div>
</template>

<script setup>
import Icon from './Icon.vue';
import { STAGES } from '../lib/format';
defineProps({ modelValue: String });
defineEmits(['update:modelValue']);
</script>
