<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-200" leave-active-class="transition duration-200">
            <div v-if="store.batchId" class="fixed inset-0 z-50 bg-ink-950/40 backdrop-blur-[2px]" @click="close" />
        </Transition>
        <Transition enter-from-class="translate-x-full" leave-to-class="translate-x-full" enter-active-class="transition duration-300 ease-out" leave-active-class="transition duration-200 ease-in">
            <aside v-if="store.batchId" class="fixed inset-y-0 right-0 z-50 w-full max-w-[560px] bg-ink-50 shadow-pop flex flex-col">
                <!-- Header -->
                <div class="bg-white border-b border-ink-200/70 px-6 pt-5 pb-4">
                    <div class="flex items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wider muted">Batch</p>
                            <h2 class="text-xl font-extrabold font-mono tracking-tight mt-0.5">{{ b?.code || '…' }}</h2>
                            <div v-if="b" class="flex flex-wrap gap-1.5 mt-2">
                                <Chip :value="b.status" type="status" />
                                <Chip :value="b.stage" />
                                <span class="chip bg-ink-100 text-ink-700">Grade {{ b.grade }}</span>
                            </div>
                        </div>
                        <button class="shrink-0 rounded-xl border border-ink-200 p-1.5 bg-white hover:border-brand-400 transition" title="Print label" @click="printLabel">
                            <canvas ref="qrEl" class="block size-[68px]" />
                        </button>
                        <button class="btn-icon btn-ghost -mr-2 -mt-1" @click="close"><Icon name="x" /></button>
                    </div>

                    <!-- Actions -->
                    <div v-if="b && canAct" class="flex flex-wrap gap-2 mt-4">
                        <button v-if="b.status === 'receiving' || can.warehouse()" class="btn-primary btn-sm" @click="openAction('inspect')"><Icon name="clipboard" :size="14" /> Inspect / stage</button>
                        <button v-if="movable" class="btn-outline btn-sm" @click="openAction('move')"><Icon name="move" :size="14" /> Move</button>
                        <button v-if="b.remaining_kg > 0" class="btn-outline btn-sm" @click="openAction('loss')"><Icon name="trash" :size="14" /> Log loss</button>
                        <button class="btn-ghost btn-sm" @click="printLabel"><Icon name="printer" :size="14" /> Label</button>
                    </div>
                </div>

                <div v-if="!b" class="p-6 space-y-3"><div v-for="i in 5" :key="i" class="skeleton h-16" /></div>

                <div v-else class="flex-1 overflow-y-auto scrollbar-thin p-6 space-y-5">
                    <!-- Journey -->
                    <div class="card p-4">
                        <div class="flex items-start">
                            <template v-for="(s, i) in journey" :key="s.key">
                                <div class="flex flex-col items-center text-center w-16 shrink-0">
                                    <span class="grid place-items-center size-8 rounded-full border-2 transition" :class="s.done ? 'bg-brand-400 border-brand-400 text-ink-900' : s.current ? 'border-brand-400 text-brand-600 bg-brand-50' : 'border-ink-200 text-ink-300 bg-white'">
                                        <Icon :name="s.done ? 'check' : s.icon" :size="14" :stroke="2.4" />
                                    </span>
                                    <p class="text-[11px] font-bold mt-1.5" :class="s.done || s.current ? 'text-ink-800' : 'text-ink-400'">{{ s.label }}</p>
                                    <p class="text-[10px] muted leading-tight">{{ s.at ? date(s.at) : '' }}</p>
                                </div>
                                <div v-if="i < journey.length - 1" class="flex-1 h-0.5 mt-4 rounded" :class="journey[i + 1].done || journey[i + 1].current ? 'bg-brand-400' : 'bg-ink-200'" />
                            </template>
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div class="card p-4">
                        <div class="flex items-end justify-between">
                            <div>
                                <p class="text-xs muted">Remaining</p>
                                <p class="text-3xl font-extrabold tabular tracking-tight">{{ kg(b.remaining_kg) }}</p>
                            </div>
                            <p class="text-xs muted text-right">of {{ kg(b.initial_kg) }} received<br />{{ num(b.cartons) }} cartons</p>
                        </div>
                        <div class="mt-3 h-2.5 rounded-full bg-ink-100 overflow-hidden flex">
                            <div class="bg-brand-400 h-full" :style="{ width: (b.remaining_kg / b.initial_kg) * 100 + '%' }" />
                            <div class="bg-ink-300 h-full" :style="{ width: (sold / b.initial_kg) * 100 + '%' }" />
                            <div class="bg-red-400 h-full" :style="{ width: (lost / b.initial_kg) * 100 + '%' }" />
                        </div>
                        <div class="flex gap-4 mt-2 text-[11px]">
                            <span class="flex items-center gap-1.5"><span class="size-2 rounded-sm bg-brand-400" />In stock</span>
                            <span class="flex items-center gap-1.5"><span class="size-2 rounded-sm bg-ink-300" />Sold {{ kg(sold) }}</span>
                            <span class="flex items-center gap-1.5"><span class="size-2 rounded-sm bg-red-400" />Lost {{ kg(lost) }}</span>
                        </div>
                        <div v-if="['ripening', 'pre_ripening'].includes(b.status)" class="mt-4 pt-4 border-t border-ink-100">
                            <div class="flex justify-between text-xs mb-1.5"><span class="font-semibold">Ripening progress</span><span class="muted">{{ b.expected_ripe_at ? 'expected ' + ago(b.expected_ripe_at) : 'not started' }}</span></div>
                            <div class="h-2 rounded-full overflow-hidden" style="background: linear-gradient(90deg, #65a30d, #a3b818, #f5c400)">
                                <div class="h-full bg-ink-100 ml-auto transition-all" :style="{ width: 100 - b.ripening_progress + '%' }" />
                            </div>
                        </div>
                        <div v-if="b.shelf_days_left != null" class="mt-4 flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold" :class="b.shelf_days_left <= 1 ? 'bg-red-50 text-red-700' : b.shelf_days_left <= 2.5 ? 'bg-amber-50 text-amber-800' : 'bg-emerald-50 text-emerald-700'">
                            <Icon name="clock" :size="14" /> {{ b.shelf_days_left > 0 ? `${b.shelf_days_left} days of shelf life left` : 'Past shelf life' }}
                        </div>
                    </div>

                    <!-- Facts -->
                    <div class="card divide-y divide-ink-100">
                        <div v-for="f in facts" :key="f[0]" class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
                            <span class="muted">{{ f[0] }}</span>
                            <span class="font-semibold text-right">{{ f[1] }}</span>
                        </div>
                    </div>

                    <!-- History -->
                    <div>
                        <Tabs v-model="tab" :tabs="tabs" />
                        <div class="card mt-3 p-4">
                            <ol v-if="tab === 'inspections'" class="space-y-4">
                                <li v-for="i in b.inspections" :key="i.id" class="flex gap-3">
                                    <span class="grid place-items-center size-8 rounded-lg bg-brand-100 text-brand-700 shrink-0"><Icon name="clipboard" :size="15" /></span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <p class="text-sm font-bold capitalize">{{ i.type.replace('_', '-') }} check</p>
                                            <Chip :value="i.stage" />
                                            <span class="chip bg-ink-100 text-ink-700">Grade {{ i.grade }}</span>
                                        </div>
                                        <p class="text-xs text-ink-600 mt-1">{{ i.notes }}</p>
                                        <p class="text-[11px] muted mt-1">
                                            {{ i.user?.name }} · {{ dateTime(i.created_at) }}
                                            <template v-if="i.damage_pct"> · {{ i.damage_pct }}% damage</template>
                                            <template v-if="i.pulp_temp"> · pulp {{ i.pulp_temp }}°C</template>
                                        </p>
                                    </div>
                                </li>
                            </ol>
                            <ol v-if="tab === 'movements'" class="space-y-3">
                                <li v-for="m in b.movements" :key="m.id" class="flex items-center gap-3 text-sm">
                                    <span class="grid place-items-center size-8 rounded-lg bg-ink-100 text-ink-600 shrink-0"><Icon name="move" :size="15" /></span>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold">{{ m.from?.name || 'Supplier truck' }} <Icon name="arrowRight" :size="12" class="inline text-ink-400" /> {{ m.to?.name || '—' }}</p>
                                        <p class="text-[11px] muted">{{ m.note }} · {{ m.user?.name }} · {{ dateTime(m.created_at) }}</p>
                                    </div>
                                </li>
                            </ol>
                            <div v-if="tab === 'sales'">
                                <div v-for="it in b.order_items" :key="it.id" class="flex items-center justify-between py-2 border-b border-ink-100 last:border-0 text-sm">
                                    <div>
                                        <p class="font-semibold">{{ it.order?.customer?.name }}</p>
                                        <p class="text-[11px] muted">{{ it.order?.number }} · {{ dateTime(it.order?.ordered_at) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold tabular">{{ kg(it.quantity_kg) }}</p>
                                        <p class="text-[11px] muted">{{ money(it.line_total) }}<span v-if="it.discount_pct"> · −{{ it.discount_pct }}%</span></p>
                                    </div>
                                </div>
                                <Empty v-if="!b.order_items.length" icon="cart" title="No sales yet" />
                            </div>
                            <div v-if="tab === 'losses'">
                                <div v-for="l in b.losses" :key="l.id" class="flex items-center justify-between py-2 border-b border-ink-100 last:border-0 text-sm">
                                    <div>
                                        <p class="font-semibold capitalize">{{ l.reason }}</p>
                                        <p class="text-[11px] muted">{{ l.notes }} · {{ l.user?.name }} · {{ dateTime(l.created_at) }}</p>
                                    </div>
                                    <p class="font-bold text-red-600 tabular">−{{ kg(l.quantity_kg) }}</p>
                                </div>
                                <Empty v-if="!b.losses.length" icon="check" title="No losses recorded" />
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </Transition>

        <!-- Action modals -->
        <Modal :open="action === 'inspect'" title="Inspection & ripening stage" :subtitle="b?.code" icon="clipboard" @close="action = null">
            <div class="space-y-4">
                <div>
                    <label class="label">Ripeness stage</label>
                    <StagePicker v-model="form.stage" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Check type</label>
                        <select v-model="form.type" class="input">
                            <option value="arrival">Arrival</option>
                            <option value="ripening">Ripening progress</option>
                            <option value="pre_sale">Pre-sale</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Quality grade</label>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button v-for="g in ['A', 'B', 'C']" :key="g" type="button" class="h-10 rounded-xl border text-sm font-bold transition" :class="form.grade === g ? 'border-brand-500 bg-brand-100' : 'border-ink-200 hover:border-ink-300'" @click="form.grade = g">{{ g }}</button>
                        </div>
                    </div>
                    <div>
                        <label class="label">Damage %</label>
                        <input v-model.number="form.damage_pct" type="number" step="0.1" min="0" class="input" />
                    </div>
                    <div>
                        <label class="label">Pulp temp °C</label>
                        <input v-model.number="form.pulp_temp" type="number" step="0.1" class="input" />
                    </div>
                </div>
                <div>
                    <label class="label">Visual checks</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label v-for="c in checks" :key="c.key" class="flex items-center gap-2 rounded-xl border border-ink-200 px-3 h-10 text-sm cursor-pointer" :class="form.checks[c.key] ? 'bg-emerald-50 border-emerald-200' : ''">
                            <input v-model="form.checks[c.key]" type="checkbox" class="accent-emerald-600" /> {{ c.label }}
                        </label>
                    </div>
                </div>
                <div v-if="b?.status === 'receiving'">
                    <label class="label">Send to pre-ripening store</label>
                    <select v-model="form.storage_location_id" class="input">
                        <option :value="null">Keep at receiving</option>
                        <option v-for="l in locs('pre_ripening')" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="label">Notes</label>
                    <textarea v-model="form.notes" rows="2" class="input" placeholder="Colour, finger size, crown condition…" />
                </div>
            </div>
            <template #footer>
                <button class="btn-ghost" @click="action = null">Cancel</button>
                <button class="btn-primary" :disabled="busy" @click="submit('inspect')">Save inspection</button>
            </template>
        </Modal>

        <Modal :open="action === 'move'" title="Move batch" :subtitle="`${b?.code} · ${kg(b?.remaining_kg)}`" icon="move" @close="action = null">
            <div class="space-y-3">
                <p class="text-xs muted">Current location: <b class="text-ink-800">{{ b?.location?.name || 'Unassigned' }}</b></p>
                <div class="space-y-2 max-h-[320px] overflow-y-auto scrollbar-thin pr-1">
                    <label
                        v-for="l in moveTargets" :key="l.id"
                        class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition"
                        :class="[form.storage_location_id === l.id ? 'border-brand-500 bg-brand-50' : 'border-ink-200 hover:border-ink-300', l.disabled ? 'opacity-40 pointer-events-none' : '']"
                    >
                        <input v-model="form.storage_location_id" type="radio" :value="l.id" class="accent-brand-500" />
                        <div class="flex-1">
                            <p class="text-sm font-semibold">{{ l.name }} <span class="font-mono text-[11px] muted">{{ l.code }}</span></p>
                            <p class="text-[11px] muted">{{ store.meta?.zones[l.zone] }}<span v-if="l.disabled"> · {{ l.reason }}</span></p>
                        </div>
                        <Chip v-if="l.zone === 'ripening'" :value="l.status" type="room" />
                    </label>
                </div>
                <div v-if="targetZone === 'ripening' && b?.status !== 'ripening'">
                    <label class="label">Planned ripening cycle: {{ form.ripening_days }} days</label>
                    <input v-model.number="form.ripening_days" type="range" min="3" max="8" step="0.5" class="w-full accent-brand-500" />
                </div>
                <input v-model="form.note" class="input" placeholder="Note (optional)" />
            </div>
            <template #footer>
                <button class="btn-ghost" @click="action = null">Cancel</button>
                <button class="btn-primary" :disabled="busy || !form.storage_location_id" @click="submit('move')">Move batch</button>
            </template>
        </Modal>

        <Modal :open="action === 'loss'" title="Log loss / spoilage" :subtitle="b?.code" icon="trash" @close="action = null">
            <div class="space-y-4">
                <div>
                    <label class="label">Quantity (kg). Max {{ num(b?.remaining_kg) }}</label>
                    <input v-model.number="form.quantity_kg" type="number" min="1" :max="b?.remaining_kg" class="input" />
                    <FieldError :msg="errors.quantity_kg" />
                </div>
                <div>
                    <label class="label">Reason</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button v-for="r in ['spoiled', 'damaged', 'overripe', 'handling', 'other']" :key="r" type="button" class="h-10 rounded-xl border text-xs font-semibold capitalize transition" :class="form.reason === r ? 'border-red-400 bg-red-50 text-red-700' : 'border-ink-200 hover:border-ink-300'" @click="form.reason = r">{{ r }}</button>
                    </div>
                </div>
                <textarea v-model="form.notes" rows="2" class="input" placeholder="What happened?" />
            </div>
            <template #footer>
                <button class="btn-ghost" @click="action = null">Cancel</button>
                <button class="btn-dark" :disabled="busy || !form.quantity_kg" @click="submit('loss')">Record loss</button>
            </template>
        </Modal>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import Icon from './Icon.vue';
import Chip from './Chip.vue';
import Tabs from './Tabs.vue';
import Modal from './Modal.vue';
import Empty from './Empty.vue';
import FieldError from './FieldError.vue';
import StagePicker from './StagePicker.vue';
import { api } from '../lib/api';
import { ago, date, dateTime, kg, money, num } from '../lib/format';
import { can, dataChanged, loadMeta, store, toast } from '../lib/store';

const b = ref(null);
const tab = ref('inspections');
const action = ref(null);
const busy = ref(false);
const errors = ref({});
const qrEl = ref();
const form = reactive({});

const canAct = computed(() => can.warehouse());
const sold = computed(() => b.value?.order_items?.reduce((a, i) => a + i.quantity_kg, 0) || 0);
const lost = computed(() => b.value?.losses?.reduce((a, i) => a + i.quantity_kg, 0) || 0);
const movable = computed(() => b.value && !['depleted', 'spoiled'].includes(b.value.status) && b.value.remaining_kg > 0);
const locs = (zone) => (store.meta?.locations || []).filter((l) => l.zone === zone);
const targetZone = computed(() => store.meta?.locations.find((l) => l.id === form.storage_location_id)?.zone);

const moveTargets = computed(() =>
    (store.meta?.locations || [])
        .filter((l) => l.id !== b.value?.storage_location_id && l.zone !== 'receiving')
        .map((l) => {
            let reason = null;
            if (l.status === 'maintenance') reason = 'Under maintenance';
            else if (['sales', 'dispatch'].includes(l.zone) && !['ripe', 'overripe'].includes(b.value?.stage)) reason = 'Only ripe stock';
            return { ...l, disabled: !!reason, reason };
        }),
);

const tabs = computed(() => [
    { label: 'Inspections', value: 'inspections', count: b.value?.inspections.length },
    { label: 'Movements', value: 'movements', count: b.value?.movements.length },
    { label: 'Sales', value: 'sales', count: b.value?.order_items.length },
    { label: 'Losses', value: 'losses', count: b.value?.losses.length },
]);

const checks = [
    { key: 'crown_rot', label: 'No crown rot' },
    { key: 'latex_stains', label: 'No latex stains' },
    { key: 'bruising', label: 'No bruising' },
    { key: 'size_uniform', label: 'Uniform size' },
];

const journey = computed(() => {
    const s = b.value;
    if (!s) return [];
    const order = ['receiving', 'pre_ripening', 'ripening', 'sales', 'depleted'];
    const idx = s.status === 'spoiled' ? 4 : order.indexOf(s.status);
    const mv = (st) => [...s.movements].reverse().find((m) => m.to_status === st)?.created_at;
    return [
        { key: 'receiving', label: 'Received', icon: 'truck', at: s.arrived_at },
        { key: 'pre_ripening', label: 'Pre-ripe', icon: 'package', at: mv('pre_ripening') },
        { key: 'ripening', label: 'Ripening', icon: 'flame', at: s.ripening_started_at },
        { key: 'sales', label: 'For sale', icon: 'cart', at: mv('sales') },
        { key: 'depleted', label: s.status === 'spoiled' ? 'Spoiled' : 'Sold out', icon: s.status === 'spoiled' ? 'trash' : 'check', at: s.depleted_at },
    ].map((j, i) => ({ ...j, done: i < idx || (i === idx && i === 4), current: i === idx && i !== 4 }));
});

const facts = computed(() => {
    const s = b.value;
    return [
        ['Supplier', s.supplier?.name],
        ['Variety', s.variety],
        ['Location', s.location ? `${s.location.name} (${s.location.code})` : '—'],
        ['Arrived', dateTime(s.arrived_at)],
        ['Received by', s.receiver?.name || '—'],
        ['Ripening started', dateTime(s.ripening_started_at)],
        ['Expected ripe', dateTime(s.expected_ripe_at)],
        ['Ripe at', dateTime(s.ripe_at)],
        ['Cost', `${money(s.cost_per_kg)} / kg`],
        ...(s.notes ? [['Notes', s.notes]] : []),
    ];
});

async function load() {
    const id = store.batchId;
    b.value = null;
    tab.value = 'inspections';
    loadMeta();
    const data = await api.get(`batches/${id}`);
    if (store.batchId !== id) return;
    b.value = data;
    await nextTick();
    const QR = (await import('qrcode')).default;
    if (qrEl.value) QR.toCanvas(qrEl.value, data.code, { width: 68, margin: 0, color: { dark: '#1a1916', light: '#ffffff' } });
}

watch(() => store.batchId, (id) => id && load());

function close() {
    store.batchId = null;
    action.value = null;
}

function openAction(a) {
    errors.value = {};
    const s = b.value;
    Object.keys(form).forEach((k) => delete form[k]);
    if (a === 'inspect') {
        const next = { green: 'turning', turning: 'ripe', ripe: 'ripe', overripe: 'overripe' }[s.stage];
        Object.assign(form, {
            stage: s.status === 'receiving' ? s.stage : next,
            type: s.status === 'receiving' ? 'arrival' : s.status === 'ripening' && next === 'ripe' ? 'pre_sale' : 'ripening',
            grade: s.grade, damage_pct: 0, pulp_temp: null, notes: '',
            checks: { crown_rot: true, latex_stains: true, bruising: true, size_uniform: true },
            storage_location_id: s.status === 'receiving' ? locs('pre_ripening')[0]?.id : null,
        });
    }
    if (a === 'move') {
        const suggested = s.status === 'pre_ripening' ? locs('ripening').find((l) => l.status !== 'maintenance') : ['ripe', 'overripe'].includes(s.stage) ? locs('sales')[0] : null;
        Object.assign(form, { storage_location_id: suggested?.id || null, ripening_days: 5, note: '' });
    }
    if (a === 'loss') Object.assign(form, { quantity_kg: null, reason: s.stage === 'overripe' ? 'spoiled' : 'damaged', notes: '' });
    action.value = a;
}

async function submit(a) {
    busy.value = true;
    errors.value = {};
    try {
        const id = b.value.id;
        if (a === 'inspect') await api.post(`batches/${id}/inspect`, form);
        if (a === 'move') await api.post(`batches/${id}/move`, form);
        if (a === 'loss') await api.post(`batches/${id}/loss`, form);
        toast({ inspect: 'Inspection saved', move: 'Batch moved', loss: 'Loss recorded' }[a]);
        action.value = null;
        await load();
        dataChanged();
    } catch (e) {
        errors.value = e.errors;
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}

function printLabel() {
    const s = b.value;
    if (!s || !qrEl.value) return;
    const img = qrEl.value.toDataURL();
    const w = window.open('', '_blank', 'width=420,height=520');
    w.document.write(`<html><head><title>${s.code}</title><style>body{font-family:system-ui;margin:0;display:grid;place-items:center;height:100vh}.l{border:3px solid #1a1916;border-radius:16px;padding:20px;width:300px;text-align:center}.b{background:#facc15;font-weight:800;padding:6px;border-radius:8px;margin-bottom:12px}img{width:180px;image-rendering:pixelated}h1{font-family:monospace;margin:10px 0 4px}p{margin:2px;color:#555;font-size:13px}</style></head><body><div class="l"><div class="b">🍌 MAKATA BANANA</div><img src="${img}"><h1>${s.code}</h1><p>${s.supplier?.name}</p><p>${s.variety} · Grade ${s.grade} · ${s.initial_kg} kg</p><p>Arrived ${new Date(s.arrived_at).toLocaleDateString()}</p></div><script>setTimeout(()=>print(),300)<\/script></body></html>`);
    w.document.close();
}
</script>
