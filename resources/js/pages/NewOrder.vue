<template>
    <div class="grid xl:grid-cols-[1fr_420px] gap-5 items-start">
        <div class="space-y-5">
            <RouterLink to="/sales" class="inline-flex items-center gap-1 text-sm font-semibold muted hover:text-ink-900"><Icon name="chevronLeft" :size="16" /> Back to orders</RouterLink>

            <!-- Customer -->
            <div class="card card-pad">
                <div class="flex items-center justify-between mb-4">
                    <p class="section-title"><span class="text-brand-600 mr-1">1.</span> Customer</p>
                    <button class="btn-ghost btn-sm" @click="custOpen = true"><Icon name="plus" :size="14" /> New customer</button>
                </div>
                <div class="relative mb-3">
                    <Icon name="search" :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
                    <input v-model="custSearch" class="input h-9 pl-9" placeholder="Find customer…" />
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-[260px] overflow-y-auto scrollbar-thin pr-1">
                    <button v-for="c in customers" :key="c.id" class="flex items-center gap-3 rounded-xl border p-3 text-left transition" :class="form.customer_id === c.id ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-200' : 'border-ink-200 hover:border-ink-300'" @click="form.customer_id = c.id">
                        <span class="grid place-items-center size-9 rounded-lg text-xs font-bold" :class="form.customer_id === c.id ? 'bg-brand-400' : 'bg-ink-100 text-ink-600'">{{ c.name.split(' ').map((p) => p[0]).slice(0, 2).join('') }}</span>
                        <span class="min-w-0"><span class="block text-sm font-semibold truncate">{{ c.name }}</span><span class="block text-[11px] muted capitalize">{{ c.type }}</span></span>
                    </button>
                </div>
            </div>

            <!-- Lines -->
            <div class="card card-pad">
                <p class="section-title mb-1"><span class="text-brand-600 mr-1">2.</span> What does the customer need?</p>
                <p class="text-xs muted mb-4">Choose ripeness and quantity. The system picks batches using FIFO.</p>
                <div class="space-y-3">
                    <div v-for="(l, i) in form.lines" :key="i" class="rounded-2xl border border-ink-200 p-4 grid sm:grid-cols-[1fr_auto_160px_auto] gap-3 items-end">
                        <div>
                            <label class="label">Ripeness</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="s in ['ripe', 'overripe']" :key="s" class="h-10 rounded-xl border text-sm font-semibold flex items-center justify-center gap-2 transition" :class="l.stage === s ? 'border-ink-900 bg-ink-900 text-white' : 'border-ink-200'" @click="l.stage = s">
                                    <span class="size-2.5 rounded-full" :style="{ background: STAGES[s].hex }" />{{ STAGES[s].label }}
                                    <span class="text-[10px] font-medium opacity-60 tabular">{{ tonnes(available[s]) }}</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="label">Grade</label>
                            <select v-model="l.grade" class="input w-28"><option :value="null">Any</option><option v-for="g in ['A', 'B', 'C']" :key="g" :value="g">{{ g }}</option></select>
                        </div>
                        <div>
                            <label class="label">Quantity (kg)</label>
                            <input v-model.number="l.quantity_kg" type="number" min="1" step="10" class="input tabular" />
                            <div class="flex gap-1 mt-1.5">
                                <button v-for="q in [180, 540, 1080]" :key="q" class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-ink-100 hover:bg-brand-200" @click="l.quantity_kg = q">{{ q / 18 }} ctn</button>
                            </div>
                        </div>
                        <button class="btn-icon btn-ghost text-ink-400 hover:text-red-600" :disabled="form.lines.length === 1" @click="form.lines.splice(i, 1)"><Icon name="trash" :size="16" /></button>
                        <FieldError class="sm:col-span-4 -mt-2" :msg="errors[`lines.${i}.quantity_kg`]" />
                    </div>
                </div>
                <button class="btn-outline btn-sm mt-3" @click="form.lines.push({ stage: 'ripe', grade: null, quantity_kg: 100 })"><Icon name="plus" :size="14" /> Add line</button>
                <textarea v-model="form.notes" rows="2" class="input mt-4" placeholder="Delivery notes (optional)" />
            </div>
        </div>

        <!-- Allocation summary -->
        <div class="xl:sticky xl:top-24 space-y-4">
            <div class="card overflow-hidden">
                <div class="bg-ink-950 text-white p-5">
                    <p class="text-xs text-ink-400 font-semibold">Order total</p>
                    <p class="text-4xl font-extrabold tabular tracking-tight mt-1">{{ money(preview?.total || 0) }}</p>
                    <div class="flex gap-4 mt-3 text-xs">
                        <span><span class="text-ink-400">Qty</span> <b class="tabular">{{ kg(preview?.quantity_kg || 0) }}</b></span>
                        <span><span class="text-ink-400">Batches</span> <b>{{ preview?.allocations.length || 0 }}</b></span>
                        <span v-if="preview?.discount" class="text-brand-300"><span class="text-ink-400">Saved</span> <b class="tabular">{{ money(preview.discount) }}</b></span>
                    </div>
                </div>
                <div class="p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-bold">FIFO batch allocation</p>
                        <span v-if="previewing" class="size-4 border-2 border-ink-200 border-t-brand-500 rounded-full animate-spin" />
                    </div>
                    <div v-if="preview?.allocations.length" class="space-y-2">
                        <div v-for="(a, i) in preview.allocations" :key="i" class="flex items-center gap-3 rounded-xl bg-ink-50 p-3 animate-fade-up" :style="{ animationDelay: i * 40 + 'ms' }">
                            <span class="grid place-items-center size-7 rounded-lg bg-white border border-ink-200 text-[11px] font-extrabold">{{ i + 1 }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold font-mono">{{ a.batch_code }}</p>
                                <p class="text-[10px] muted truncate">{{ a.supplier }} · Grade {{ a.grade }} · <ShelfLife :days="a.shelf_days_left" /></p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold tabular">{{ kg(a.quantity_kg) }}</p>
                                <p class="text-[10px] tabular" :class="a.discount_pct ? 'text-emerald-700 font-semibold' : 'muted'">{{ money(a.unit_price) }}{{ a.discount_pct ? ` −${a.discount_pct}%` : '' }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else-if="previewError" class="text-sm text-red-600 bg-red-50 rounded-xl p-3">{{ previewError }}</p>
                    <p v-else class="text-sm muted text-center py-6">Add quantities to see which batches will be used.</p>
                </div>
                <div class="px-5 pb-5">
                    <button class="btn-primary w-full h-12 text-base" :disabled="!canSubmit || saving" @click="submit">
                        <Icon name="check" :size="18" :stroke="2.4" /> Confirm & allocate stock
                    </button>
                    <p v-if="!form.customer_id" class="text-[11px] muted text-center mt-2">Select a customer to continue</p>
                </div>
            </div>
        </div>

        <Modal :open="custOpen" title="New customer" icon="users" @close="custOpen = false">
            <form id="cust" class="space-y-3" @submit.prevent="addCustomer">
                <div><label class="label">Name</label><input v-model="cust.name" class="input" required /></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Type</label><select v-model="cust.type" class="input"><option v-for="t in ['supermarket', 'retailer', 'wholesaler', 'hotel', 'market']" :key="t" :value="t" class="capitalize">{{ t }}</option></select></div>
                    <div><label class="label">Phone</label><input v-model="cust.phone" class="input" /></div>
                </div>
                <div><label class="label">Location</label><input v-model="cust.location" class="input" /></div>
            </form>
            <template #footer><button class="btn-ghost" @click="custOpen = false">Cancel</button><button form="cust" class="btn-primary">Add customer</button></template>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import ShelfLife from '../components/ShelfLife.vue';
import FieldError from '../components/FieldError.vue';
import { api } from '../lib/api';
import { kg, money, STAGES, tonnes } from '../lib/format';
import { dataChanged, loadMeta, store, toast } from '../lib/store';

const router = useRouter();
const form = reactive({ customer_id: null, notes: '', lines: [{ stage: 'ripe', grade: null, quantity_kg: 360 }] });
const preview = ref(null);
const previewError = ref('');
const previewing = ref(false);
const saving = ref(false);
const errors = ref({});
const custSearch = ref('');
const custOpen = ref(false);
const cust = reactive({ name: '', type: 'retailer', phone: '', location: '' });
const stock = ref([]);

const customers = computed(() => (store.meta?.customers || []).filter((c) => c.name.toLowerCase().includes(custSearch.value.toLowerCase())));
const available = computed(() => ({
    ripe: stock.value.filter((b) => b.stage === 'ripe').reduce((a, b) => a + b.remaining_kg, 0),
    overripe: stock.value.filter((b) => b.stage === 'overripe').reduce((a, b) => a + b.remaining_kg, 0),
}));
const canSubmit = computed(() => form.customer_id && preview.value?.allocations.length && !previewError.value);

let t;
watch(() => JSON.stringify(form.lines), () => (clearTimeout(t), (t = setTimeout(runPreview, 300))));

async function runPreview() {
    const lines = form.lines.filter((l) => l.quantity_kg > 0);
    if (!lines.length) return (preview.value = null);
    previewing.value = true;
    previewError.value = '';
    errors.value = {};
    try {
        preview.value = await api.post('orders/preview', { lines });
    } catch (e) {
        preview.value = null;
        errors.value = e.errors;
        previewError.value = Object.values(e.errors || {})[0]?.[0] || e.message;
    } finally {
        previewing.value = false;
    }
}

async function submit() {
    saving.value = true;
    try {
        const o = await api.post('orders', form);
        toast(`Order ${o.number} created: ${money(o.total)}`);
        dataChanged();
        router.push({ name: 'sales', query: { open: o.id } });
    } catch (e) {
        errors.value = e.errors;
        toast(e.message, 'error');
    } finally {
        saving.value = false;
    }
}

async function addCustomer() {
    try {
        const c = await api.post('customers', cust);
        await loadMeta(true);
        form.customer_id = c.id;
        custOpen.value = false;
        toast(`${c.name} added`);
    } catch (e) {
        toast(e.message, 'error');
    }
}

onMounted(async () => {
    loadMeta();
    stock.value = (await api.get('batches', { status: 'sales', per_page: 100 })).data;
    runPreview();
});
</script>
