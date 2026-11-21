<template>
    <span v-if="days != null" class="inline-flex items-center gap-1.5 text-xs font-semibold" :class="days <= 1 ? 'text-red-600' : days <= 2.5 ? 'text-amber-700' : 'text-emerald-700'">
        <span class="flex gap-0.5">
            <span v-for="i in 6" :key="i" class="w-1.5 h-3 rounded-sm" :class="i <= Math.ceil(Math.max(0, days)) ? (days <= 1 ? 'bg-red-500' : days <= 2.5 ? 'bg-amber-500' : 'bg-emerald-500') : 'bg-ink-200'" />
        </span>
        {{ days > 0 ? `${days}d` : 'Expired' }}
    </span>
    <span v-else class="muted text-xs">—</span>
</template>

<script setup>
defineProps({ days: Number });
</script>
