import { createRouter, createWebHistory } from 'vue-router';
import { store, hasRole } from './lib/store';

// Every page is a lazily loaded chunk → tiny initial bundle.
const routes = [
    { path: '/login', name: 'login', component: () => import('./pages/Login.vue'), meta: { guest: true } },
    {
        path: '/',
        component: () => import('./layouts/AppLayout.vue'),
        children: [
            { path: '', name: 'dashboard', component: () => import('./pages/Dashboard.vue'), meta: { title: 'Dashboard' } },
            { path: 'receiving', name: 'receiving', component: () => import('./pages/Receiving.vue'), meta: { title: 'Receiving', roles: ['warehouse', 'inspector'] } },
            { path: 'ripening', name: 'ripening', component: () => import('./pages/Ripening.vue'), meta: { title: 'Ripening Rooms', roles: ['warehouse', 'inspector'] } },
            { path: 'inventory', name: 'inventory', component: () => import('./pages/Inventory.vue'), meta: { title: 'Inventory' } },
            { path: 'batches', name: 'batches', component: () => import('./pages/Batches.vue'), meta: { title: 'All Batches' } },
            { path: 'sales', name: 'sales', component: () => import('./pages/Orders.vue'), meta: { title: 'Sales Orders', roles: ['sales'] } },
            { path: 'sales/new', name: 'sales.new', component: () => import('./pages/NewOrder.vue'), meta: { title: 'New Order', roles: ['sales'] } },
            { path: 'pricing', name: 'pricing', component: () => import('./pages/Pricing.vue'), meta: { title: 'Pricing', roles: ['sales'] } },
            { path: 'partners', name: 'partners', component: () => import('./pages/Partners.vue'), meta: { title: 'Suppliers & Customers' } },
            { path: 'reports', name: 'reports', component: () => import('./pages/Reports.vue'), meta: { title: 'Reports & Analytics', roles: [] } },
            { path: 'users', name: 'users', component: () => import('./pages/Users.vue'), meta: { title: 'Users & Audit Log', roles: [] } },
            { path: ':pathMatch(.*)*', name: 'notfound', component: () => import('./pages/NotFound.vue'), meta: { title: 'Not found' } },
        ],
    },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach((to) => {
    if (to.meta.guest) return store.user ? { name: 'dashboard' } : true;
    if (!store.user) return { name: 'login', query: to.fullPath !== '/' ? { next: to.fullPath } : {} };
    if (!hasRole(to.meta.roles)) return { name: 'dashboard' };
    return true;
});

router.afterEach((to) => {
    document.title = `${to.meta.title || 'Sign in'} · Makata Banana`;
    store.sidebarOpen = false;
});
