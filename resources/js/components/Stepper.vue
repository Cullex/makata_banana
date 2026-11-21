<template>
    <div class="rounded-2xl border border-ink-200 p-4">
        <div class="flex items-center gap-2 text-xs font-semibold text-ink-600"><Icon :name="icon" :size="15" /> {{ label }}</div>
        <div class="flex items-center justify-between mt-3">
            <button class="btn-icon btn-outline h-9 w-9" @click="set(modelValue - step)"><Icon name="minus" :size="16" /></button>
            <p class="text-2xl font-extrabold tabular">{{ Number(modelValue).toFixed(step < 1 ? 1 : 0) }}<span class="text-xs muted ml-0.5">{{ unit }}</span></p>
            <button class="btn-icon btn-outline h-9 w-9" @click="set(modelValue + step)"><Icon name="plus" :size="16" /></button>
        </div>
        <p class="text-[10px] muted mt-2 text-center">{{ hint }}</p>
    </div>
</template>

<script setup>
import Icon from './Icon.vue';
const props = defineProps({ modelValue: Number, label: String, unit: String, step: { type: Number, default: 1 }, min: Number, max: Number, icon: String, hint: String });
const emit = defineEmits(['update:modelValue']);
const set = (v) => emit('update:modelValue', Math.round(Math.max(props.min, Math.min(props.max, v)) * 10) / 10);
</script>
