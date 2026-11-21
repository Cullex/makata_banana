<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">All batches</h2>
                <p class="text-sm muted">Full traceability from supplier truck to customer order.</p>
            </div>
            <Tabs v-model="filters.status" :tabs="statusTabs" />
        </div>

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center gap-2 p-4 border-b border-ink-100">
                <div class="relative flex-1 min-w-[200px]">
                    <Icon name="search" :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
                    <input v-model="filters.search" class="input h-9 pl-9" placeholder="Search batch code or supplier…" />
                </div>
                <select v-model="filters.stage" class="input h-9 w-auto">
                    <option value="">All stages</option>
                    <option v-for="(s, k) in STAGES" :key="k" :value="k">{{ s.label }}</option>
                </select>
                <select v-model="filters.supplier_id" class="input h-9 w-auto">
                    <option value="">All suppliers</option>
                    <option v-for="s in store.meta?.suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="filters.grade" class="input h-9 w-auto">
                    <option value="">Any grade</option>
                    <option v-for="g in ['A', 'B', 'C']" :key="g" :value="g">Grade {{ g }}</option>
                </select>
                <select v-model="filters.sort" class="input h-9 w-auto">
                    <option value="recent">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="fifo">FIFO (ripe date)</option>
                    <option value="qty">Largest stock</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px]">
                    <thead>
                        <tr><th class="table-head">Batch</th><th class="table-head">Supplier</th><th class="table-head">Arrived</th><th class="table-head">Status</th><th class="table-head">Stage</th><th class="table-head">Grade</th><th class="table-head">Location</th><th class="table-head">Shelf life</th><th class="table-head text-right">Remaining</th><th class="table-head w-36">Sell-through</th></tr>
                    </thead>
                    <tbody :class="loading ? 'opacity-60' : ''" class="transition">
                        <tr v-for="b in list.data" :key="b.id" class="hover:bg-brand-50/50 cursor-pointer" @click="openBatch(b.id)">
                            <td class="table-cell"><p class="font-mono font-bold">{{ b.code }}</p><p class="text-[11px] muted">{{ b.variety }}</p></td>
                            <td class="table-cell">{{ b.supplier?.name }}</td>
                            <td class="table-cell text-ink-600 whitespace-nowrap">{{ date(b.arrived_at) }} <span class="text-[11px] muted">· {{ b.age_days }}d</span></td>
                            <td class="table-cell"><Chip :value="b.status" type="status" :dot="false" /></td>
                            <td class="table-cell"><Chip :value="b.stage" /></td>
                            <td class="table-cell"><span class="chip bg-ink-100 text-ink-700">{{ b.grade }}</span></td>
                            <td class="table-cell text-ink-600">{{ b.location?.code || '—' }}</td>
                            <td class="table-cell"><ShelfLife :days="b.shelf_days_left" /></td>
                            <td class="table-cell text-right font-semibold tabular">{{ kg(b.remaining_kg) }}</td>
                            <td class="table-cell">
                                <div class="h-1.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full bg-ink-800 rounded-full" :style="{ width: (1 - b.remaining_kg / b.initial_kg) * 100 + '%' }" /></div>
                                <p class="text-[10px] muted mt-1 tabular">{{ Math.round((1 - b.remaining_kg / b.initial_kg) * 100) }}% of {{ tonnes(b.initial_kg) }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Empty v-if="!loading && !list.data?.length" icon="search" title="No batches match" text="Try clearing some filters." />
            </div>
            <Pager :meta="list" @page="(p) => fetch(p)" />
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import Icon from '../components/Icon.vue';
import Chip from '../components/Chip.vue';
import Tabs from '../components/Tabs.vue';
import Empty from '../components/Empty.vue';
import Pager from '../components/Pager.vue';
import ShelfLife from '../components/ShelfLife.vue';
import { api } from '../lib/api';
import { date, kg, STAGES, tonnes } from '../lib/format';
import { loadMeta, onDataChanged, openBatch, store } from '../lib/store';

const list = ref({});
const loading = ref(true);
const filters = reactive({ status: 'in_stock', search: '', stage: '', supplier_id: '', grade: '', sort: 'recent' });
const statusTabs = [
    { label: 'In stock', value: 'in_stock' },
    { label: 'Receiving', value: 'receiving' },
    { label: 'Pre-ripening', value: 'pre_ripening' },
    { label: 'Ripening', value: 'ripening' },
    { label: 'Sales', value: 'sales' },
    { label: 'Closed', value: 'depleted,spoiled' },
    { label: 'All', value: '' },
];

async function fetch(page = 1) {
    loading.value = true;
    list.value = await api.get('batches', { ...filters, page, per_page: 15 });
    loading.value = false;
}
let t;
watch(filters, () => (clearTimeout(t), (t = setTimeout(() => fetch(1), 250))));
let off;
onMounted(() => (loadMeta(), fetch(), (off = onDataChanged(() => fetch(list.value.current_page)))));
onBeforeUnmount(() => off());
</script>
