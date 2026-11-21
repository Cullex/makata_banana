<template>
    <div ref="el" class="relative select-none" @mouseleave="hover = null">
        <svg :width="width" :height="height" class="block overflow-visible">
            <defs>
                <linearGradient :id="gid" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity=".35" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>
            <g v-for="t in ticks" :key="t">
                <line :x1="pad.l" :x2="width - pad.r" :y1="y(t)" :y2="y(t)" stroke="var(--color-ink-200)" stroke-dasharray="3 4" />
                <text :x="pad.l - 8" :y="y(t) + 4" text-anchor="end" class="fill-ink-400 text-[10px] tabular">{{ fmtY(t) }}</text>
            </g>
            <path :d="area" :fill="`url(#${gid})`" />
            <path :d="line" fill="none" :stroke="color" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" class="chart-line" />
            <path v-if="line2" :d="line2" fill="none" :stroke="color2" stroke-width="2" stroke-dasharray="4 4" />
            <g v-for="(d, i) in data" :key="i">
                <text v-if="showLabel(i)" :x="x(i)" :y="height - 6" text-anchor="middle" class="fill-ink-400 text-[10px]">{{ fmtX(d[xKey]) }}</text>
                <rect :x="x(i) - step / 2" :y="pad.t" :width="step" :height="innerH" fill="transparent" @mouseenter="hover = i" />
            </g>
            <g v-if="hover != null">
                <line :x1="x(hover)" :x2="x(hover)" :y1="pad.t" :y2="pad.t + innerH" stroke="var(--color-ink-300)" />
                <circle :cx="x(hover)" :cy="y(data[hover][yKey])" r="5" :fill="color" stroke="white" stroke-width="2.5" />
            </g>
        </svg>
        <div v-if="hover != null" class="absolute pointer-events-none -translate-x-1/2 -translate-y-full bg-ink-900 text-white rounded-lg px-2.5 py-1.5 text-xs shadow-pop whitespace-nowrap" :style="{ left: x(hover) + 'px', top: y(data[hover][yKey]) - 10 + 'px' }">
            <p class="text-ink-400 text-[10px]">{{ fmtTip(data[hover][xKey]) }}</p>
            <p class="font-bold">{{ fmtVal(data[hover][yKey]) }}</p>
            <p v-if="y2Key" class="text-ink-300">{{ fmtVal2(data[hover][y2Key]) }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { niceMax, useWidth, compact } from './useWidth';

const props = defineProps({
    data: Array, xKey: { type: String, default: 'date' }, yKey: { type: String, default: 'value' }, y2Key: String,
    height: { type: Number, default: 240 }, color: { type: String, default: '#eab308' }, color2: { type: String, default: '#a8a495' },
    fmtX: { type: Function, default: (v) => new Date(v).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) },
    fmtTip: { type: Function, default: (v) => new Date(v).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) },
    fmtY: { type: Function, default: compact },
    fmtVal: { type: Function, default: (v) => v },
    fmtVal2: { type: Function, default: (v) => v },
    min: Number,
});

const el = ref();
const width = useWidth(el);
const hover = ref(null);
const gid = 'g' + Math.random().toString(36).slice(2, 8);
const pad = { t: 12, r: 8, b: 26, l: 44 };
const innerW = computed(() => width.value - pad.l - pad.r);
const innerH = computed(() => props.height - pad.t - pad.b);
const values = computed(() => props.data.map((d) => +d[props.yKey]));
const lo = computed(() => props.min ?? 0);
const max = computed(() => (props.min != null ? Math.ceil(Math.max(...values.value) + 1) : niceMax(Math.max(...values.value, 1))));
const step = computed(() => innerW.value / Math.max(1, props.data.length - 1));
const x = (i) => pad.l + i * step.value;
const y = (v) => pad.t + innerH.value - ((v - lo.value) / (max.value - lo.value || 1)) * innerH.value;
const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => lo.value + (max.value - lo.value) * f));

function smooth(pts) {
    if (!pts.length) return '';
    let d = `M${pts[0][0]},${pts[0][1]}`;
    for (let i = 1; i < pts.length; i++) {
        const [x0, y0] = pts[i - 1], [x1, y1] = pts[i];
        const cx = (x0 + x1) / 2;
        d += ` C${cx},${y0} ${cx},${y1} ${x1},${y1}`;
    }
    return d;
}
const pts = computed(() => props.data.map((d, i) => [x(i), y(+d[props.yKey])]));
const line = computed(() => smooth(pts.value));
const area = computed(() => (pts.value.length ? `${line.value} L${x(props.data.length - 1)},${pad.t + innerH.value} L${pad.l},${pad.t + innerH.value} Z` : ''));
const line2 = computed(() => null);
const showLabel = (i) => {
    const every = Math.ceil(props.data.length / Math.max(2, Math.floor(innerW.value / 60)));
    return i % every === 0 || i === props.data.length - 1;
};
</script>

<style scoped>
.chart-line { stroke-dasharray: 3000; stroke-dashoffset: 3000; animation: draw 1.1s ease-out forwards; }
@keyframes draw { to { stroke-dashoffset: 0; } }
</style>
