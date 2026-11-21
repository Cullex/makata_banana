<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
            <div v-if="open" class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-ink-950/50 backdrop-blur-sm" @mousedown.self="$emit('close')">
                <div class="bg-white w-full rounded-t-3xl sm:rounded-3xl shadow-pop max-h-[92dvh] flex flex-col animate-fade-up" :class="width">
                    <div class="flex items-start gap-3 px-6 pt-5 pb-4 border-b border-ink-100">
                        <span v-if="icon" class="grid place-items-center size-10 rounded-xl bg-brand-100 text-brand-700 shrink-0"><Icon :name="icon" :size="20" /></span>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base font-bold">{{ title }}</h3>
                            <p v-if="subtitle" class="text-xs muted mt-0.5">{{ subtitle }}</p>
                        </div>
                        <button class="btn-icon btn-ghost -mr-2 -mt-1" @click="$emit('close')"><Icon name="x" /></button>
                    </div>
                    <div class="overflow-y-auto px-6 py-5 scrollbar-thin"><slot /></div>
                    <div v-if="$slots.footer" class="flex items-center justify-end gap-2 px-6 py-4 border-t border-ink-100 bg-ink-50/60 rounded-b-3xl"><slot name="footer" /></div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { onBeforeUnmount, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({ open: Boolean, title: String, subtitle: String, icon: String, width: { type: String, default: 'sm:max-w-lg' } });
const emit = defineEmits(['close']);
const esc = (e) => e.key === 'Escape' && emit('close');
watch(() => props.open, (v) => (v ? document.addEventListener('keydown', esc) : document.removeEventListener('keydown', esc)));
onBeforeUnmount(() => document.removeEventListener('keydown', esc));
</script>
