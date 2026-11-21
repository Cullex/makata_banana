<template>
    <div class="min-h-dvh lg:pl-[264px]">
        <!-- Sidebar -->
        <div v-if="store.sidebarOpen" class="fixed inset-0 bg-ink-950/50 backdrop-blur-sm z-40 lg:hidden" @click="store.sidebarOpen = false" />
        <aside
            class="fixed inset-y-0 left-0 z-50 w-[264px] bg-ink-950 text-ink-300 flex flex-col transition-transform duration-300 lg:translate-x-0"
            :class="store.sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <RouterLink to="/" class="flex items-center gap-3 px-5 h-[72px] shrink-0">
                <span class="grid place-items-center size-10 rounded-xl bg-brand-400 text-ink-950 shadow-brand">
                    <Icon name="banana" :size="22" :stroke="2" />
                </span>
                <span class="leading-tight">
                    <span class="block text-white font-extrabold tracking-tight">Makata Banana</span>
                    <span class="block text-[11px] text-ink-400 font-medium">Warehouse Management</span>
                </span>
            </RouterLink>

            <nav class="flex-1 overflow-y-auto scrollbar-thin px-3 pb-4">
                <template v-for="section in nav" :key="section.label">
                    <p class="px-3 pt-5 pb-2 text-[10px] font-bold uppercase tracking-[.14em] text-ink-500">{{ section.label }}</p>
                    <RouterLink
                        v-for="item in section.items"
                        :key="item.to"
                        :to="item.to"
                        class="group flex items-center gap-3 h-10 px-3 rounded-xl text-[13.5px] font-medium transition mb-0.5"
                        :class="isActive(item) ? 'bg-brand-400 text-ink-950 shadow-brand' : 'hover:bg-white/5 hover:text-white'"
                    >
                        <Icon :name="item.icon" :size="18" />
                        <span class="flex-1">{{ item.label }}</span>
                        <span v-if="item.badge" class="min-w-5 h-5 px-1.5 rounded-full text-[10px] font-bold grid place-items-center" :class="isActive(item) ? 'bg-ink-950 text-brand-300' : 'bg-brand-400 text-ink-950'">{{ item.badge }}</span>
                    </RouterLink>
                </template>
            </nav>

            <!-- Process flow mini map -->
            <div class="mx-3 mb-3 rounded-2xl bg-white/[.04] border border-white/5 p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[.14em] text-ink-500 mb-2.5">Warehouse flow</p>
                <div class="flex items-center justify-between">
                    <template v-for="(s, i) in flow" :key="s.label">
                        <div class="flex flex-col items-center gap-1">
                            <span class="size-2.5 rounded-full" :style="{ background: s.color }" />
                            <span class="text-[9px] text-ink-400 font-medium">{{ s.label }}</span>
                        </div>
                        <span v-if="i < flow.length - 1" class="flex-1 h-px bg-ink-700 -mt-3.5 mx-0.5" />
                    </template>
                </div>
            </div>

            <div class="border-t border-white/5 p-3">
                <div class="flex items-center gap-3 p-2 rounded-xl">
                    <span class="grid place-items-center size-9 rounded-full bg-brand-400/15 text-brand-300 text-xs font-bold">{{ store.user?.initials }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate">{{ store.user?.name }}</p>
                        <p class="text-[11px] text-ink-400 truncate">{{ store.user?.role_label }}</p>
                    </div>
                    <button class="btn-icon h-9 w-9 text-ink-400 hover:text-white hover:bg-white/5" title="Sign out" @click="logout">
                        <Icon name="logout" :size="17" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Top bar -->
        <header class="sticky top-0 z-30 bg-ink-50/85 backdrop-blur-xl border-b border-ink-200/60">
            <div class="flex items-center gap-3 h-16 px-4 sm:px-6 lg:px-8">
                <button class="btn-icon btn-ghost lg:hidden -ml-2" @click="store.sidebarOpen = true"><Icon name="menu" /></button>
                <div class="min-w-0">
                    <h1 class="text-lg font-bold tracking-tight truncate">{{ route.meta.title }}</h1>
                </div>
                <div class="flex-1" />

                <form class="relative hidden md:block" @submit.prevent="findBatch">
                    <Icon name="search" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400" />
                    <input v-model="q" class="input h-10 w-64 pl-9 bg-white" placeholder="Scan or type batch code…" />
                    <kbd class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-ink-400 border border-ink-200 rounded px-1.5 py-0.5">↵</kbd>
                </form>

                <div class="relative" ref="bellEl">
                    <button class="btn-icon btn-outline relative" @click="bellOpen = !bellOpen" title="Alerts">
                        <Icon name="bell" />
                        <span v-if="urgent" class="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-red-500 text-white text-[10px] font-bold grid place-items-center ring-2 ring-ink-50">{{ urgent }}</span>
                    </button>
                    <Transition enter-from-class="opacity-0 -translate-y-1" leave-to-class="opacity-0 -translate-y-1" enter-active-class="transition duration-150" leave-active-class="transition duration-150">
                        <div v-if="bellOpen" class="absolute right-0 mt-2 w-[min(380px,calc(100vw-2rem))] card shadow-pop overflow-hidden">
                            <div class="flex items-center justify-between px-4 py-3 border-b border-ink-100">
                                <p class="font-bold text-sm">Alerts & notifications</p>
                                <span class="chip bg-ink-100 text-ink-600">{{ store.alerts.length }}</span>
                            </div>
                            <div class="max-h-[420px] overflow-y-auto scrollbar-thin">
                                <AlertItem v-for="a in store.alerts" :key="a.id" :alert="a" @click="bellOpen = false" />
                                <p v-if="!store.alerts.length" class="p-6 text-center text-sm muted">All clear. No alerts. 🍌</p>
                            </div>
                        </div>
                    </Transition>
                </div>

                <RouterLink v-if="can.warehouse()" :to="{ name: 'receiving', query: { new: 1 } }" class="btn-primary hidden sm:inline-flex">
                    <Icon name="plus" :size="16" :stroke="2.4" /> Receive batch
                </RouterLink>
                <RouterLink v-else-if="can.sales()" :to="{ name: 'sales.new' }" class="btn-primary hidden sm:inline-flex">
                    <Icon name="plus" :size="16" :stroke="2.4" /> New order
                </RouterLink>
            </div>
        </header>

        <main class="px-4 sm:px-6 lg:px-8 py-6 max-w-[1500px] mx-auto">
            <RouterView v-slot="{ Component }">
                <component :is="Component" />
            </RouterView>
        </main>

        <BatchDrawer />
    </div>
</template>

<script setup>
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import AlertItem from '../components/AlertItem.vue';
import { api } from '../lib/api';
import { can, hasRole, openBatch, refreshAlerts, store, toast, loadMeta } from '../lib/store';

const BatchDrawer = defineAsyncComponent(() => import('../components/BatchDrawer.vue'));

const route = useRoute();
const router = useRouter();
const q = ref('');
const bellOpen = ref(false);
const bellEl = ref();

const urgent = computed(() => store.alerts.filter((a) => a.level === 'danger' || a.level === 'warning' || a.level === 'success').length);
const ripeReady = computed(() => store.alerts.filter((a) => a.action === 'move_to_sales').length);
const inspect = computed(() => store.alerts.filter((a) => a.action === 'inspect').length);

const nav = computed(() =>
    [
        {
            label: 'Overview',
            items: [
                { to: '/', label: 'Dashboard', icon: 'dashboard', exact: true },
                { to: '/reports', label: 'Reports & Analytics', icon: 'chart', roles: [] },
            ],
        },
        {
            label: 'Operations',
            items: [
                { to: '/receiving', label: 'Receiving', icon: 'truck', roles: ['warehouse', 'inspector'], badge: inspect.value },
                { to: '/ripening', label: 'Ripening Rooms', icon: 'flame', roles: ['warehouse', 'inspector'], badge: ripeReady.value },
                { to: '/inventory', label: 'Inventory', icon: 'boxes' },
                { to: '/batches', label: 'All Batches', icon: 'layers' },
            ],
        },
        {
            label: 'Sales',
            items: [
                { to: '/sales', label: 'Sales Orders', icon: 'cart', roles: ['sales'] },
                { to: '/pricing', label: 'Pricing', icon: 'tag', roles: ['sales'] },
                { to: '/partners', label: 'Suppliers & Customers', icon: 'handshake' },
            ],
        },
        { label: 'Admin', items: [{ to: '/users', label: 'Users & Audit Log', icon: 'shield', roles: [] }] },
    ]
        .map((s) => ({ ...s, items: s.items.filter((i) => hasRole(i.roles)) }))
        .filter((s) => s.items.length),
);

const flow = [
    { label: 'Receive', color: '#38bdf8' },
    { label: 'Pre-ripe', color: '#2dd4bf' },
    { label: 'Ripen', color: '#fb923c' },
    { label: 'Sell', color: '#facc15' },
    { label: 'Ship', color: '#a8a495' },
];

const isActive = (item) => (item.exact ? route.path === item.to : route.path.startsWith(item.to));

async function findBatch() {
    const code = q.value.trim();
    if (!code) return;
    const res = await api.get('batches', { search: code, per_page: 1 });
    if (res.data[0]) {
        openBatch(res.data[0].id);
        q.value = '';
    } else toast(`No batch matches “${code}”`, 'error');
}

async function logout() {
    await api.post('logout');
    store.user = null;
    router.replace({ name: 'login' });
}

const closeBell = (e) => bellEl.value && !bellEl.value.contains(e.target) && (bellOpen.value = false);
let timer;
onMounted(() => {
    refreshAlerts();
    loadMeta();
    timer = setInterval(refreshAlerts, 60000);
    document.addEventListener('click', closeBell);
});
onBeforeUnmount(() => {
    clearInterval(timer);
    document.removeEventListener('click', closeBell);
});
</script>
