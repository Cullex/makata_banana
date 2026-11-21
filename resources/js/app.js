import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router';
import { setUnauthorizedHandler } from './lib/api';
import { store } from './lib/store';

setUnauthorizedHandler(() => {
    store.user = null;
    if (router.currentRoute.value.name !== 'login') router.replace({ name: 'login' });
});

const app = createApp(App).use(router);
router.isReady().then(() => {
    app.mount('#app');
    const s = document.getElementById('splash');
    if (s) {
        s.style.opacity = '0';
        setTimeout(() => s.remove(), 250);
    }
});
