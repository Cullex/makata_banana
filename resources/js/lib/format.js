import { store } from './store';

const nf = new Intl.NumberFormat('en-US');
const nf1 = new Intl.NumberFormat('en-US', { maximumFractionDigits: 1 });

export const num = (n) => nf.format(Math.round(n || 0));
export const dec = (n) => nf1.format(n || 0);
export const kg = (n) => `${num(n)} kg`;
export const tonnes = (n) => (Math.abs(n) >= 1000 ? `${nf1.format(n / 1000)} t` : `${num(n)} kg`);
export const money = (n, compact = false) => {
    const v = Number(n || 0);
    const opts = { style: 'currency', currency: store.currency };
    if (compact && Math.abs(v) >= 1000) {
        return new Intl.NumberFormat('en-US', { ...opts, notation: 'compact', maximumFractionDigits: 1 }).format(v);
    }
    return new Intl.NumberFormat('en-US', { ...opts, minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
};
export const pct = (n, d = 1) => `${Number(n || 0).toFixed(d)}%`;

export const date = (d, opts = { day: 'numeric', month: 'short' }) => (d ? new Date(d).toLocaleDateString('en-GB', opts) : '—');
export const dateTime = (d) =>
    d ? new Date(d).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';
export const time = (d) => (d ? new Date(d).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : '—');

export function ago(d) {
    if (!d) return '—';
    const s = (Date.now() - new Date(d).getTime()) / 1000;
    const f = (v, u) => `${Math.round(v)}${u} ${s >= 0 ? 'ago' : ''}`.trim();
    const abs = Math.abs(s);
    const out = abs < 60 ? 'just now' : abs < 3600 ? f(abs / 60, 'm') : abs < 86400 ? f(abs / 3600, 'h') : f(abs / 86400, 'd');
    return s < 0 && abs >= 60 ? `in ${out}` : out;
}

export const STAGES = {
    green: { label: 'Green', color: 'var(--color-stage-green)', hex: '#65a30d', chip: 'bg-lime-100 text-lime-800' },
    turning: { label: 'Turning', color: 'var(--color-stage-turning)', hex: '#a3b818', chip: 'bg-[#f1f5c8] text-[#5b6a0a]' },
    ripe: { label: 'Ripe', color: 'var(--color-stage-ripe)', hex: '#f5c400', chip: 'bg-brand-200 text-brand-900' },
    overripe: { label: 'Overripe', color: 'var(--color-stage-overripe)', hex: '#9a5b13', chip: 'bg-amber-100 text-amber-900' },
};

export const STATUSES = {
    receiving: { label: 'Receiving', chip: 'bg-sky-100 text-sky-800' },
    pre_ripening: { label: 'Pre-ripening', chip: 'bg-teal-100 text-teal-800' },
    ripening: { label: 'Ripening', chip: 'bg-orange-100 text-orange-800' },
    sales: { label: 'Sales stock', chip: 'bg-brand-100 text-brand-800' },
    depleted: { label: 'Sold out', chip: 'bg-ink-100 text-ink-600' },
    spoiled: { label: 'Spoiled', chip: 'bg-red-100 text-red-700' },
};

export const ORDER_STATUSES = {
    allocated: { label: 'Allocated', chip: 'bg-sky-100 text-sky-800' },
    packed: { label: 'Packed', chip: 'bg-violet-100 text-violet-800' },
    dispatched: { label: 'Dispatched', chip: 'bg-orange-100 text-orange-800' },
    delivered: { label: 'Delivered', chip: 'bg-emerald-100 text-emerald-800' },
    cancelled: { label: 'Cancelled', chip: 'bg-red-100 text-red-700' },
};

export const ROOM_STATUSES = {
    active: { label: 'Holding', chip: 'bg-emerald-100 text-emerald-800', dot: 'bg-emerald-500' },
    gassing: { label: 'Gassing', chip: 'bg-brand-200 text-brand-900', dot: 'bg-brand-500' },
    venting: { label: 'Venting', chip: 'bg-sky-100 text-sky-800', dot: 'bg-sky-500' },
    idle: { label: 'Idle', chip: 'bg-ink-100 text-ink-600', dot: 'bg-ink-400' },
    maintenance: { label: 'Maintenance', chip: 'bg-red-100 text-red-700', dot: 'bg-red-500' },
};
