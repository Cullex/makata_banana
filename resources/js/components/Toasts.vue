<template>
    <div class="fixed bottom-4 right-4 left-4 sm:left-auto z-[100] flex flex-col gap-2 items-end pointer-events-none">
        <TransitionGroup enter-from-class="opacity-0 translate-y-2" leave-to-class="opacity-0 translate-x-4" enter-active-class="transition duration-200" leave-active-class="transition duration-200">
            <div v-for="t in store.toasts" :key="t.id" class="pointer-events-auto flex items-center gap-3 rounded-xl bg-ink-900 text-white pl-3 pr-4 py-3 shadow-pop text-sm max-w-sm">
                <span class="grid place-items-center size-6 rounded-full shrink-0" :class="t.type === 'error' ? 'bg-red-500' : 'bg-brand-400 text-ink-900'">
                    <Icon :name="t.type === 'error' ? 'x' : 'check'" :size="14" :stroke="2.5" />
                </span>
                {{ t.message }}
            </div>
        </TransitionGroup>
    </div>
</template>

<script setup>
import Icon from './Icon.vue';
import { store } from '../lib/store';
</script>
