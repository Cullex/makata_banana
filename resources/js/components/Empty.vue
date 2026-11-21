<template>
    <div class="flex flex-col items-center justify-center text-center py-12 px-6">
        <span class="grid place-items-center size-14 rounded-2xl bg-brand-100 text-brand-600 mb-4"><Icon :name="icon" :size="26" /></span>
        <p class="font-bold">{{ title }}</p>
        <p v-if="text" class="text-sm muted mt-1 max-w-sm">{{ text }}</p>
        <div v-if="$slots.default" class="mt-4"><slot /></div>
    </div>
</template>

<script setup>
import Icon from './Icon.vue';
defineProps({ icon: { type: String, default: 'banana' }, title: String, text: String });
</script>
