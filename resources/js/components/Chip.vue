<template>
    <span class="chip" :class="def.chip">
        <span v-if="dot" class="size-1.5 rounded-full" :style="{ background: def.hex || 'currentColor' }" />
        {{ def.label }}
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { STAGES, STATUSES, ORDER_STATUSES, ROOM_STATUSES } from '../lib/format';

const props = defineProps({ value: String, type: { type: String, default: 'stage' }, dot: { type: Boolean, default: true } });
const maps = { stage: STAGES, status: STATUSES, order: ORDER_STATUSES, room: ROOM_STATUSES };
const def = computed(() => maps[props.type]?.[props.value] || { label: props.value, chip: 'bg-ink-100 text-ink-600' });
</script>
