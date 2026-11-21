<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">Receiving area</h2>
                <p class="text-sm muted">Log arrivals, inspect quality and assign pre-ripening storage.</p>
            </div>
            <div class="flex gap-2">
                <button class="btn-outline" @click="scanOpen = true"><Icon name="scan" :size="16" /> Scan code</button>
                <button class="btn-primary" @click="openNew"><Icon name="plus" :size="16" :stroke="2.4" /> Receive batch</button>
            </div>
        </div>

        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            <Kpi label="Received today" icon="truck" :value="tonnes(stats.today)" :hint="`${stats.todayCount} trucks`" accent />
            <Kpi label="Awaiting inspection" icon="clipboard" :value="waiting.length" hint="At receiving bay" />
            <Kpi label="Received (7 days)" icon="calendar" :value="tonnes(stats.week)" :hint="`${stats.weekCount} batches`" />
            <Kpi label="Grade A share (7 days)" icon="star" :value="pct(stats.gradeA, 0)" hint="Of received weight" />
        </div>

        <!-- Awaiting inspection -->
        <div>
            <div class="flex items-center gap-2 mb-3">
                <p class="section-title">Awaiting inspection</p>
                <span class="chip bg-sky-100 text-sky-800">{{ waiting.length }}</span>
            </div>
            <div v-if="waiting.length" class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
                <div v-for="b in waiting" :key="b.id" class="card p-5 relative overflow-hidden animate-fade-up">
                    <div class="absolute inset-y-0 left-0 w-1 bg-sky-400" />
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="font-mono font-extrabold">{{ b.code }}</p>
                            <p class="text-xs muted mt-0.5">{{ b.supplier?.name }}</p>
                        </div>
                        <span class="chip bg-sky-100 text-sky-800"><span class="size-1.5 rounded-full bg-sky-500 animate-pulse" />Arrived {{ ago(b.arrived_at) }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                        <div class="rounded-xl bg-ink-50 py-2"><p class="text-[10px] muted">Weight</p><p class="font-bold text-sm tabular">{{ tonnes(b.initial_kg) }}</p></div>
                        <div class="rounded-xl bg-ink-50 py-2"><p class="text-[10px] muted">Cartons</p><p class="font-bold text-sm tabular">{{ b.cartons }}</p></div>
                        <div class="rounded-xl bg-ink-50 py-2"><p class="text-[10px] muted">Variety</p><p class="font-bold text-sm">{{ b.variety }}</p></div>
                    </div>
                    <button class="btn-dark w-full mt-4" @click="openBatch(b.id)"><Icon name="clipboard" :size="16" /> Inspect & store</button>
                </div>
            </div>
            <div v-else class="card"><Empty icon="check" title="Receiving bay is clear" text="All arrivals have been inspected and moved to pre-ripening." /></div>
        </div>

        <!-- Recent receipts -->
        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-5 pb-4">
                <p class="section-title">Recent receipts</p>
                <div class="relative">
                    <Icon name="search" :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
                    <input v-model="search" class="input h-9 w-56 pl-9" placeholder="Batch or supplier…" @input="debounced" />
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px]">
                    <thead>
                        <tr><th class="table-head">Batch</th><th class="table-head">Supplier</th><th class="table-head">Arrived</th><th class="table-head text-right">Weight</th><th class="table-head">Grade</th><th class="table-head">Stage</th><th class="table-head">Status</th><th class="table-head">Location</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in list.data" :key="b.id" class="hover:bg-brand-50/50 cursor-pointer transition" @click="openBatch(b.id)">
                            <td class="table-cell font-mono font-bold">{{ b.code }}</td>
                            <td class="table-cell">{{ b.supplier?.name }}</td>
                            <td class="table-cell text-ink-600">{{ dateTime(b.arrived_at) }}</td>
                            <td class="table-cell text-right font-semibold tabular">{{ kg(b.initial_kg) }}</td>
                            <td class="table-cell"><span class="chip bg-ink-100 text-ink-700">{{ b.grade }}</span></td>
                            <td class="table-cell"><Chip :value="b.stage" /></td>
                            <td class="table-cell"><Chip :value="b.status" type="status" :dot="false" /></td>
                            <td class="table-cell text-ink-600">{{ b.location?.code || '—' }}</td>
                        </tr>
                        <tr v-if="loading && !list.data?.length"><td colspan="8" class="p-4"><div v-for="i in 5" :key="i" class="skeleton h-10 mb-2" /></td></tr>
                    </tbody>
                </table>
            </div>
            <Pager :meta="list" @page="(p) => fetchList(p)" />
        </div>

        <!-- Receive modal -->
        <Modal :open="newOpen" :title="created ? 'Batch received' : 'Receive new batch'" :subtitle="created ? created.code : 'Record arrival details from the delivery note'" icon="truck" width="sm:max-w-2xl" @close="newOpen = false">
            <div v-if="created" class="text-center py-4">
                <div class="inline-block rounded-2xl border-2 border-ink-900 p-4">
                    <canvas ref="qrEl" class="size-40" />
                    <p class="font-mono font-extrabold text-lg mt-2">{{ created.code }}</p>
                    <p class="text-xs muted">{{ created.supplier?.name }} · {{ kg(created.initial_kg) }}</p>
                </div>
                <p class="text-sm muted mt-4">Print this label and attach it to the pallet. Scan it anywhere to open the batch.</p>
            </div>
            <form v-else id="recv" class="grid sm:grid-cols-2 gap-4" @submit.prevent="save">
                <div class="sm:col-span-2">
                    <label class="label">Supplier</label>
                    <select v-model="form.supplier_id" class="input" required>
                        <option :value="null" disabled>Select supplier…</option>
                        <option v-for="s in store.meta?.suppliers" :key="s.id" :value="s.id">{{ s.name }} · {{ s.region }}</option>
                    </select>
                    <FieldError :msg="errors.supplier_id" />
                </div>
                <div>
                    <label class="label">Net weight (kg)</label>
                    <input v-model.number="form.initial_kg" type="number" min="50" class="input" placeholder="e.g. 4500" required @input="form.cartons = Math.round((form.initial_kg || 0) / 18)" />
                    <FieldError :msg="errors.initial_kg" />
                </div>
                <div>
                    <label class="label">Cartons <span class="font-normal muted">(18 kg each)</span></label>
                    <input v-model.number="form.cartons" type="number" min="0" class="input" />
                </div>
                <div>
                    <label class="label">Variety</label>
                    <select v-model="form.variety" class="input"><option v-for="v in store.meta?.varieties" :key="v">{{ v }}</option></select>
                </div>
                <div>
                    <label class="label">Arrival time</label>
                    <input v-model="form.arrived_at" type="datetime-local" class="input" />
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Ripeness at arrival</label>
                    <StagePicker v-model="form.stage" />
                </div>
                <div>
                    <label class="label">Quality grade</label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button v-for="g in ['A', 'B', 'C']" :key="g" type="button" class="h-10 rounded-xl border text-sm font-bold transition" :class="form.grade === g ? 'border-brand-500 bg-brand-100' : 'border-ink-200 hover:border-ink-300'" @click="form.grade = g">{{ g }}</button>
                    </div>
                </div>
                <div>
                    <label class="label">Cost per kg ({{ store.currency }})</label>
                    <input v-model.number="form.cost_per_kg" type="number" step="0.01" min="0" class="input" />
                </div>
                <div>
                    <label class="label">Visible damage %</label>
                    <input v-model.number="form.damage_pct" type="number" step="0.1" min="0" max="100" class="input" />
                </div>
                <div>
                    <label class="label">Pulp temperature °C</label>
                    <input v-model.number="form.pulp_temp" type="number" step="0.1" class="input" placeholder="13.5" />
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Storage assignment</label>
                    <div class="grid sm:grid-cols-3 gap-2">
                        <label v-for="l in storeTargets" :key="l.id" class="flex items-center gap-2 rounded-xl border p-3 cursor-pointer transition" :class="form.storage_location_id === l.id ? 'border-brand-500 bg-brand-50' : 'border-ink-200'">
                            <input v-model="form.storage_location_id" type="radio" :value="l.id" class="accent-brand-500" />
                            <span><span class="block text-sm font-semibold">{{ l.name }}</span><span class="block text-[11px] muted">{{ l.code }}</span></span>
                        </label>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Notes</label>
                    <textarea v-model="form.notes" rows="2" class="input" placeholder="Truck condition, delivery note number…" />
                </div>
            </form>
            <template #footer>
                <template v-if="created">
                    <button class="btn-ghost" @click="openBatch(created.id), (newOpen = false)">Open batch</button>
                    <button class="btn-outline" @click="openNew">Receive another</button>
                    <button class="btn-primary" @click="print"><Icon name="printer" :size="16" /> Print label</button>
                </template>
                <template v-else>
                    <button class="btn-ghost" @click="newOpen = false">Cancel</button>
                    <button form="recv" class="btn-primary" :disabled="saving">Log batch</button>
                </template>
            </template>
        </Modal>

        <!-- Scan modal -->
        <Modal :open="scanOpen" title="Scan batch code" subtitle="Use a handheld scanner or type the code" icon="scan" @close="scanOpen = false">
            <form @submit.prevent="scan">
                <div class="relative rounded-2xl bg-ink-950 h-40 grid place-items-center overflow-hidden mb-4">
                    <div class="absolute inset-x-8 h-0.5 bg-brand-400 shadow-[0_0_20px_4px_rgba(250,204,21,.6)] animate-[scan_2s_ease-in-out_infinite]" />
                    <Icon name="qr" :size="64" class="text-ink-700" />
                </div>
                <input ref="scanInput" v-model="scanCode" class="input h-12 text-center font-mono text-lg tracking-wider" placeholder="MB-XXXXXX-XXX" autofocus />
            </form>
            <template #footer>
                <button class="btn-primary" @click="scan">Find batch</button>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import Kpi from '../components/Kpi.vue';
