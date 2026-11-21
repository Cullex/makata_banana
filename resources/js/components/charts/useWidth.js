import { onBeforeUnmount, onMounted, ref } from 'vue';

export function useWidth(el, initial = 600) {
    const width = ref(initial);
    let ro;
    onMounted(() => {
        ro = new ResizeObserver(([e]) => (width.value = Math.max(120, e.contentRect.width)));
        el.value && ro.observe(el.value);
    });
    onBeforeUnmount(() => ro?.disconnect());
    return width;
}

export function niceMax(v) {
    if (v <= 0) return 1;
    const p = 10 ** Math.floor(Math.log10(v));
    const n = v / p;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p;
}

export const compact = (v) => new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(v);
