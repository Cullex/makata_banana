<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">Suppliers & customers</h2>
                <p class="text-sm muted">The farms we buy from and the businesses we sell to.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Tabs v-model="tab" :tabs="[{ label: 'Suppliers', value: 'suppliers', count: suppliers.length }, { label: 'Customers', value: 'customers', count: customers.length }]" />
                <button v-if="tab === 'suppliers' ? can.warehouse() : can.sales()" class="btn-primary" @click="openNew"><Icon name="plus" :size="16" :stroke="2.4" /> Add</button>
            </div>
        </div>

        <div v-if="tab === 'suppliers'" class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="s in suppliers" :key="s.id" class="card card-pad animate-fade-up">
                <div class="flex items-start gap-3">
                    <span class="grid place-items-center size-12 rounded-2xl bg-lime-100 text-lime-700"><Icon name="map" :size="22" /></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold">{{ s.name }}</p>
                        <p class="text-xs muted">{{ s.region }} · {{ s.contact_person }}</p>
                    </div>
                    <span class="chip bg-brand-100 text-brand-800"><Icon name="star" :size="11" /> {{ s.rating }}</span>
                </div>
                <div class="grid grid-cols-3 gap-2 mt-4">
                    <div class="rounded-xl bg-ink-50 p-2.5"><p class="text-[10px] muted">Deliveries</p><p class="font-bold tabular">{{ s.batches_count }}</p></div>
                    <div class="rounded-xl bg-ink-50 p-2.5"><p class="text-[10px] muted">Volume</p><p class="font-bold tabular">{{ tonnes(s.total_kg || 0) }}</p></div>
                    <div class="rounded-xl bg-ink-50 p-2.5"><p class="text-[10px] muted">Last</p><p class="font-bold text-sm">{{ s.last_delivery ? ago(s.last_delivery) : '—' }}</p></div>
                </div>
                <p class="text-xs muted mt-3">{{ s.phone }}</p>
            </div>
        </div>

        <div v-else class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead><tr><th class="table-head">Customer</th><th class="table-head">Type</th><th class="table-head">Location</th><th class="table-head">Phone</th><th class="table-head text-right">Orders</th><th class="table-head text-right">Lifetime revenue</th><th class="table-head">Last order</th></tr></thead>
                    <tbody>
                        <tr v-for="c in customers" :key="c.id">
                            <td class="table-cell">
                                <div class="flex items-center gap-3">
                                    <span class="grid place-items-center size-9 rounded-xl bg-ink-100 text-xs font-bold text-ink-600">{{ c.name.split(' ').map((p) => p[0]).slice(0, 2).join('') }}</span>
                                    <span class="font-semibold">{{ c.name }}</span>
                                </div>
                            </td>
                            <td class="table-cell"><span class="chip capitalize" :class="typeChip[c.type]">{{ c.type }}</span></td>
                            <td class="table-cell text-ink-600">{{ c.location }}</td>
                            <td class="table-cell text-ink-600">{{ c.phone }}</td>
                            <td class="table-cell text-right tabular">{{ c.orders_count }}</td>
                            <td class="table-cell text-right font-bold tabular">{{ money(c.revenue || 0, true) }}</td>
                            <td class="table-cell text-ink-600">{{ c.last_order ? ago(c.last_order) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :open="newOpen" :title="tab === 'suppliers' ? 'New supplier' : 'New customer'" icon="handshake" @close="newOpen = false">
            <form id="partner" class="space-y-3" @submit.prevent="save">
                <div><label class="label">Name</label><input v-model="form.name" class="input" required /></div>
                <template v-if="tab === 'suppliers'">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="label">Contact person</label><input v-model="form.contact_person" class="input" /></div>
                        <div><label class="label">Region</label><input v-model="form.region" class="input" /></div>
                    </div>
                </template>
                <template v-else>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="label">Type</label><select v-model="form.type" class="input"><option v-for="t in Object.keys(typeChip)" :key="t" :value="t">{{ t }}</option></select></div>
                        <div><label class="label">Location</label><input v-model="form.location" class="input" /></div>
                    </div>
                </template>
                <div><label class="label">Phone</label><input v-model="form.phone" class="input" /></div>
            </form>
            <template #footer><button class="btn-ghost" @click="newOpen = false">Cancel</button><button form="partner" class="btn-primary">Save</button></template>
        </Modal>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import Icon from '../components/Icon.vue';
import Tabs from '../components/Tabs.vue';
import Modal from '../components/Modal.vue';
import { api } from '../lib/api';
import { ago, money, tonnes } from '../lib/format';
import { can, loadMeta, toast } from '../lib/store';

const tab = ref(can.sales() && !can.warehouse() ? 'customers' : 'suppliers');
const suppliers = ref([]);
const customers = ref([]);
const newOpen = ref(false);
const form = reactive({});
const typeChip = { supermarket: 'bg-sky-100 text-sky-800', retailer: 'bg-violet-100 text-violet-800', wholesaler: 'bg-brand-100 text-brand-800', hotel: 'bg-emerald-100 text-emerald-800', market: 'bg-orange-100 text-orange-800' };

async function load() {
    [suppliers.value, customers.value] = await Promise.all([api.get('suppliers'), api.get('customers')]);
}
function openNew() {
    Object.keys(form).forEach((k) => delete form[k]);
    Object.assign(form, { name: '', phone: '', type: 'retailer' });
    newOpen.value = true;
}
async function save() {
    try {
        await api.post(tab.value, form);
        toast('Saved');
        newOpen.value = false;
        load();
        loadMeta(true);
    } catch (e) {
        toast(e.message, 'error');
    }
}
onMounted(load);
</script>
