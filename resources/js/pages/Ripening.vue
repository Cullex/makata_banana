<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">Ripening rooms</h2>
                <p class="text-sm muted">Monitor and adjust temperature, humidity and ethylene in real time.</p>
            </div>
            <div class="flex items-center gap-2 text-xs muted">
                <span class="relative flex size-2"><span class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-75 animate-ping" /><span class="relative inline-flex rounded-full size-2 bg-emerald-500" /></span>
                Sensors live · refresh every 10s
            </div>
        </div>

        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            <Kpi label="Rooms in use" icon="flame" :value="`${inUse}/${rooms.length}`" :hint="`${gassing} gassing now`" accent />
            <Kpi label="Ripening now" icon="layers" :value="tonnes(ripeningKg)" :hint="`${ripeningBatches.length} batches`" />
            <Kpi label="Ripe, ready to move" icon="sparkle" :value="ripeReady.length" hint="Move to sales storage" />
            <Kpi label="Pre-ripening queue" icon="package" :value="tonnes(queueKg)" :hint="`${queue.length} batches waiting`" />
        </div>

        <!-- Rooms -->
        <div class="grid md:grid-cols-2 2xl:grid-cols-3 gap-4">
            <div v-for="r in rooms" :key="r.id" class="card overflow-hidden flex flex-col animate-fade-up" :class="r.status === 'maintenance' ? 'opacity-75' : ''">
                <div class="flex items-center gap-3 px-5 pt-4 pb-3">
                    <span class="grid place-items-center size-10 rounded-xl font-extrabold text-sm" :class="r.status === 'gassing' ? 'bg-brand-400 text-ink-900 shadow-brand' : 'bg-ink-100 text-ink-600'">{{ r.code.split('-')[1] }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold">{{ r.name }}</p>
                        <p class="text-[11px] muted">{{ tonnes(r.load_kg) }} of {{ tonnes(r.capacity_kg) }} · {{ r.batches.length }} batches</p>
                    </div>
                    <Chip :value="r.status" type="room" />
                </div>

                <div class="grid grid-cols-3 gap-1 px-4 pb-3 justify-items-center">
                    <Gauge :value="r.live.temperature" :min="8" :max="26" :ok="[13, 20]" unit="°C" label="Temperature" :color="tempColor(r.live.temperature)" :size="104" />
                    <Gauge :value="r.live.humidity" :min="60" :max="100" :ok="[90, 95]" unit="%" label="Humidity" color="#0ea5e9" :size="104" />
                    <Gauge :value="r.live.ethylene" :min="0" :max="200" :ok="[100, 150]" unit="ppm" label="Ethylene" color="#84cc16" :size="104" :decimals="0" />
                </div>

                <div class="px-5 pb-3">
                    <div class="flex justify-between text-[11px] text-ink-500 mb-1">
                        <span>Set point {{ r.target_temp }}°C · {{ r.target_humidity }}% · {{ r.target_ethylene }} ppm</span>
                        <span class="font-semibold tabular">{{ Math.round((r.load_kg / r.capacity_kg) * 100) }}% full</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-ink-100 overflow-hidden"><div class="h-full rounded-full bg-brand-400 transition-all" :style="{ width: Math.min(100, (r.load_kg / r.capacity_kg) * 100) + '%' }" /></div>
                </div>

                <div class="flex-1 border-t border-ink-100 bg-ink-50/50 px-3 py-2 space-y-1 min-h-[96px]">
                    <button v-for="b in r.batches" :key="b.id" class="w-full flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-white transition text-left" @click="openBatch(b.id)">
                        <span class="size-2.5 rounded-full shrink-0" :style="{ background: STAGES[b.stage].hex }" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-bold font-mono">{{ b.code }}</p>
                                <p class="text-[11px] muted tabular">{{ tonnes(b.remaining_kg) }}</p>
                            </div>
                            <div class="mt-1 h-1 rounded-full bg-ink-200 overflow-hidden"><div class="h-full rounded-full" :style="{ width: b.ripening_progress + '%', background: STAGES[b.stage].hex }" /></div>
                        </div>
                        <span v-if="b.stage === 'ripe'" class="chip bg-brand-400 text-ink-900">Ready</span>
                        <span v-else class="text-[10px] muted w-16 text-right">{{ ago(b.expected_ripe_at) }}</span>
                    </button>
                    <p v-if="!r.batches.length" class="text-xs muted text-center py-6">{{ r.status === 'maintenance' ? 'Room offline for maintenance' : 'Empty. Ready for loading.' }}</p>
                </div>

                <div class="flex gap-2 px-4 py-3 border-t border-ink-100">
                    <button class="btn-dark btn-sm flex-1" @click="openControl(r)"><Icon name="gauge" :size="14" /> Controls & history</button>
                    <button v-if="r.batches.some((b) => b.stage === 'ripe')" class="btn-primary btn-sm" @click="moveRipe(r)"><Icon name="arrowRight" :size="14" /> Move ripe to sales</button>
                </div>
            </div>
        </div>

        <!-- Schedule -->
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-5 pb-3">
                <div>
                    <p class="section-title">Ripening schedule</p>
                    <p class="text-xs muted">Planned cycles, from room entry to expected ripeness</p>
                </div>
                <div class="flex items-center gap-4 text-[11px]">
                    <span class="flex items-center gap-1.5"><span class="w-5 h-2 rounded-full" style="background: linear-gradient(90deg, #65a30d, #f5c400)" />Ripening cycle</span>
                    <span class="flex items-center gap-1.5"><span class="w-5 h-2 rounded-full bg-ink-200 border border-dashed border-ink-400" />Planned / queued</span>
                    <span class="flex items-center gap-1.5"><span class="w-0.5 h-3 bg-red-500" />Now</span>
                </div>
            </div>
            <div class="overflow-x-auto scrollbar-thin">
                <div class="min-w-[860px] px-5 pb-5">
                    <div class="grid grid-cols-[180px_1fr] text-[10px] muted font-semibold">
                        <div />
                        <div class="relative h-6 border-b border-ink-200">
                            <span v-for="d in scheduleDays" :key="d.ts" class="absolute -translate-x-1/2 top-0" :class="d.today ? 'text-red-600 font-bold' : ''" :style="{ left: pos(d.ts) + '%' }">{{ d.label }}</span>
                        </div>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-[180px] right-0 pointer-events-none">
                            <div v-for="d in scheduleDays" :key="d.ts" class="absolute inset-y-0 w-px bg-ink-100" :style="{ left: pos(d.ts) + '%' }" />
                            <div class="absolute inset-y-0 w-0.5 bg-red-500 z-10" :style="{ left: pos(Date.now()) + '%' }"><span class="absolute -top-1 -left-[3px] size-2 rounded-full bg-red-500" /></div>
                        </div>
                        <div v-for="row in schedule" :key="row.id" class="grid grid-cols-[180px_1fr] items-center h-11 border-b border-ink-100 last:border-0 group">
                            <button class="flex items-center gap-2 text-left min-w-0 pr-3" @click="openBatch(row.id)">
                                <span class="size-2 rounded-full shrink-0" :style="{ background: STAGES[row.stage].hex }" />
                                <span class="min-w-0">
                                    <span class="block text-xs font-bold font-mono truncate group-hover:text-brand-700">{{ row.code }}</span>
                                    <span class="block text-[10px] muted truncate">{{ row.room }} · {{ tonnes(row.kg) }}</span>
                                </span>
                            </button>
                            <div class="relative h-full">
                                <div
                                    class="absolute top-1/2 -translate-y-1/2 h-6 rounded-lg flex items-center px-2 text-[10px] font-bold cursor-pointer hover:ring-2 hover:ring-ink-900/20 transition overflow-hidden"
                                    :class="row.planned ? 'bg-ink-100 border border-dashed border-ink-400 text-ink-600' : 'text-ink-900'"
                                    :style="{ left: pos(row.start) + '%', width: Math.max(2, pos(row.end) - pos(row.start)) + '%', background: row.planned ? undefined : `linear-gradient(90deg, #a3d65c, ${row.stage === 'ripe' ? '#facc15' : '#e7e98a'})` }"
                                    @click="openBatch(row.id)"
                                >
                                    <span class="truncate">{{ row.planned ? 'Queued' : row.stage === 'ripe' ? 'Ripe ✓' : `${row.progress}%` }}</span>
                                </div>
                            </div>
                        </div>
                        <Empty v-if="!schedule.length" icon="calendar" title="Nothing scheduled" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Pre-ripening queue -->
        <div class="card overflow-hidden">
            <div class="p-5 pb-3">
                <p class="section-title">Pre-ripening queue</p>
                <p class="text-xs muted">Green fruit in cool storage, oldest first. Load into a ripening room when space allows.</p>
            </div>
            <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-3 px-5 pb-5">
                <div v-for="b in queue" :key="b.id" class="rounded-2xl border border-ink-200/70 p-4 flex items-center gap-3">
                    <span class="grid place-items-center size-10 rounded-xl bg-teal-50 text-teal-700"><Icon name="package" :size="18" /></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold font-mono">{{ b.code }}</p>
                        <p class="text-[11px] muted truncate">{{ b.supplier?.name }} · {{ tonnes(b.remaining_kg) }} · {{ b.location?.code }}</p>
                        <p class="text-[11px] muted">In store {{ ago(b.arrived_at) }}</p>
                    </div>
                    <button class="btn-primary btn-sm" @click="openBatch(b.id)">Load room</button>
                </div>
                <Empty v-if="!queue.length" class="md:col-span-3" icon="check" title="Queue is empty" />
            </div>
        </div>

        <!-- Room control modal -->
        <Modal :open="!!ctrl" :title="ctrl?.name" :subtitle="`${ctrl?.code} · Room control panel`" icon="gauge" width="sm:max-w-3xl" @close="ctrl = null">
            <div v-if="ctrl" class="space-y-6">
                <div>
                    <label class="label">Room mode</label>
                    <div class="grid grid-cols-5 gap-2">
                        <button v-for="(s, key) in ROOM_STATUSES" :key="key" class="rounded-xl border p-2.5 text-center transition" :class="ctrlForm.status === key ? 'border-ink-900 bg-ink-900 text-white' : 'border-ink-200 hover:border-ink-300'" @click="ctrlForm.status = key">
                            <span class="block size-2 rounded-full mx-auto mb-1.5" :class="s.dot" />
                            <span class="text-[11px] font-bold">{{ s.label }}</span>
                        </button>
                    </div>
                </div>
                <div class="grid sm:grid-cols-3 gap-3">
                    <Stepper v-model="ctrlForm.target_temp" label="Temperature" unit="°C" :step="0.5" :min="10" :max="25" icon="thermometer" hint="Recommended 13 to 20 °C" />
                    <Stepper v-model="ctrlForm.target_humidity" label="Humidity" unit="%" :step="1" :min="70" :max="99" icon="droplet" hint="Recommended 90 to 95%" />
                    <Stepper v-model="ctrlForm.target_ethylene" label="Ethylene" unit="ppm" :step="10" :min="0" :max="300" icon="wind" hint="100 to 150 ppm while gassing" />
                </div>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-bold">Sensor history · 48h</p>
                        <Tabs v-model="histKey" :tabs="[{ label: 'Temp', value: 'temperature' }, { label: 'Humidity', value: 'humidity' }, { label: 'Ethylene', value: 'ethylene' }]" />
                    </div>
                    <AreaChart
                        v-if="history.length" :data="history" x-key="recorded_at" :y-key="histKey" :height="200" :min="histKey === 'ethylene' ? undefined : Math.floor(Math.min(...history.map((h) => h[histKey])) - 1)"
                        :color="{ temperature: '#f97316', humidity: '#0ea5e9', ethylene: '#84cc16' }[histKey]"
                        :fmt-x="(v) => new Date(v).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })"
                        :fmt-tip="(v) => dateTime(v)" :fmt-val="(v) => v + { temperature: ' °C', humidity: ' %', ethylene: ' ppm' }[histKey]" :fmt-y="(v) => Math.round(v)"
                    />
                    <div v-else class="skeleton h-[200px]" />
                </div>
            </div>
            <template #footer>
                <button class="btn-ghost" @click="ctrl = null">Close</button>
                <button class="btn-primary" :disabled="saving" @click="saveControl">Apply settings</button>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import Icon from '../components/Icon.vue';
import Kpi from '../components/Kpi.vue';
import Chip from '../components/Chip.vue';
import Tabs from '../components/Tabs.vue';
import Modal from '../components/Modal.vue';
import Empty from '../components/Empty.vue';
import Stepper from '../components/Stepper.vue';
import Gauge from '../components/charts/Gauge.vue';
import AreaChart from '../components/charts/AreaChart.vue';
import { api } from '../lib/api';
import { ago, dateTime, ROOM_STATUSES, STAGES, tonnes } from '../lib/format';
import { dataChanged, loadMeta, onDataChanged, openBatch, toast } from '../lib/store';

const rooms = ref([]);
const queue = ref([]);
const ctrl = ref(null);
const ctrlForm = reactive({});
const history = ref([]);
const histKey = ref('temperature');
const saving = ref(false);

const ripeningBatches = computed(() => rooms.value.flatMap((r) => r.batches.map((b) => ({ ...b, room: r.code }))));
const ripeningKg = computed(() => ripeningBatches.value.reduce((a, b) => a + b.remaining_kg, 0));
const ripeReady = computed(() => ripeningBatches.value.filter((b) => b.stage === 'ripe'));
const queueKg = computed(() => queue.value.reduce((a, b) => a + b.remaining_kg, 0));
const inUse = computed(() => rooms.value.filter((r) => r.batches.length).length);
const gassing = computed(() => rooms.value.filter((r) => r.status === 'gassing').length);
const tempColor = (t) => (t < 13 || t > 20 ? '#ef4444' : '#f97316');

// Schedule window: 6 days back → 5 days ahead
const DAY = 864e5;
const startOfToday = new Date().setHours(0, 0, 0, 0);
const win = [startOfToday - 6 * DAY, startOfToday + 6 * DAY];
const pos = (ts) => Math.max(0, Math.min(100, ((ts - win[0]) / (win[1] - win[0])) * 100));
const scheduleDays = Array.from({ length: 13 }, (_, i) => {
    const ts = win[0] + i * DAY;
    return { ts, today: ts === startOfToday, label: ts === startOfToday ? 'Today' : new Date(ts).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric' }) };
});
const schedule = computed(() => {
    const active = ripeningBatches.value.map((b) => ({
        id: b.id, code: b.code, stage: b.stage, kg: b.remaining_kg, room: b.room, progress: b.ripening_progress,
        start: new Date(b.ripening_started_at).getTime(), end: new Date(b.ripe_at || b.expected_ripe_at).getTime(),
    }));
    let cursor = Date.now() + 6 * 3600e3;
    const planned = queue.value.map((b) => {
        const row = { id: b.id, code: b.code, stage: b.stage, kg: b.remaining_kg, room: 'Queue', planned: true, start: cursor, end: cursor + 5 * DAY };
        cursor += 14 * 3600e3;
        return row;
    });
    return [...active.sort((a, b) => a.start - b.start), ...planned];
});

async function load() {
    const [r, q] = await Promise.all([api.get('locations', { zone: 'ripening' }), api.get('batches', { status: 'pre_ripening', sort: 'oldest', per_page: 30 })]);
    rooms.value = r;
    queue.value = q.data;
}

async function openControl(r) {
    ctrl.value = r;
    Object.assign(ctrlForm, { status: r.status, target_temp: r.target_temp, target_humidity: r.target_humidity, target_ethylene: r.target_ethylene });
    history.value = [];
    const d = await api.get(`locations/${r.id}`);
    history.value = [...d.history, d.live];
}

async function saveControl() {
    saving.value = true;
    try {
        await api.patch(`locations/${ctrl.value.id}`, ctrlForm);
        toast(`${ctrl.value.name} updated`);
        ctrl.value = null;
        await load();
        dataChanged();
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}

async function moveRipe(r) {
    const meta = await loadMeta();
    const target = meta.locations.find((l) => l.zone === 'sales');
    const ripe = r.batches.filter((b) => b.stage === 'ripe');
    try {
        for (const b of ripe) await api.post(`batches/${b.id}/move`, { storage_location_id: target.id, note: 'Ripe, moved to sales inventory' });
        toast(`${ripe.length} batch${ripe.length > 1 ? 'es' : ''} moved to ${target.name}`);
        await load();
        dataChanged();
    } catch (e) {
        toast(e.message, 'error');
    }
}

let timer, off;
onMounted(() => {
    load();
    timer = setInterval(load, 10000);
    off = onDataChanged(load);
});
onBeforeUnmount(() => (clearInterval(timer), off()));
</script>
