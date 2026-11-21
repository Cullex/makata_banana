<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">Users & audit log</h2>
                <p class="text-sm muted">Role-based access control and a full record of who changed what.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Tabs v-model="tab" :tabs="[{ label: 'Team', value: 'team', count: users.length }, { label: 'Audit log', value: 'audit' }, { label: 'Permissions', value: 'perms' }]" />
                <button v-if="tab === 'team'" class="btn-primary" @click="openNew"><Icon name="plus" :size="16" :stroke="2.4" /> Add user</button>
            </div>
        </div>

        <div v-if="tab === 'team'" class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="u in users" :key="u.id" class="card card-pad flex items-center gap-4" :class="!u.active ? 'opacity-60' : ''">
                <span class="grid place-items-center size-12 rounded-2xl text-sm font-extrabold" :class="roleStyle[u.role]">{{ u.initials }}</span>
                <div class="flex-1 min-w-0">
                    <p class="font-bold truncate">{{ u.name }} <span v-if="u.id === store.user.id" class="chip bg-ink-100 text-ink-600 ml-1">You</span></p>
                    <p class="text-xs muted truncate">{{ u.email }}</p>
                    <div class="flex items-center gap-2 mt-2">
                        <select :value="u.role" class="h-7 rounded-lg border border-ink-200 text-xs font-semibold px-2 bg-white" :disabled="u.id === store.user.id" @change="update(u, { role: $event.target.value })">
                            <option v-for="(label, key) in store.meta?.roles" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <span class="text-[11px] muted">seen {{ ago(u.last_seen_at) }}</span>
                    </div>
                </div>
                <button class="btn-icon btn-ghost" :title="u.active ? 'Deactivate' : 'Activate'" :disabled="u.id === store.user.id" @click="update(u, { active: !u.active })">
                    <Icon name="power" :size="17" :class="u.active ? 'text-emerald-600' : 'text-ink-400'" />
                </button>
            </div>
        </div>

        <div v-if="tab === 'audit'" class="card overflow-hidden">
            <div class="flex flex-wrap gap-2 p-4 border-b border-ink-100">
                <select v-model="auditFilter.user_id" class="input h-9 w-auto"><option value="">All users</option><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option></select>
                <select v-model="auditFilter.action" class="input h-9 w-auto">
                    <option value="">All actions</option>
                    <option value="batch">Batches</option>
                    <option value="order">Orders</option>
                    <option value="room">Room controls</option>
                    <option value="price">Pricing</option>
                    <option value="user">Users</option>
                    <option value="auth">Sign-ins</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead><tr><th class="table-head">When</th><th class="table-head">User</th><th class="table-head">Action</th><th class="table-head">Details</th></tr></thead>
                    <tbody>
                        <tr v-for="a in audit.data" :key="a.id">
                            <td class="table-cell text-ink-600 whitespace-nowrap">{{ dateTime(a.created_at) }}</td>
                            <td class="table-cell"><p class="font-semibold">{{ a.user?.name || 'System' }}</p><p class="text-[11px] muted capitalize">{{ a.user?.role }}</p></td>
                            <td class="table-cell"><code class="text-[11px] font-semibold bg-ink-100 rounded-md px-1.5 py-0.5">{{ a.action }}</code></td>
                            <td class="table-cell">{{ a.description }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pager :meta="audit" @page="loadAudit" />
        </div>

        <div v-if="tab === 'perms'" class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead><tr><th class="table-head">Capability</th><th v-for="(label, key) in store.meta?.roles" :key="key" class="table-head text-center">{{ label }}</th></tr></thead>
                    <tbody>
                        <tr v-for="p in perms" :key="p[0]">
                            <td class="table-cell font-semibold">{{ p[0] }}</td>
                            <td v-for="(role, i) in ['manager', 'warehouse', 'inspector', 'sales']" :key="role" class="table-cell text-center">
                                <span v-if="p[1][i]" class="inline-grid place-items-center size-6 rounded-full bg-emerald-100 text-emerald-700"><Icon name="check" :size="13" :stroke="3" /></span>
                                <span v-else class="text-ink-300">–</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :open="newOpen" title="Add team member" icon="users" @close="newOpen = false">
            <form id="user" class="space-y-3" @submit.prevent="save">
                <div><label class="label">Full name</label><input v-model="form.name" class="input" required /></div>
                <div><label class="label">Email</label><input v-model="form.email" type="email" class="input" required /><FieldError :msg="errors.email" /></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Role</label><select v-model="form.role" class="input"><option v-for="(label, key) in store.meta?.roles" :key="key" :value="key">{{ label }}</option></select></div>
                    <div><label class="label">Phone</label><input v-model="form.phone" class="input" /></div>
                </div>
                <p class="text-xs muted">Default password: <code class="font-semibold">password</code></p>
            </form>
            <template #footer><button class="btn-ghost" @click="newOpen = false">Cancel</button><button form="user" class="btn-primary">Add user</button></template>
        </Modal>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import Icon from '../components/Icon.vue';
import Tabs from '../components/Tabs.vue';
import Modal from '../components/Modal.vue';
import Pager from '../components/Pager.vue';
import FieldError from '../components/FieldError.vue';
import { api } from '../lib/api';
import { ago, dateTime } from '../lib/format';
import { loadMeta, store, toast } from '../lib/store';

const tab = ref('team');
const users = ref([]);
const audit = ref({});
const auditFilter = reactive({ user_id: '', action: '' });
const newOpen = ref(false);
const form = reactive({});
const errors = ref({});
const roleStyle = { manager: 'bg-ink-900 text-brand-400', warehouse: 'bg-sky-100 text-sky-800', inspector: 'bg-violet-100 text-violet-800', sales: 'bg-brand-200 text-brand-900' };
const perms = [
    ['View dashboard & inventory', [1, 1, 1, 1]],
    ['Receive batches', [1, 1, 1, 0]],
    ['Inspect & update ripening stage', [1, 1, 1, 0]],
    ['Control ripening rooms', [1, 1, 1, 0]],
    ['Move batches / log losses', [1, 1, 1, 0]],
    ['Create & manage sales orders', [1, 0, 0, 1]],
    ['Edit pricing', [1, 0, 0, 0]],
    ['Reports & analytics', [1, 0, 0, 0]],
    ['Manage users & audit log', [1, 0, 0, 0]],
];

async function load() {
    users.value = await api.get('users');
}
async function loadAudit(page = 1) {
    audit.value = await api.get('activity', { page, ...auditFilter });
}
async function update(u, data) {
    try {
        Object.assign(u, await api.patch(`users/${u.id}`, data));
        toast(`${u.name} updated`);
    } catch (e) {
        toast(e.message, 'error');
    }
}
function openNew() {
    Object.assign(form, { name: '', email: '', role: 'warehouse', phone: '' });
    errors.value = {};
    newOpen.value = true;
}
async function save() {
    try {
        await api.post('users', form);
        toast('User added');
        newOpen.value = false;
        load();
    } catch (e) {
        errors.value = e.errors;
    }
}
watch(tab, (t) => t === 'audit' && loadAudit());
watch(auditFilter, () => loadAudit());
onMounted(() => (loadMeta(), load()));
</script>
