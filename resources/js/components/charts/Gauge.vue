<template>
    <div class="relative" :style="{ width: size + 'px', height: size * 0.62 + 'px' }">
        <svg :width="size" :height="size * 0.62" :viewBox="`0 0 ${size} ${size * 0.62}`">
            <path :d="arc(1)" fill="none" stroke="var(--color-ink-100)" :stroke-width="thick" stroke-linecap="round" />
            <path v-if="range" :d="arcBetween(range[0], range[1])" fill="none" stroke="var(--color-emerald-200, #a7f3d0)" :stroke-width="thick" />
            <path :d="arc(frac)" fill="none" :stroke="color" :stroke-width="thick" stroke-linecap="round" class="transition-all duration-700" />
        </svg>
        <div class="absolute inset-x-0 bottom-0 text-center">
            <p class="text-lg font-extrabold leading-none tabular">{{ display }}<span class="text-xs font-semibold muted ml-0.5">{{ unit }}</span></p>
            <p class="text-[10px] muted mt-1 font-medium">{{ label }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: Number, min: { type: Number, default: 0 }, max: { type: Number, default: 100 }, unit: String, label: String,
    size: { type: Number, default: 110 }, thick: { type: Number, default: 9 }, color: { type: String, default: '#eab308' }, ok: Array, decimals: { type: Number, default: 1 },
});
const frac = computed(() => Math.max(0.001, Math.min(1, (props.value - props.min) / (props.max - props.min))));
const display = computed(() => Number(props.value ?? 0).toFixed(props.decimals));
const range = computed(() => props.ok && [(props.ok[0] - props.min) / (props.max - props.min), (props.ok[1] - props.min) / (props.max - props.min)]);
const cx = computed(() => props.size / 2);
const cy = computed(() => props.size / 2);
const r = computed(() => props.size / 2 - props.thick);
const pt = (f) => {
    const a = Math.PI * (1 - f);
    return [cx.value + r.value * Math.cos(a), cy.value - r.value * Math.sin(a)];
};
const arcBetween = (f0, f1) => {
    const [x0, y0] = pt(f0), [x1, y1] = pt(f1);
    return `M${x0},${y0} A${r.value},${r.value} 0 0 1 ${x1},${y1}`;
};
const arc = (f) => arcBetween(0, f);
</script>
