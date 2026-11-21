<template>
    <div class="flex items-center gap-5" :class="vertical ? 'flex-col' : 'flex-col sm:flex-row'">
        <div class="relative shrink-0" :style="{ width: size + 'px', height: size + 'px' }">
            <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`" class="-rotate-90">
                <circle :cx="c" :cy="c" :r="r" fill="none" stroke="var(--color-ink-100)" :stroke-width="thick" />
                <circle
                    v-for="(s, i) in arcs" :key="i" :cx="c" :cy="c" :r="r" fill="none" :stroke="s.color" :stroke-width="thick"
                    :stroke-dasharray="`${s.len} ${circ}`" :stroke-dashoffset="-s.offset" class="transition-all duration-700"
                    @mouseenter="hover = i" @mouseleave="hover = null"
                />
            </svg>
            <div class="absolute inset-0 grid place-items-center text-center pointer-events-none">
                <div>
                    <p class="text-[22px] font-extrabold leading-none tabular">{{ hover != null ? fmt(items[hover].value) : centerValue }}</p>
                    <p class="text-[11px] muted mt-1">{{ hover != null ? items[hover].label : centerLabel }}</p>
                </div>
            </div>
        </div>
        <div v-if="legend" class="flex-1 w-full space-y-2.5">
            <div v-for="(s, i) in items" :key="s.label" class="flex items-center gap-2.5 text-sm" @mouseenter="hover = i" @mouseleave="hover = null">
                <span class="size-3 rounded" :style="{ background: s.color }" />
                <span class="text-ink-600">{{ s.label }}</span>
                <span class="ml-auto font-bold tabular">{{ fmt(s.value) }}</span>
                <span class="w-11 text-right text-xs muted tabular">{{ totalV ? Math.round((s.value / totalV) * 100) : 0 }}%</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    items: Array, size: { type: Number, default: 170 }, thick: { type: Number, default: 20 },
    centerValue: [String, Number], centerLabel: String, legend: { type: Boolean, default: true },
    fmt: { type: Function, default: (v) => v }, vertical: Boolean,
});
const hover = ref(null);
const c = computed(() => props.size / 2);
const r = computed(() => props.size / 2 - props.thick / 2 - 2);
const circ = computed(() => 2 * Math.PI * r.value);
const totalV = computed(() => props.items.reduce((a, b) => a + b.value, 0));
const arcs = computed(() => {
    let off = 0;
    const gap = props.items.filter((i) => i.value > 0).length > 1 ? 3 : 0;
    return props.items.map((s) => {
        const len = totalV.value ? (s.value / totalV.value) * circ.value : 0;
        const a = { color: s.color, len: Math.max(0, len - gap), offset: off };
        off += len;
        return a;
    });
});
</script>
