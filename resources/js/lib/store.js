import { reactive } from 'vue';
import { api } from './api';

const boot = window.__APP__ || {};

export const store = reactive({
    user: boot.user || null,
    currency: boot.currency || 'USD',
    meta: null,
    alerts: [],
    toasts: [],
    batchId: null, // global batch drawer
    sidebarOpen: false,
});

export const can = {
    // mirrors the backend role middleware
    warehouse: () => ['manager', 'warehouse', 'inspector'].includes(store.user?.role),
    sales: () => ['manager', 'sales'].includes(store.user?.role),
    manage: () => store.user?.role === 'manager',
};

export function hasRole(roles) {
    return !roles || store.user?.role === 'manager' || roles.includes(store.user?.role);
}

let toastId = 0;
export function toast(message, type = 'success') {
    const id = ++toastId;
    store.toasts.push({ id, message, type });
    setTimeout(() => (store.toasts = store.toasts.filter((t) => t.id !== id)), 3800);
}

export async function loadMeta(force = false) {
    if (store.meta && !force) return store.meta;
    store.meta = await api.get('meta');
    return store.meta;
}

export async function refreshAlerts() {
    try {
        store.alerts = await api.get('alerts');
    } catch {}
}

export function openBatch(id) {
    store.batchId = id;
}

// Simple event bus so pages can refresh after drawer/modals mutate data
const listeners = new Set();
export const onDataChanged = (fn) => (listeners.add(fn), () => listeners.delete(fn));
export const dataChanged = () => {
    listeners.forEach((fn) => fn());
    refreshAlerts();
};
