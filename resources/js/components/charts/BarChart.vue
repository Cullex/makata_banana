<template>
    <div ref="el" class="relative select-none" @mouseleave="hover = null">
        <svg :width="width" :height="height" class="block overflow-visible">
            <g v-for="t in ticks" :key="t">
                <line :x1="pad.l" :x2="width - pad.r" :y1="y(t)" :y2="y(t)" stroke="var(--color-ink-200)" stroke-dasharray="3 4" />
                <text :x="pad.l - 8" :y="y(t) + 4" text-anchor="end" class="fill-ink-400 text-[10px] tabular">{{ fmtY(t) }}</text>
            </g>
            <g v-for="(d, i) in data" :key="i" @mouseenter="hover = i">
                <rect :x="bx(i) - 2" :y="pad.t" :width="bw + 4" :height="innerH" :fill="hover === i ? 'var(--color-ink-100)' : 'transparent'" rx="6" />
                <template v-for="(s, si) in series" :key="s.key">
                    <rect
                        :x="bx(i)" :y="y(stackTop(d, si))" :width="bw" :height="Math.max(0, y(stackTop(d, si) - (+d[s.key] || 0)) - y(stackTop(d, si)))"
                        :fill="s.color" :rx="si === series.length - 1 ? Math.min(6, bw / 3) : 0" class="bar"
                        :style="{ animationDelay: i * 25 + 'ms' }"
                    />
                </template>
                <text v-if="showLabel(i)" :x="bx(i) + bw / 2" :y="height - 6" text-anchor="middle" class="fill-ink-400 text-[10px]">{{ fmtX(d[xKey]) }}</text>
            </g>
        </svg>
        <div v-if="hover != null" class="absolute pointer-events-none -translate-x-1/2 -translate-y-full bg-ink-900 text-white rounded-lg px-2.5 py-1.5 text-xs shadow-pop whitespace-nowrap z-10" :style="{ left: bx(hover) + bw / 2 + 'px', top: y(total(data[hover])) - 8 + 'px' }">
            <p class="text-ink-400 text-[10px] mb-0.5">{{ fmtTip(data[hover][xKey]) }}</p>
            <p v-for="s in series" :key="s.key" class="flex items-center gap-1.5">
                <span class="size-2 rounded-sm" :style="{ background: s.color }" />
                <span v-if="series.length > 1" class="text-ink-300">{{ s.label }}</span>
                <b class="ml-auto pl-2">{{ fmtVal(+data[hover][s.key] || 0) }}</b>
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { niceMax, useWidth, compact } from './useWidth';

const props = defineProps({
    data: Array, xKey: { type: String, default: 'label' },
    series: { type: Array, default: () => [{ key: 'value', color: '#facc15', label: 'Value' }] },
    height: { type: Number, default: 240 },
    fmtX: { type: Function, default: (v) => v },
    fmtTip: { type: Function, default: (v) => v },
    fmtY: { type: Function, default: compact },
    fmtVal: { type: Function, default: (v) => v },
});
const el = ref();
const width = useWidth(el);
const hover = ref(null);
const pad = { t: 10, r: 6, b: 26, l: 44 };
const innerW = computed(() => width.value - pad.l - pad.r);
const innerH = computed(() => props.height - pad.t - pad.b);
const total = (d) => props.series.reduce((a, s) => a + (+d[s.key] || 0), 0);
const stackTop = (d, si) => props.series.slice(0, si + 1).reduce((a, s) => a + (+d[s.key] || 0), 0);
const max = computed(() => niceMax(Math.max(1, ...props.data.map(total))));
const slot = computed(() => innerW.value / Math.max(1, props.data.length));
const bw = computed(() => Math.max(4, Math.min(42, slot.value * 0.62)));
const bx = (i) => pad.l + i * slot.value + (slot.value - bw.value) / 2;
const y = (v) => pad.t + innerH.value - (v / max.value) * innerH.value;
const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => max.value * f));
const showLabel = (i) => {
    const every = Math.ceil(props.data.length / Math.max(2, Math.floor(innerW.value / 52)));
    return i % every === 0;
};
</script>

<style scoped>
.bar { transform-box: fill-box; transform-origin: bottom; animation: grow 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both; }
@keyframes grow { from { transform: scaleY(0); } }
</style>
