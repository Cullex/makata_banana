<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight">Pricing management</h2>
            <p class="text-sm muted">Price per kg by ripeness and grade. Discounts apply automatically to overripe fruit and to batches with under 24h of shelf life (50% clearance).</p>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            <div v-for="stage in ['ripe', 'overripe']" :key="stage" class="card overflow-hidden">
                <div class="flex items-center gap-3 p-5 pb-4">
                    <svg viewBox="0 0 48 24" class="w-12 h-6"><path d="M4 8c6 10 28 14 40 0-4 14-34 16-40 0z" :fill="STAGES[stage].hex" stroke="#1a1916" stroke-width="1.6" /></svg>
                    <div>
                        <p class="section-title">{{ STAGES[stage].label }} bananas</p>
                        <p class="text-xs muted">{{ stage === 'ripe' ? 'Standard pricing, colour stage 5 to 6' : 'Discounted, sell first to avoid spoilage' }}</p>
                    </div>
                </div>
                <div class="divide-y divide-ink-100 border-t border-ink-100">
                    <div v-for="r in rules.filter((x) => x.stage === stage)" :key="r.id" class="flex items-center gap-4 px-5 py-4">
                        <span class="grid place-items-center size-10 rounded-xl bg-ink-100 font-extrabold">{{ r.grade }}</span>
                        <div class="flex-1 grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">Base price / kg</label>
                                <input v-model.number="r.price_per_kg" type="number" step="0.01" class="input h-9 tabular" :disabled="!can.manage()" />
                            </div>
                            <div>
                                <label class="label">Discount %</label>
                                <input v-model.number="r.discount_pct" type="number" step="1" min="0" max="90" class="input h-9 tabular" :disabled="!can.manage()" />
                            </div>
                        </div>
                        <div class="text-right w-28">
                            <p class="text-[11px] muted">Effective</p>
                            <p class="text-lg font-extrabold tabular">{{ money(eff(r)) }}</p>
                        </div>
                        <button v-if="can.manage()" class="btn-outline btn-sm" :disabled="saving === r.id" @click="save(r)">Save</button>
                    </div>
                </div>
            </div>
        </div>
        <p v-if="!can.manage()" class="text-xs muted -mt-2"><Icon name="info" :size="12" class="inline -mt-0.5" /> Only managers can change prices.</p>

        <div class="card overflow-hidden">
            <div class="p-5 pb-3"><p class="section-title">Live price list · sales stock</p><p class="text-xs muted">What each batch sells for right now, in FIFO order</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead><tr><th class="table-head">Batch</th><th class="table-head">Stage</th><th class="table-head">Grade</th><th class="table-head">Shelf life</th><th class="table-head text-right">Available</th><th class="table-head text-right">Base</th><th class="table-head text-right">Discount</th><th class="table-head text-right">Selling price</th></tr></thead>
                    <tbody>
                        <tr v-for="b in stock" :key="b.id" class="hover:bg-brand-50/50 cursor-pointer" @click="openBatch(b.id)">
                            <td class="table-cell font-mono font-bold">{{ b.code }}</td>
                            <td class="table-cell"><Chip :value="b.stage" /></td>
                            <td class="table-cell">{{ b.grade }}</td>
                            <td class="table-cell"><ShelfLife :days="b.shelf_days_left" /></td>
                            <td class="table-cell text-right tabular">{{ kg(b.remaining_kg) }}</td>
                            <td class="table-cell text-right tabular">{{ money(b.pricing.unit_price) }}</td>
                            <td class="table-cell text-right tabular">
                                <span v-if="b.pricing.discount_pct" class="chip" :class="b.pricing.discount_pct >= 50 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-800'">−{{ b.pricing.discount_pct }}%{{ b.pricing.discount_pct >= 50 ? ' clearance' : '' }}</span>
                                <span v-else class="text-ink-300">–</span>
                            </td>
                            <td class="table-cell text-right font-extrabold tabular">{{ money(b.pricing.unit_price * (1 - b.pricing.discount_pct / 100)) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import Icon from '../components/Icon.vue';
import Chip from '../components/Chip.vue';
import ShelfLife from '../components/ShelfLife.vue';
import { api } from '../lib/api';
import { kg, money, STAGES } from '../lib/format';
import { can, openBatch, toast } from '../lib/store';

const rules = ref([]);
const stock = ref([]);
const saving = ref(null);
const eff = (r) => Math.round(r.price_per_kg * (1 - r.discount_pct / 100) * 100) / 100;

async function load() {
    const d = await api.get('prices');
    rules.value = d.rules;
    stock.value = d.stock;
}
async function save(r) {
    saving.value = r.id;
    try {
        await api.patch(`prices/${r.id}`, { price_per_kg: r.price_per_kg, discount_pct: r.discount_pct });
        toast(`Grade ${r.grade} ${r.stage} price saved`);
        load();
    } catch (e) {
        toast(e.message, 'error');
    } finally {
        saving.value = null;
    }
}
onMounted(load);
</script>