import Chip from '../components/Chip.vue';
import Modal from '../components/Modal.vue';
import Empty from '../components/Empty.vue';
import Pager from '../components/Pager.vue';
import FieldError from '../components/FieldError.vue';
import StagePicker from '../components/StagePicker.vue';
import { api } from '../lib/api';
import { ago, dateTime, kg, pct, tonnes } from '../lib/format';
import { dataChanged, loadMeta, onDataChanged, openBatch, store, toast } from '../lib/store';

const route = useRoute();
const router = useRouter();
const list = ref({});
const recent = ref([]);
const waiting = ref([]);
const loading = ref(true);
const search = ref('');
const newOpen = ref(false);
const scanOpen = ref(false);
const scanCode = ref('');
const saving = ref(false);
const errors = ref({});
const created = ref(null);
const qrEl = ref();
const form = reactive({});

const storeTargets = computed(() => (store.meta?.locations || []).filter((l) => ['receiving', 'pre_ripening'].includes(l.zone)));

const stats = computed(() => {
    const today = new Date().toDateString();
    const weekAgo = Date.now() - 7 * 864e5;
    const t = recent.value.filter((b) => new Date(b.arrived_at).toDateString() === today);
    const w = recent.value.filter((b) => new Date(b.arrived_at).getTime() >= weekAgo);
    return {
        today: t.reduce((a, b) => a + b.initial_kg, 0), todayCount: t.length,
        week: w.reduce((a, b) => a + b.initial_kg, 0), weekCount: w.length,
        gradeA: w.length ? (w.filter((b) => b.grade === "A").reduce((a, b) => a + b.initial_kg, 0) / (w.reduce((a, b) => a + b.initial_kg, 0) || 1)) * 100 : 0,
    };
});

