<template>
    <div>
        <div class="flex h-2.5 rounded-full overflow-hidden bg-ink-100" :class="height">
            <div v-for="s in parts" :key="s.key" class="h-full transition-all duration-700 first:rounded-l-full last:rounded-r-full" :style="{ width: s.pct + '%', background: s.hex }" :title="`${s.label}: ${s.kg} kg`" />
        </div>
        <div v-if="legend" class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
            <div v-for="s in parts" :key="s.key" class="flex items-center gap-2 text-xs">
                <span class="size-2.5 rounded-sm" :style="{ background: s.hex }" />
                <span class="muted">{{ s.label }}</span>
                <span class="ml-auto font-semibold tabular">{{ tonnes(s.kg) }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { STAGES, tonnes } from '../lib/format';

const props = defineProps({ stages: Object, legend: Boolean, height: String });
const parts = computed(() => {
    const total = Object.values(props.stages || {}).reduce((a, b) => a + b, 0) || 1;
    return Object.entries(STAGES).map(([key, s]) => ({ key, ...s, kg: props.stages?.[key] || 0, pct: ((props.stages?.[key] || 0) / total) * 100 }));
});
</script>
