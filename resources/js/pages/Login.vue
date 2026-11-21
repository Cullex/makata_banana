<template>
    <div class="min-h-dvh grid lg:grid-cols-[1.1fr_1fr] bg-white">
        <!-- Brand panel -->
        <section class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-brand-400 p-12 text-ink-950">
            <div class="absolute -right-24 -top-24 size-[420px] rounded-full bg-brand-300/70" />
            <div class="absolute -left-32 bottom-[-180px] size-[520px] rounded-full bg-brand-500/30" />
            <svg class="absolute right-10 top-1/2 -translate-y-1/2 w-[46%] max-w-[420px] drop-shadow-2xl" viewBox="0 0 400 400" aria-hidden="true">
                <g transform="rotate(-18 200 200)">
                    <path d="M70 250c40 70 170 90 250 10 20-20 30-60 20-90-10 40-50 80-120 90-60 8-110-5-150-10z" fill="#fff7c2" />
                    <path d="M70 250c40 70 170 90 250 10 20-20 30-60 20-90-10 40-50 80-120 90-60 8-110-5-150-10z" fill="none" stroke="#1a1916" stroke-width="7" stroke-linejoin="round" />
                    <path d="M60 220c45 55 175 70 255-5 18-18 28-55 22-85-15 45-60 75-125 82-60 6-105-2-152 8z" fill="#fde047" stroke="#1a1916" stroke-width="7" stroke-linejoin="round" />
                    <path d="M337 130c4-14 2-30-6-40l14-8c10 14 12 34 6 50z" fill="#65a30d" stroke="#1a1916" stroke-width="6" stroke-linejoin="round" />
                    <path d="M120 248c50 14 120 8 170-30" fill="none" stroke="#ca8a04" stroke-width="5" stroke-linecap="round" opacity=".6" />
                    <circle cx="58" cy="226" r="9" fill="#713f12" />
                </g>
            </svg>

            <div class="relative flex items-center gap-3">
                <span class="grid place-items-center size-11 rounded-xl bg-ink-950 text-brand-400"><Icon name="banana" :size="24" :stroke="2" /></span>
                <span class="font-extrabold text-lg tracking-tight">Makata Banana</span>
            </div>

            <div class="relative max-w-md">
                <p class="chip bg-ink-950/10 text-ink-900 mb-5">Warehouse Management System</p>
                <h1 class="text-[44px] leading-[1.05] font-extrabold tracking-tight">From green to golden, <br />every batch tracked.</h1>
                <p class="mt-5 text-ink-800/80 text-[15px] leading-relaxed max-w-sm">
                    Receive, ripen and sell bananas with full batch traceability, live ripening-room control and FIFO-driven sales.
                </p>
                <div class="mt-10 flex items-center gap-2">
                    <template v-for="(s, i) in ['Receive', 'Pre-ripen', 'Ripen', 'Sell', 'Ship']" :key="s">
                        <span class="chip bg-white/70 text-ink-900 h-7">{{ s }}</span>
                        <Icon v-if="i < 4" name="chevronRight" :size="14" class="text-ink-900/40" />
                    </template>
                </div>
            </div>
            <p class="relative text-xs text-ink-900/60">© {{ new Date().getFullYear() }} Makata Banana · Demo environment</p>
        </section>

        <!-- Form -->
        <section class="flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-sm animate-fade-up">
                <div class="lg:hidden flex items-center gap-3 mb-10">
                    <span class="grid place-items-center size-11 rounded-xl bg-brand-400 text-ink-950 shadow-brand"><Icon name="banana" :size="24" :stroke="2" /></span>
                    <span class="font-extrabold text-lg">Makata Banana</span>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight">Welcome back</h2>
                <p class="text-sm muted mt-1.5">Sign in to manage the warehouse.</p>

                <form class="mt-8 space-y-4" @submit.prevent="submit()">
                    <div>
                        <label class="label">Email</label>
                        <input v-model="form.email" type="email" class="input h-11" placeholder="you@makata.test" autocomplete="username" required />
                        <FieldError :msg="errors.email" />
                    </div>
                    <div>
                        <label class="label">Password</label>
                        <input v-model="form.password" type="password" class="input h-11" placeholder="••••••••" autocomplete="current-password" required />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-ink-600">
                        <input v-model="form.remember" type="checkbox" class="size-4 rounded accent-brand-500" /> Keep me signed in
                    </label>
                    <button class="btn-primary w-full h-11" :disabled="loading">
                        <span v-if="loading" class="size-4 border-2 border-ink-900/30 border-t-ink-900 rounded-full animate-spin" />
                        Sign in
                    </button>
                </form>

                <div class="mt-10">
                    <div class="flex items-center gap-3 text-[11px] font-semibold uppercase tracking-wider text-ink-400">
                        <span class="h-px flex-1 bg-ink-200" /> Demo accounts <span class="h-px flex-1 bg-ink-200" />
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button
                            v-for="d in demos" :key="d.email" type="button" :disabled="loading"
                            class="group text-left rounded-xl border border-ink-200 p-3 hover:border-brand-400 hover:bg-brand-50 transition"
                            @click="submit(d.email)"
                        >
                            <span class="flex items-center gap-2">
                                <span class="grid place-items-center size-7 rounded-lg bg-ink-100 text-ink-600 group-hover:bg-brand-400 group-hover:text-ink-900 transition"><Icon :name="d.icon" :size="15" /></span>
                                <span class="text-[13px] font-bold">{{ d.role }}</span>
                            </span>
                            <span class="block text-[11px] muted mt-1.5 leading-snug">{{ d.desc }}</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-center muted mt-4">All demo passwords: <code class="font-semibold text-ink-700">password</code></p>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import FieldError from '../components/FieldError.vue';
import { api } from '../lib/api';
import { store, toast } from '../lib/store';

const router = useRouter();
const route = useRoute();
const form = reactive({ email: '', password: '', remember: true });
const errors = ref({});
const loading = ref(false);

const demos = [
    { role: 'Manager', email: 'manager@makata.test', icon: 'chart', desc: 'Full access, reports & users' },
    { role: 'Warehouse', email: 'warehouse@makata.test', icon: 'truck', desc: 'Receiving & ripening rooms' },
    { role: 'Inspector', email: 'inspector@makata.test', icon: 'clipboard', desc: 'Quality checks & stages' },
    { role: 'Sales', email: 'sales@makata.test', icon: 'cart', desc: 'Orders, pricing & customers' },
];

async function submit(email) {
    if (email) Object.assign(form, { email, password: 'password' });
    loading.value = true;
    errors.value = {};
    try {
        const { user } = await api.post('login', form);
        store.user = user;
        toast(`Welcome, ${user.name.split(' ')[0]} 👋`);
        router.replace(route.query.next || '/');
    } catch (e) {
        errors.value = e.errors || { email: e.message };
    } finally {
        loading.value = false;
    }
}
</script>