async function fetchList(page = 1) {
    loading.value = true;
    list.value = await api.get('batches', { page, search: search.value, per_page: 10 });
    loading.value = false;
}
async function load() {
    const [w, r] = await Promise.all([api.get('batches', { status: 'receiving', per_page: 50 }), api.get('batches', { per_page: 40 })]);
    waiting.value = w.data;
    recent.value = r.data;
    fetchList(list.value.current_page || 1);
}
let t;
const debounced = () => (clearTimeout(t), (t = setTimeout(() => fetchList(1), 300)));

function localNow() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
}

async function openNew() {
    await loadMeta();
    created.value = null;
    errors.value = {};
    Object.assign(form, {
        supplier_id: null, initial_kg: null, cartons: null, variety: 'Cavendish', stage: 'green', grade: 'A',
        cost_per_kg: 0.48, damage_pct: 0, pulp_temp: 13.5, arrived_at: localNow(),
        storage_location_id: storeTargets.value.find((l) => l.zone === 'pre_ripening')?.id, notes: '',
    });
    newOpen.value = true;
}

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        created.value = await api.post('batches', form);
        toast(`Batch ${created.value.code} logged`);
        dataChanged();
        await nextTick();
        const QR = (await import('qrcode')).default;
        QR.toCanvas(qrEl.value, created.value.code, { width: 160, margin: 0 });
    } catch (e) {
        errors.value = e.errors;
        toast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}

function print() {
    openBatch(created.value.id);
    newOpen.value = false;
}

async function scan() {
    const code = scanCode.value.trim();
    if (!code) return;
    const r = await api.get('batches', { search: code, per_page: 1 });
    if (r.data[0]) {
        scanOpen.value = false;
        scanCode.value = '';
        openBatch(r.data[0].id);
    } else toast('No batch found for that code', 'error');
}

let off;
onMounted(() => {
    load();
    off = onDataChanged(load);
    if (route.query.new) {
        openNew();
        router.replace({ query: {} });
    }
});
watch(() => route.query.new, (v) => v && (openNew(), router.replace({ query: {} })));
onBeforeUnmount(() => off());
</script>

<style>
@keyframes scan { 0%, 100% { top: 20%; } 50% { top: 80%; } }
</style>
