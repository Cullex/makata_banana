<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">Reports & analytics</h2>
                <p class="text-sm muted">{{ r ? `${date(r.range.from, { day: 'numeric', month: 'short', year: 'numeric' })} – ${date(r.range.to, { day: 'numeric', month: 'short', year: 'numeric' })}` : 'Loading…' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Tabs v-model="days" :tabs="[{ label: '7 days', value: 7 }, { label: '14 days', value: 14 }, { label: '30 days', value: 30 }, { label: '45 days', value: 45 }]" />
                <button class="btn-outline btn-sm h-10" @click="exportCsv"><Icon name="download" :size="14" /> Export CSV</button>
            </div>
        </div>

        <div v-if="r" class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            <Kpi label="Revenue" icon="cart" :value="money(r.summary.revenue, true)" :hint="`${num(r.summary.orders)} orders`" accent />
            <Kpi label="Volume sold" icon="boxes" :value="tonnes(r.summary.kg)" :hint="`Avg ${money(r.summary.avg_price)}/kg`" />
            <Kpi label="Avg ripening duration" icon="clock" :value="`${r.summary.avg_ripening_days} d`" hint="Room entry → ripe" />
            <Kpi label="Spoilage rate" icon="trash" :value="pct(r.summary.spoilage_rate, 2)" :hint="`${tonnes(r.summary.lost_kg)} written off`" />
        </div>
        <div v-else class="grid grid-cols-2 xl:grid-cols-4 gap-4"><div v-for="i in 4" :key="i" class="skeleton h-[124px] rounded-2xl" /></div>

        <!-- Sales -->
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div><p class="section-title">Sales report</p><p class="text-xs muted">Revenue by {{ period === 'daily' ? 'day' : period === 'weekly' ? 'week' : 'month' }}</p></div>
                <div class="flex gap-2">
                    <Tabs v-model="period" :tabs="[{ label: 'Daily', value: 'daily' }, { label: 'Weekly', value: 'weekly' }, { label: 'Monthly', value: 'monthly' }]" />
                </div>
            </div>
            <BarChart v-if="r" :data="r.sales" :series="[{ key: 'revenue', color: '#facc15', label: 'Revenue' }]" :height="260" :fmt-x="fmtPeriod" :fmt-tip="fmtPeriod" :fmt-val="(v) => money(v)" />
        </div>

        <div class="grid xl:grid-cols-2 gap-4">
            <div class="card card-pad">
                <p class="section-title">Sales by ripeness</p>
                <p class="text-xs muted mb-5">Ripe vs discounted overripe stock</p>
                <Donut v-if="r" :items="stageDonut" :center-value="money(r.summary.revenue, true)" center-label="revenue" :fmt="(v) => money(v, true)" />
                <div v-if="r" class="grid grid-cols-2 gap-3 mt-5">
                    <div v-for="s in r.by_stage" :key="s.stage" class="rounded-xl bg-ink-50 p-3">
                        <Chip :value="s.stage" />
                        <p class="font-extrabold mt-1.5 tabular">{{ tonnes(s.kg) }}</p>
                        <p class="text-[11px] muted">{{ money(s.revenue / s.kg) }}/kg avg</p>
                    </div>
                </div>
            </div>
            <div class="card card-pad">
                <p class="section-title">Ripening time distribution</p>
                <p class="text-xs muted mb-4">Batches by days from room entry to ripe, for quality control</p>
                <BarChart v-if="r" :data="r.ripening_distribution" x-key="days" :series="[{ key: 'batches', color: '#f97316', label: 'Batches' }]" :height="240" :fmt-x="(v) => v + 'd'" :fmt-tip="(v) => v + ' days'" :fmt-val="(v) => v + ' batches'" :fmt-y="(v) => Math.round(v)" />
            </div>
        </div>

        <div class="grid xl:grid-cols-2 gap-4">
            <div class="card card-pad">
                <p class="section-title">Weekly spoilage</p>
                <p class="text-xs muted mb-4">Received vs written off</p>
                <BarChart v-if="r" :data="r.spoilage" :series="[{ key: 'received', color: '#e7e5dc', label: 'Received' }, { key: 'lost', color: '#ef4444', label: 'Lost' }]" :height="240" :fmt-x="(v) => v.split('-')[1]" :fmt-val="kg" />
            </div>
            <div class="card card-pad">
                <p class="section-title">Current inventory by status</p>
                <p class="text-xs muted mb-4">Unripe, ripening and ripe stock levels</p>
                <BarChart v-if="r" :data="invBars" x-key="label" :series="stageSeries" :height="240" :fmt-val="kg" />
            </div>
        </div>

        <!-- Supplier performance -->
        <div class="card overflow-hidden">
            <div class="p-5 pb-3"><p class="section-title">Supplier performance</p><p class="text-xs muted">Volume, ripening consistency, spoilage and revenue generated</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px]">
                    <thead><tr><th class="table-head">Supplier</th><th class="table-head text-right">Received</th><th class="table-head text-right">Avg ripening</th><th class="table-head text-right">Lost</th><th class="table-head">Spoilage rate</th><th class="table-head text-right">Revenue</th></tr></thead>
                    <tbody>
                        <tr v-for="s in r?.suppliers" :key="s.name">
                            <td class="table-cell font-semibold">{{ s.name }}</td>
                            <td class="table-cell text-right tabular">{{ tonnes(s.received_kg) }}</td>
                            <td class="table-cell text-right tabular">{{ s.avg_ripening ? s.avg_ripening + ' d' : '—' }}</td>
                            <td class="table-cell text-right tabular text-red-600">{{ kg(s.lost_kg) }}</td>
                            <td class="table-cell">
                                <div class="flex items-center gap-2">
                                    <div class="w-24 h-1.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full" :class="s.spoilage_rate > 3 ? 'bg-red-500' : s.spoilage_rate > 1.5 ? 'bg-amber-500' : 'bg-emerald-500'" :style="{ width: Math.min(100, s.spoilage_rate * 15) + '%' }" /></div>
                                    <span class="text-xs font-semibold tabular">{{ pct(s.spoilage_rate, 2) }}</span>
                                </div>
                            </td>
                            <td class="table-cell text-right font-bold tabular">{{ money(s.revenue, true) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid xl:grid-cols-[1fr_420px] gap-4">
            <div class="card overflow-hidden">
                <div class="p-5 pb-3"><p class="section-title">Customer order history</p><p class="text-xs muted">Ranked by revenue</p></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px]">
                        <thead><tr><th class="table-head">Customer</th><th class="table-head text-right">Orders</th><th class="table-head text-right">Volume</th><th class="table-head text-right">Revenue</th><th class="table-head">Last order</th></tr></thead>
                        <tbody>
                            <tr v-for="c in r?.customers" :key="c.customer_id">
                                <td class="table-cell"><p class="font-semibold">{{ c.customer?.name }}</p><p class="text-[11px] muted capitalize">{{ c.customer?.type }} · {{ c.customer?.location }}</p></td>
                                <td class="table-cell text-right tabular">{{ c.orders }}</td>
                                <td class="table-cell text-right tabular">{{ tonnes(c.kg) }}</td>
                                <td class="table-cell text-right font-bold tabular">{{ money(c.revenue, true) }}</td>
                                <td class="table-cell text-ink-600">{{ ago(c.last_order) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card card-pad">
                <p class="section-title">Expected ripening</p>
                <p class="text-xs muted mb-4">Batches coming ready for sale</p>
                <div class="space-y-2">
                    <button v-for="b in r?.expected_ripening" :key="b.id" class="w-full flex items-center gap-3 rounded-xl border border-ink-200/70 p-3 text-left hover:border-brand-400 transition" @click="openBatch(b.id)">
                        <span class="grid place-items-center size-10 rounded-xl bg-orange-50 text-orange-600 shrink-0"><Icon name="calendar" :size="17" /></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold font-mono">{{ b.code }}</p>
                            <p class="text-[11px] muted">{{ b.location?.name }} · {{ tonnes(b.remaining_kg) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold">{{ date(b.expected_ripe_at, { weekday: 'short', day: 'numeric', month: 'short' }) }}</p>
                            <p class="text-[11px] muted">{{ ago(b.expected_ripe_at) }}</p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Icon from '../components/Icon.vue';
import Kpi from '../components/Kpi.vue';
import Chip from '../components/Chip.vue';
import Tabs from '../components/Tabs.vue';
import BarChart from '../components/charts/BarChart.vue';
import Donut from '../components/charts/Donut.vue';
import { api } from '../lib/api';
import { ago, date, kg, money, num, pct, STAGES, STATUSES, tonnes } from '../lib/format';
import { openBatch } from '../lib/store';

const r = ref(null);
const days = ref(30);
const period = ref('daily');

async function load() {
    r.value = await api.get('reports', { days: days.value, period: period.value });
}
watch([days, period], load);
onMounted(load);

const fmtPeriod = (v) => (period.value === 'daily' ? new Date(v).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : period.value === 'weekly' ? v.replace(/^\d+-/, '') : new Date(v + '-01').toLocaleDateString('en-GB', { month: 'short', year: '2-digit' }));
const stageDonut = computed(() => (r.value?.by_stage || []).map((s) => ({ label: STAGES[s.stage].label, value: +s.revenue, color: STAGES[s.stage].hex })));
const stageSeries = Object.entries(STAGES).map(([k, s]) => ({ key: k, color: s.hex, label: s.label }));
const invBars = computed(() => (r.value?.inventory || []).map((i) => ({ label: STATUSES[i.status].label, ...i.stages })));

function exportCsv() {
    const rows = [['Period', 'Orders', 'Kg', 'Revenue', 'Discount'], ...r.value.sales.map((s) => [s.label, s.orders, s.kg, s.revenue, s.discount])];
    const blob = new Blob([rows.map((x) => x.join(',')).join('\n')], { type: 'text/csv' });
    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: `makata-sales-${period.value}-${days.value}d.csv` });
    a.click();
}
</script>
