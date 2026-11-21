<template>
    <button class="w-full text-left flex gap-3 px-4 py-3 hover:bg-ink-50 transition border-b border-ink-100 last:border-0" @click="go">
        <span class="grid place-items-center size-8 rounded-lg shrink-0 mt-0.5" :class="style.bg">
            <Icon :name="style.icon" :size="16" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-[13px] font-semibold text-ink-900 leading-snug">{{ alert.title }}</span>
            <span class="block text-xs text-ink-500 mt-0.5 leading-snug">{{ alert.body }}</span>
        </span>
        <span class="text-[10px] text-ink-400 whitespace-nowrap mt-1">{{ ago(alert.at) }}</span>
    </button>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import Icon from './Icon.vue';
import { ago } from '../lib/format';
import { openBatch } from '../lib/store';

const props = defineProps({ alert: Object });
const emit = defineEmits(['click']);
const router = useRouter();

const style = computed(
    () =>
        ({
            danger: { bg: 'bg-red-100 text-red-600', icon: 'alert' },
            warning: { bg: 'bg-amber-100 text-amber-700', icon: 'clock' },
            success: { bg: 'bg-brand-200 text-brand-800', icon: 'sparkle' },
            info: { bg: 'bg-sky-100 text-sky-700', icon: 'clipboard' },
        })[props.alert.level],
);

function go() {
    emit('click');
    if (props.alert.batch_id) openBatch(props.alert.batch_id);
    else if (props.alert.room_id) router.push('/ripening');
}
</script>
