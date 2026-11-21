<template>
    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between gap-3 px-4 py-3 text-xs">
        <p class="muted">Showing <b class="text-ink-800">{{ meta.from }}–{{ meta.to }}</b> of <b class="text-ink-800">{{ meta.total }}</b></p>
        <div class="flex items-center gap-1">
            <button class="btn-outline btn-sm" :disabled="meta.current_page <= 1" @click="$emit('page', meta.current_page - 1)"><Icon name="chevronLeft" :size="14" /></button>
            <span class="px-2 font-semibold tabular">{{ meta.current_page }} / {{ meta.last_page }}</span>
            <button class="btn-outline btn-sm" :disabled="meta.current_page >= meta.last_page" @click="$emit('page', meta.current_page + 1)"><Icon name="chevronRight" :size="14" /></button>
        </div>
    </div>
</template>

<script setup>
import Icon from './Icon.vue';
defineProps({ meta: Object });
defineEmits(['page']);
</script>
