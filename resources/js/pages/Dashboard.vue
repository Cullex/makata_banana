<template>
    <div class="space-y-6">
        <!-- Greeting -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm muted">{{ today }}</p>
                <h2 class="text-2xl font-extrabold tracking-tight mt-0.5">{{ greeting }}, {{ store.user?.name.split(' ')[0] }} <span class="inline-block origin-bottom-right animate-[wave_2s_ease-in-out_1]">👋</span></h2>
            </div>
            <div class="flex items-center gap-2 text-xs muted">
                <span class="relative flex size-2"><span class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-75 animate-ping" /><span class="relative inline-flex rounded-full size-2 bg-emerald-500" /></span>
                Live · updated {{ updated ? ago(updated) : '…' }}
                <button class="btn-ghost btn-sm" @click="load"><Icon name="refresh" :size="14" /></button>
            </div>
        </div>

        <!-- KPIs -->
        <div v-if="d" class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            <Kpi label="Stock on hand" icon="boxes" :value="tonnes(d.kpis.stock_kg)" :hint="`${d.kpis.stock_batches} active batches`" accent />
            <Kpi label="Ready to sell (ripe)" icon="banana" :value="tonnes(d.kpis.sellable_kg)" :hint="`${daysCover} days of cover at current pace`" />
            <Kpi label="Revenue today" icon="cart" :value="money(d.kpis.revenue_today, true)" :delta="revDelta" :hint="`${d.kpis.orders_today} orders · ${tonnes(d.kpis.kg_sold_today)}`" />
            <Kpi label="Spoilage rate (30d)" icon="trash" :value="pct(d.kpis.spoilage_rate)" :hint="`Avg ripening ${d.kpis.avg_ripening_days} days`" />
        </div>
        <div v-else class="grid grid-cols-2 xl:grid-cols-4 gap-4"><div v-for="i in 4" :key="i" class="skeleton h-[124px] rounded-2xl" /></div>

        <!-- Pipeline + stage mix -->
        <div class="grid xl:grid-cols-[1fr_380px] gap-4">
            <div class="card card-pad">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <p class="section-title">Warehouse flow</p>
                        <p class="text-xs muted">Live stock through each zone, oldest first</p>
                    </div>
                    <RouterLink to="/inventory" class="btn-ghost btn-sm">Inventory <Icon name="arrowRight" :size="14" /></RouterLink>
                </div>
                <div v-if="d" class="grid grid-cols-2 md:grid-cols-4 gap-3 relative">
                    <RouterLink
                        v-for="(z, i) in pipeline" :key="z.key" :to="z.to"
                        class="relative group rounded-2xl border border-ink-200/70 p-4 hover:border-brand-400 hover:shadow-brand/30 hover:-translate-y-0.5 transition bg-gradient-to-b from-white to-ink-50/60"
                    >
                        <div class="flex items-center gap-2 mb-4">
                            <span class="grid place-items-center size-8 rounded-lg text-white" :style="{ background: z.color }"><Icon :name="z.icon" :size="16" /></span>
                            <span class="text-xs font-bold text-ink-600">{{ i + 1 }}. {{ z.label }}</span>
                        </div>
                        <p class="text-2xl font-extrabold tabular tracking-tight">{{ tonnes(z.kg) }}</p>
                        <p class="text-xs muted mt-0.5">{{ z.batches }} batch{{ z.batches === 1 ? '' : 'es' }}</p>
                        <div class="mt-3 h-1.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full transition-all duration-700" :style="{ width: (z.kg / maxZone) * 100 + '%', background: z.color }" /></div>
                        <span v-if="i < 3" class="hidden md:grid absolute -right-[15px] top-1/2 -translate-y-1/2 z-10 size-6 place-items-center rounded-full bg-white border border-ink-200 text-ink-400"><Icon name="chevronRight" :size="13" :stroke="2.4" /></span>
                    </RouterLink>
                </div>
                <div v-if="d" class="mt-5 pt-5 border-t border-ink-100">
                    <div class="flex items-center justify-between text-xs mb-2"><span class="font-semibold text-ink-600">Ripeness mix across all stock</span><span class="muted">{{ tonnes(d.kpis.stock_kg) }}</span></div>
                    <StageBar :stages="d.by_stage" legend />
                </div>
            </div>

            <div class="card card-pad">
                <p class="section-title">Today at a glance</p>
                <p class="text-xs muted mb-4">Movements since midnight</p>
                <div v-if="d" class="space-y-3">
                    <div v-for="row in glance" :key="row.label" class="flex items-center gap-3 rounded-xl bg-ink-50 p-3">
                        <span class="grid place-items-center size-9 rounded-lg" :class="row.bg"><Icon :name="row.icon" :size="17" /></span>
                        <div class="flex-1 min-w-0"><p class="text-xs muted">{{ row.label }}</p><p class="font-bold tabular">{{ row.value }}</p></div>
                        <span v-if="row.sub" class="text-[11px] muted">{{ row.sub }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales + Alerts -->
        <div class="grid xl:grid-cols-[1fr_400px] gap-4">
            <div class="card card-pad">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <p class="section-title">Sales trend</p>
                        <p class="text-xs muted">Last 14 days</p>
                    </div>
                    <Tabs v-model="metric" :tabs="[{ label: 'Revenue', value: 'revenue' }, { label: 'Volume (kg)', value: 'kg' }, { label: 'Orders', value: 'orders' }]" />
                </div>
                <div v-if="d" class="flex items-baseline gap-3 mb-2">
                    <p class="text-3xl font-extrabold tabular tracking-tight">{{ metric === 'revenue' ? money(sum, true) : metric === 'kg' ? tonnes(sum) : num(sum) }}</p>
                    <p class="text-xs muted">total · 14 days</p>
                </div>
                <AreaChart v-if="d" :data="d.sales" x-key="date" :y-key="metric" :height="250" :fmt-val="(v) => (metric === 'revenue' ? money(v) : metric === 'kg' ? kg(v) : `${v} orders`)" />
                <div v-else class="skeleton h-[250px]" />
            </div>

            <div class="card flex flex-col">
                <div class="flex items-center justify-between px-5 pt-5 pb-3">
                    <div>
                        <p class="section-title">Alerts</p>
                        <p class="text-xs muted">Ripe batches, spoilage risk & inspections</p>
                    </div>
                    <span class="chip bg-red-50 text-red-600" v-if="store.alerts.length">{{ store.alerts.length }} open</span>
                </div>
                <div class="flex-1 overflow-y-auto max-h-[330px] scrollbar-thin border-t border-ink-100">
                    <AlertItem v-for="a in store.alerts" :key="a.id" :alert="a" />
                    <Empty v-if="!store.alerts.length" title="All clear" text="Nothing needs your attention right now." />
                </div>
            </div>
        </div>

        <!-- Rooms + FIFO -->
        <div class="grid xl:grid-cols-[1fr_400px] gap-4">
            <div class="card card-pad">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="section-title">Ripening rooms</p>
                        <p class="text-xs muted">Live sensor readings</p>
                    </div>
                    <RouterLink v-if="can.warehouse()" to="/ripening" class="btn-ghost btn-sm">Control rooms <Icon name="arrowRight" :size="14" /></RouterLink>
                </div>
                <div v-if="d" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div v-for="r in d.rooms" :key="r.id" class="rounded-2xl border border-ink-200/70 p-4" :class="r.status === 'maintenance' ? 'bg-ink-50 opacity-70' : ''">
                        <div class="flex items-center justify-between">
                            <p class="font-bold text-sm">{{ r.code }}</p>
                            <Chip :value="r.status" type="room" />
                        </div>
                        <div class="flex items-end justify-between mt-3">
                            <p class="text-2xl font-extrabold tabular">{{ r.live.temperature }}<span class="text-sm muted font-semibold">°C</span></p>
                            <div class="text-right text-[11px] muted leading-tight">
                                <p><Icon name="droplet" :size="11" class="inline -mt-0.5" /> {{ r.live.humidity }}%</p>
                                <p><Icon name="wind" :size="11" class="inline -mt-0.5" /> {{ r.live.ethylene }} ppm</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="flex justify-between text-[11px] mb-1"><span class="muted">{{ r.batch_count }} batches</span><span class="font-semibold tabular">{{ Math.round((r.load_kg / r.capacity_kg) * 100) }}%</span></div>
                            <div class="h-1.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full bg-brand-400" :style="{ width: Math.min(100, (r.load_kg / r.capacity_kg) * 100) + '%' }" /></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <div class="flex items-center justify-between mb-1">
                    <p class="section-title">Next out (FIFO)</p>
                    <span class="chip bg-brand-100 text-brand-800">First in, first out</span>
                </div>
                <p class="text-xs muted mb-4">Sales will allocate from these batches first</p>
                <div v-if="d" class="space-y-2">
                    <button v-for="(b, i) in d.fifo_next" :key="b.id" class="w-full flex items-center gap-3 rounded-xl border border-ink-200/70 p-3 hover:border-brand-400 text-left transition" @click="openBatch(b.id)">
                        <span class="grid place-items-center size-8 rounded-lg font-extrabold text-sm" :class="i === 0 ? 'bg-brand-400 text-ink-900' : 'bg-ink-100 text-ink-500'">{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold font-mono">{{ b.code }}</p>
                            <p class="text-[11px] muted truncate">{{ b.supplier?.name }} · ripe {{ ago(b.ripe_at) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold tabular">{{ tonnes(b.remaining_kg) }}</p>
                            <Chip :value="b.stage" />
                        </div>
                    </button>
                    <Empty v-if="!d.fifo_next.length" icon="boxes" title="No sales stock" text="Move ripe batches from the ripening rooms." />
                </div>
            </div>
        </div>

        <!-- Activity + customers -->
        <div class="grid xl:grid-cols-2 gap-4">
            <div class="card card-pad">
                <p class="section-title mb-4">Recent activity</p>
                <ol v-if="d" class="relative space-y-4 before:absolute before:left-[15px] before:top-2 before:bottom-2 before:w-px before:bg-ink-200">
                    <li v-for="a in d.activity" :key="a.id" class="relative flex gap-3">
                        <span class="relative z-10 grid place-items-center size-8 rounded-full bg-white border border-ink-200 text-[10px] font-bold text-ink-600 shrink-0">{{ initials(a.user?.name) }}</span>
                        <div class="min-w-0 pt-0.5">
                            <p class="text-[13px] leading-snug"><b>{{ a.user?.name || 'System' }}</b> <span class="text-ink-600">{{ lower(a.description) }}</span></p>
                            <p class="text-[11px] muted mt-0.5">{{ ago(a.created_at) }}</p>
                        </div>
                    </li>
                </ol>
            </div>
            <div class="card card-pad">
                <p class="section-title">Top customers</p>
                <p class="text-xs muted mb-4">Last 30 days by revenue</p>
                <div v-if="d" class="space-y-3.5">
                    <div v-for="(c, i) in d.top_customers" :key="c.customer_id" class="flex items-center gap-3">
                        <span class="grid place-items-center size-9 rounded-xl text-xs font-bold" :class="i === 0 ? 'bg-brand-400 text-ink-900' : 'bg-ink-100 text-ink-600'">{{ initials(c.customer?.name) }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold truncate">{{ c.customer?.name }}</p>
                                <p class="text-sm font-bold tabular">{{ money(c.revenue, true) }}</p>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full bg-brand-400" :style="{ width: (c.revenue / d.top_customers[0].revenue) * 100 + '%' }" /></div>
                            <p class="text-[11px] muted mt-1 capitalize">{{ c.customer?.type }} · {{ c.orders }} orders · {{ tonnes(c.kg) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from '../components/Icon.vue';
import Kpi from '../components/Kpi.vue';
import Chip from '../components/Chip.vue';
import Tabs from '../components/Tabs.vue';
import Empty from '../components/Empty.vue';
import StageBar from '../components/StageBar.vue';
import AlertItem from '../components/AlertItem.vue';
import AreaChart from '../components/charts/AreaChart.vue';
import { api } from '../lib/api';
import { ago, kg, money, num, pct, tonnes } from '../lib/format';
import { can, onDataChanged, openBatch, store } from '../lib/store';

const d = ref(null);
const metric = ref('revenue');
const updated = ref(null);

async function load() {
    d.value = await api.get('dashboard');
    updated.value = new Date();
}

const h = new Date().getHours();
const greeting = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
const today = new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const revDelta = computed(() => (d.value?.kpis.revenue_yesterday ? ((d.value.kpis.revenue_today - d.value.kpis.revenue_yesterday) / d.value.kpis.revenue_yesterday) * 100 : null));
const sum = computed(() => d.value?.sales.reduce((a, r) => a + r[metric.value], 0) || 0);
const daysCover = computed(() => {
    const avg = d.value ? d.value.sales.reduce((a, r) => a + r.kg, 0) / 14 : 0;
    return avg ? (d.value.kpis.sellable_kg / avg).toFixed(1) : '—';
});

const pipeline = computed(() => {
    const s = d.value?.by_status || {};
    return [
        { key: 'receiving', label: 'Receiving', icon: 'truck', color: '#0ea5e9', to: '/receiving', ...s.receiving },
        { key: 'pre_ripening', label: 'Pre-ripening', icon: 'package', color: '#14b8a6', to: '/inventory', ...s.pre_ripening },
        { key: 'ripening', label: 'Ripening', icon: 'flame', color: '#f97316', to: '/ripening', ...s.ripening },
        { key: 'sales', label: 'Sales stock', icon: 'cart', color: '#eab308', to: '/inventory', ...s.sales },
    ];
});
const maxZone = computed(() => Math.max(1, ...pipeline.value.map((z) => z.kg || 0)));

const glance = computed(() =>
    d.value
        ? [
              { label: 'Received today', value: tonnes(d.value.kpis.received_today_kg), icon: 'truck', bg: 'bg-sky-100 text-sky-700' },
              { label: 'Sold today', value: tonnes(d.value.kpis.kg_sold_today), sub: `${d.value.kpis.orders_today} orders`, icon: 'cart', bg: 'bg-brand-200 text-brand-800' },
              { label: 'Ripe batches awaiting move', value: store.alerts.filter((a) => a.action === 'move_to_sales').length, icon: 'sparkle', bg: 'bg-orange-100 text-orange-700' },
              { label: 'Batches nearing spoilage', value: store.alerts.filter((a) => a.action === 'discount').length, icon: 'alert', bg: 'bg-red-100 text-red-600' },
          ]
        : [],
);

const initials = (n = '') => n.split(' ').map((p) => p[0]).slice(0, 2).join('');
const lower = (s) => s.charAt(0).toLowerCase() + s.slice(1);

let timer, off;
onMounted(() => {
    load();
    timer = setInterval(load, 30000);
    off = onDataChanged(load);
});
onBeforeUnmount(() => (clearInterval(timer), off()));
</script>

<style>
@keyframes wave { 0%, 60%, 100% { transform: rotate(0); } 10%, 30% { transform: rotate(14deg); } 20% { transform: rotate(-8deg); } 40% { transform: rotate(-4deg); } 50% { transform: rotate(10deg); } }
</style>
