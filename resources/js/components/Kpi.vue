<template>
    <div class="card p-4 sm:p-5 relative overflow-hidden animate-fade-up">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold muted">{{ label }}</p>
                <p class="mt-2 text-xl sm:text-[26px] leading-none font-extrabold tracking-tight tabular">
                    <slot>{{ value }}</slot>
                </p>
            </div>
            <span class="grid place-items-center size-8 sm:size-10 rounded-xl shrink-0" :class="accent ? 'bg-brand-400 text-ink-900 shadow-brand' : 'bg-ink-100 text-ink-600'">
                <Icon :name="icon" :size="19" />
            </span>
        </div>
        <div class="mt-3 flex items-center gap-2 text-xs">
            <span v-if="delta != null" class="inline-flex items-center gap-0.5 font-bold" :class="delta >= 0 === !invert ? 'text-emerald-600' : 'text-red-600'">
                <Icon :name="delta >= 0 ? 'arrowUp' : 'arrowDown'" :size="12" :stroke="2.6" />{{ Math.abs(delta).toFixed(1) }}%
            </span>
            <span class="muted truncate">{{ hint }}</span>
        </div>
    </div>
</template>

<script setup>
import Icon from './Icon.vue';
defineProps({ label: String, value: [String, Number], icon: String, hint: String, delta: Number, accent: Boolean, invert: Boolean });
</script>
