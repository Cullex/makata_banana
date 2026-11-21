<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">Inventory control</h2>
                <p class="text-sm muted">Real-time stock by ripeness stage and zone, FIFO rotation and loss tracking.</p>
            </div>
            <Tabs v-model="tab" :tabs="[{ label: 'Stock levels', value: 'stock' }, { label: 'Movements', value: 'moves' }, { label: 'Loss tracking', value: 'losses' }]" />
        </div>

        <template v-if="tab === 'stock'">
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                <Kpi v-for="(s, key) in STAGES" :key="key" :label="`${s.label} stock`" icon="banana" :value="tonnes(inv?.totals[key] || 0)" :hint="hintFor(key)" :accent="key === 'ripe'" />
            </div>

            <div class="grid xl:grid-cols-[1fr_360px] gap-4">
                <div class="card overflow-hidden">
                    <div class="p-5 pb-3 flex items-center justify-between">
                        <div><p class="section-title">Stock matrix</p><p class="text-xs muted">Zones × ripeness stage (kg)</p></div>
                        <p class="text-xs muted">Stock value at cost <b class="text-ink-900">{{ money(inv?.value, true) }}</b></p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead>
                                <tr>
                                    <th class="table-head">Zone</th>
                                    <th v-for="(s, k) in STAGES" :key="k" class="table-head text-right"><span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-full" :style="{ background: s.hex }" />{{ s.label }}</span></th>
                                    <th class="table-head text-right">Total</th>
                                    <th class="table-head w-40">Mix</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="z in inv?.matrix" :key="z.zone">
                                    <td class="table-cell"><p class="font-semibold">{{ z.label }}</p><p class="text-[11px] muted">{{ z.batches }} batches</p></td>
                                    <td v-for="(s, k) in STAGES" :key="k" class="table-cell text-right tabular" :class="z.stages[k] ? 'font-semibold' : 'text-ink-300'">
                                        <span v-if="z.stages[k]" class="inline-block rounded-md px-1.5 py-0.5" :style="{ background: s.hex + Math.round(Math.min(1, z.stages[k] / maxCell) * 70 + 10).toString(16).padStart(2, '0') }">{{ num(z.stages[k]) }}</span>
                                        <span v-else>–</span>
                                    </td>
                                    <td class="table-cell text-right font-extrabold tabular">{{ num(z.kg) }}</td>
                                    <td class="table-cell"><StageBar :stages="z.stages" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card card-pad">
                    <p class="section-title mb-4">Stock by stage</p>
                    <Donut v-if="inv" :items="donut" :center-value="tonnes(total)" center-label="in stock" :fmt="tonnes" vertical :size="180" />
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="p-5 pb-3 flex flex-wrap items-center justify-between gap-3">
                    <div><p class="section-title">Sales inventory · FIFO order</p><p class="text-xs muted">Orders are allocated top to bottom: oldest ripe stock first</p></div>
                    <span class="chip bg-brand-100 text-brand-800"><Icon name="info" :size="12" /> Auto-allocated by the sales module</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px]">
                        <thead><tr><th class="table-head w-12">#</th><th class="table-head">Batch</th><th class="table-head">Supplier</th><th class="table-head">Location</th><th class="table-head">Ripe since</th><th class="table-head">Shelf life</th><th class="table-head">Stage</th><th class="table-head text-right">Available</th></tr></thead>
                        <tbody>
                            <tr v-for="(b, i) in inv?.fifo" :key="b.id" class="hover:bg-brand-50/50 cursor-pointer" @click="openBatch(b.id)">
                                <td class="table-cell"><span class="grid place-items-center size-7 rounded-lg text-xs font-extrabold" :class="i === 0 ? 'bg-brand-400' : 'bg-ink-100 text-ink-500'">{{ i + 1 }}</span></td>
                                <td class="table-cell font-mono font-bold">{{ b.code }}</td>
                                <td class="table-cell">{{ b.supplier?.name }}</td>
                                <td class="table-cell text-ink-600">{{ b.location?.name }}</td>
                                <td class="table-cell text-ink-600">{{ ago(b.ripe_at) }}</td>
                                <td class="table-cell"><ShelfLife :days="b.shelf_days_left" /></td>
                                <td class="table-cell"><Chip :value="b.stage" /></td>
                                <td class="table-cell text-right font-bold tabular">{{ kg(b.remaining_kg) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <Empty v-if="inv && !inv.fifo.length" icon="boxes" title="No sales stock" />
                </div>
            </div>
        </template>

        <div v-if="tab === 'moves'" class="card overflow-hidden">
            <div class="p-5 pb-3"><p class="section-title">Batch movements</p><p class="text-xs muted">Every transfer between receiving, ripening and sales</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px]">
                    <thead><tr><th class="table-head">When</th><th class="table-head">Batch</th><th class="table-head">From</th><th class="table-head" /><th class="table-head">To</th><th class="table-head text-right">Qty</th><th class="table-head">By</th><th class="table-head">Note</th></tr></thead>
                    <tbody>
                        <tr v-for="m in moves.data" :key="m.id" class="hover:bg-ink-50 cursor-pointer" @click="openBatch(m.batch_id)">
                            <td class="table-cell text-ink-600 whitespace-nowrap">{{ dateTime(m.created_at) }}</td>
                            <td class="table-cell font-mono font-bold">{{ m.batch?.code }}</td>
                            <td class="table-cell"><Chip v-if="m.from_status" :value="m.from_status" type="status" :dot="false" /><span v-else class="muted text-xs">Supplier</span></td>
                            <td class="table-cell text-ink-300"><Icon name="arrowRight" :size="14" /></td>
                            <td class="table-cell"><Chip :value="m.to_status" type="status" :dot="false" /> <span class="text-[11px] muted ml-1">{{ m.to?.code }}</span></td>
                            <td class="table-cell text-right tabular font-semibold">{{ kg(m.quantity_kg) }}</td>
                            <td class="table-cell">{{ m.user?.name }}</td>
                            <td class="table-cell text-ink-500 text-xs">{{ m.note }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pager :meta="moves" @page="loadMoves" />
        </div>

        <template v-if="tab === 'losses'">
            <div class="grid xl:grid-cols-[360px_1fr] gap-4">
                <div class="card card-pad">
                    <p class="section-title">Losses by reason</p>
                    <p class="text-xs muted mb-4">Last 30 days</p>
                    <Donut v-if="losses" :items="lossDonut" :center-value="tonnes(lossDonut.reduce((a, b) => a + b.value, 0))" center-label="lost" :fmt="kg" vertical :size="170" />
                </div>
                <div class="card overflow-hidden">
                    <div class="p-5 pb-3"><p class="section-title">Waste log</p><p class="text-xs muted">Damaged, spoiled and overripe write-offs</p></div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px]">
                            <thead><tr><th class="table-head">When</th><th class="table-head">Batch</th><th class="table-head">Supplier</th><th class="table-head">Reason</th><th class="table-head text-right">Qty</th><th class="table-head">Logged by</th></tr></thead>
                            <tbody>
                                <tr v-for="l in losses?.items.data" :key="l.id" class="hover:bg-ink-50 cursor-pointer" @click="openBatch(l.batch_id)">
                                    <td class="table-cell text-ink-600 whitespace-nowrap">{{ dateTime(l.created_at) }}</td>
                                    <td class="table-cell font-mono font-bold">{{ l.batch?.code }}</td>
                                    <td class="table-cell">{{ l.batch?.supplier?.name }}</td>
                                    <td class="table-cell"><span class="chip capitalize" :class="reasonChip[l.reason]">{{ l.reason }}</span></td>
                                    <td class="table-cell text-right font-bold text-red-600 tabular">−{{ kg(l.quantity_kg) }}</td>
                                    <td class="table-cell">{{ l.user?.name }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Icon from '../components/Icon.vue';
import Kpi from '../components/Kpi.vue';
import Chip from '../components/Chip.vue';
import Tabs from '../components/Tabs.vue';
import Empty from '../components/Empty.vue';
import Pager from '../components/Pager.vue';
import StageBar from '../components/StageBar.vue';
import ShelfLife from '../components/ShelfLife.vue';
import Donut from '../components/charts/Donut.vue';
import { api } from '../lib/api';
import { ago, dateTime, kg, money, num, STAGES, tonnes } from '../lib/format';
import { onDataChanged, openBatch } from '../lib/store';

const tab = ref('stock');
const inv = ref(null);
const moves = ref({});
const losses = ref(null);

const total = computed(() => Object.values(inv.value?.totals || {}).reduce((a, b) => a + b, 0));
const donut = computed(() => Object.entries(STAGES).map(([k, s]) => ({ label: s.label, value: inv.value?.totals[k] || 0, color: s.hex })));
const maxCell = computed(() => Math.max(1, ...(inv.value?.matrix || []).flatMap((z) => Object.values(z.stages))));
const reasonColors = { spoiled: '#ef4444', damaged: '#f97316', overripe: '#9a5b13', handling: '#a8a495', other: '#64748b' };
const reasonChip = { spoiled: 'bg-red-100 text-red-700', damaged: 'bg-orange-100 text-orange-800', overripe: 'bg-amber-100 text-amber-900', handling: 'bg-ink-100 text-ink-700', other: 'bg-slate-100 text-slate-700' };
const lossDonut = computed(() => (losses.value?.by_reason || []).map((r) => ({ label: r.reason[0].toUpperCase() + r.reason.slice(1), value: +r.kg, color: reasonColors[r.reason] })));

const hintFor = (k) => ({ green: 'Pre-ripening & early cycle', turning: 'Mid ripening cycle', ripe: 'Ready for sale', overripe: 'Discount & sell first' })[k];

async function load() {
    inv.value = await api.get('inventory');
}
async function loadMoves(page = 1) {
    moves.value = await api.get('inventory/movements', { page });
}
async function loadLosses() {
    losses.value = await api.get('inventory/losses');
}
watch(tab, (t) => (t === 'moves' ? loadMoves() : t === 'losses' ? loadLosses() : load()));

let off;
onMounted(() => {
    load();
    off = onDataChanged(() => (tab.value === 'stock' ? load() : tab.value === 'moves' ? loadMoves() : loadLosses()));
});
onBeforeUnmount(() => off());
</script>
