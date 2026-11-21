<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">Sales orders</h2>
                <p class="text-sm muted">Ripe stock is allocated automatically from the oldest batches (FIFO).</p>
            </div>
            <RouterLink to="/sales/new" class="btn-primary"><Icon name="plus" :size="16" :stroke="2.4" /> New order</RouterLink>
        </div>

        <!-- Status pipeline -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            <button v-for="(s, k) in ORDER_STATUSES" :key="k" class="card p-4 text-left transition hover:-translate-y-0.5" :class="status === k ? 'ring-2 ring-brand-400 border-brand-400' : ''" @click="status = status === k ? '' : k">
                <Chip :value="k" type="order" />
                <p class="text-2xl font-extrabold mt-2 tabular">{{ list.counts?.[k] || 0 }}</p>
            </button>
        </div>

        <div class="card overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 p-4 border-b border-ink-100">
                <div class="relative flex-1 min-w-[220px]">
                    <Icon name="search" :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
                    <input v-model="search" class="input h-9 pl-9" placeholder="Order number or customer…" />
                </div>
                <button v-if="status" class="btn-ghost btn-sm" @click="status = ''"><Icon name="x" :size="14" /> Clear filter</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px]">
                    <thead><tr><th class="table-head">Order</th><th class="table-head">Customer</th><th class="table-head">Date</th><th class="table-head text-right">Quantity</th><th class="table-head text-right">Batches</th><th class="table-head text-right">Discount</th><th class="table-head text-right">Total</th><th class="table-head">Status</th></tr></thead>
                    <tbody :class="loading ? 'opacity-60' : ''">
                        <tr v-for="o in list.data" :key="o.id" class="hover:bg-brand-50/50 cursor-pointer" @click="openOrder(o.id)">
                            <td class="table-cell font-mono font-bold">{{ o.number }}</td>
                            <td class="table-cell"><p class="font-semibold">{{ o.customer?.name }}</p><p class="text-[11px] muted capitalize">{{ o.customer?.type }}</p></td>
                            <td class="table-cell text-ink-600 whitespace-nowrap">{{ dateTime(o.ordered_at) }}</td>
                            <td class="table-cell text-right tabular">{{ kg(o.quantity_kg) }}</td>
                            <td class="table-cell text-right tabular">{{ o.items_count }}</td>
                            <td class="table-cell text-right tabular" :class="o.discount ? 'text-emerald-700' : 'text-ink-300'">{{ o.discount ? '−' + money(o.discount) : '–' }}</td>
                            <td class="table-cell text-right font-bold tabular">{{ money(o.total) }}</td>
                            <td class="table-cell"><Chip :value="o.status" type="order" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pager :meta="list" @page="fetch" />
        </div>

        <Modal :open="!!order" :title="order?.number" :subtitle="order ? `${order.customer?.name} · ${dateTime(order.ordered_at)}` : ''" icon="cart" width="sm:max-w-2xl" @close="order = null">
            <div v-if="order" class="space-y-5">
                <!-- Status track -->
                <div class="flex items-center">
                    <template v-for="(s, i) in flow" :key="s">
                        <div class="flex flex-col items-center gap-1.5 w-20">
                            <span class="grid place-items-center size-8 rounded-full border-2" :class="flowIdx >= i && order.status !== 'cancelled' ? 'bg-brand-400 border-brand-400' : 'border-ink-200 text-ink-300'">
                                <Icon :name="flowIdx > i ? 'check' : ['clipboard', 'package', 'truck', 'check'][i]" :size="14" :stroke="2.4" />
                            </span>
                            <span class="text-[11px] font-semibold capitalize" :class="flowIdx >= i ? '' : 'muted'">{{ s }}</span>
                        </div>
                        <div v-if="i < flow.length - 1" class="flex-1 h-0.5 -mt-5" :class="flowIdx > i && order.status !== 'cancelled' ? 'bg-brand-400' : 'bg-ink-200'" />
                    </template>
                </div>
                <div v-if="order.status === 'cancelled'" class="rounded-xl bg-red-50 text-red-700 text-sm font-semibold px-4 py-3">This order was cancelled and its stock returned to inventory.</div>

                <div class="rounded-2xl border border-ink-200 overflow-hidden">
                    <table class="w-full">
                        <thead><tr><th class="table-head">Batch (FIFO)</th><th class="table-head">Stage</th><th class="table-head text-right">Qty</th><th class="table-head text-right">Price/kg</th><th class="table-head text-right">Line total</th></tr></thead>
                        <tbody>
                            <tr v-for="it in order.items" :key="it.id">
                                <td class="table-cell"><button class="font-mono font-bold hover:text-brand-700" @click="openBatch(it.batch_id)">{{ it.batch?.code }}</button><p class="text-[11px] muted">{{ it.batch?.supplier?.name }} · Grade {{ it.batch?.grade }}</p></td>
                                <td class="table-cell"><Chip :value="it.stage" /></td>
                                <td class="table-cell text-right tabular">{{ kg(it.quantity_kg) }}</td>
                                <td class="table-cell text-right tabular">{{ money(it.unit_price) }}<p v-if="it.discount_pct" class="text-[11px] text-emerald-700">−{{ it.discount_pct }}%</p></td>
                                <td class="table-cell text-right font-semibold tabular">{{ money(it.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="bg-ink-50 px-4 py-3 space-y-1 text-sm">
                        <div class="flex justify-between"><span class="muted">Subtotal</span><span class="tabular">{{ money(order.subtotal) }}</span></div>
                        <div class="flex justify-between text-emerald-700"><span>Ripeness discounts</span><span class="tabular">−{{ money(order.discount) }}</span></div>
                        <div class="flex justify-between font-extrabold text-base pt-1"><span>Total</span><span class="tabular">{{ money(order.total) }}</span></div>
                    </div>
                </div>
                <p class="text-xs muted">Created by {{ order.user?.name }}</p>
            </div>
            <template #footer>
                <button v-if="order && !['cancelled', 'delivered'].includes(order.status)" class="btn-danger mr-auto" :disabled="busy" @click="setStatus('cancelled')">Cancel order</button>
                <button class="btn-ghost" @click="order = null">Close</button>
                <button v-if="nextStatus" class="btn-primary" :disabled="busy" @click="setStatus(nextStatus)">Mark as {{ nextStatus }} <Icon name="arrowRight" :size="15" /></button>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Icon from '../components/Icon.vue';
import Chip from '../components/Chip.vue';
import Modal from '../components/Modal.vue';
import Pager from '../components/Pager.vue';
import { api } from '../lib/api';
import { dateTime, kg, money, ORDER_STATUSES } from '../lib/format';
import { dataChanged, onDataChanged, openBatch, toast } from '../lib/store';

const route = useRoute();
const list = ref({});
const loading = ref(true);
const search = ref('');
const status = ref('');
const order = ref(null);
const busy = ref(false);
const flow = ['allocated', 'packed', 'dispatched', 'delivered'];
const flowIdx = computed(() => flow.indexOf(order.value?.status));
const nextStatus = computed(() => (order.value && flowIdx.value >= 0 && flowIdx.value < 3 ? flow[flowIdx.value + 1] : null));

async function fetch(page = 1) {
    loading.value = true;
    list.value = await api.get('orders', { page, search: search.value, status: status.value });
    loading.value = false;
}
async function openOrder(id) {
    order.value = await api.get(`orders/${id}`);
}
async function setStatus(s) {
    busy.value = true;
    try {
        order.value = await api.patch(`orders/${order.value.id}/status`, { status: s });
        toast(`Order marked ${s}`);
        fetch(list.value.current_page);
        dataChanged();
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        busy.value = false;
    }
}
let t;
watch(search, () => (clearTimeout(t), (t = setTimeout(() => fetch(1), 250))));
watch(status, () => fetch(1));
let off;
onMounted(() => {
    fetch();
    if (route.query.open) openOrder(route.query.open);
    off = onDataChanged(() => fetch(list.value.current_page));
});
onBeforeUnmount(() => off());
</script>
