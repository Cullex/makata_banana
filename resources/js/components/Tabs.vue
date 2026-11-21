<template>
    <div class="inline-flex p-1 rounded-xl bg-ink-100 border border-ink-200/60 overflow-x-auto max-w-full">
        <button
            v-for="t in tabs"
            :key="t.value"
            class="h-8 px-3.5 rounded-lg text-xs font-semibold transition whitespace-nowrap flex items-center gap-1.5"
            :class="modelValue === t.value ? 'bg-white text-ink-900 shadow-card' : 'text-ink-500 hover:text-ink-800'"
            @click="$emit('update:modelValue', t.value)"
        >
            {{ t.label }}
            <span v-if="t.count != null" class="text-[10px] px-1.5 rounded-full" :class="modelValue === t.value ? 'bg-brand-200 text-brand-900' : 'bg-ink-200 text-ink-600'">{{ t.count }}</span>
        </button>
    </div>
</template>

<script setup>
defineProps({ tabs: Array, modelValue: [String, Number] });
defineEmits(['update:modelValue']);
</script>
