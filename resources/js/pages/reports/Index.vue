<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarDays,
    ChartNoAxesCombined,
    PackageCheck,
    ReceiptText,
    Truck,
    UserPlus,
    WalletCards,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CourierReportDialog from '@/components/reports/CourierReportDialog.vue';
import MetricCard from '@/components/workspace/MetricCard.vue';
import PageHeader from '@/components/workspace/PageHeader.vue';
import { index as reportsIndex } from '@/routes/reports';
import type { Team } from '@/types';
import type { CourierReport } from '@/types/reports';

type Period = 'today' | '7d' | '30d' | 'custom';
type Breakdown = {
    value: string;
    label: string;
    orders: number;
    total: string;
};

const props = defineProps<{
    report: {
        summary: {
            revenue: string;
            orders: number;
            averageTicket: string;
            deliveryFees: string;
            newCustomers: number;
        };
        dailySales: {
            date: string;
            label: string;
            orders: number;
            revenue: string;
        }[];
        payments: Breakdown[];
        modalities: Breakdown[];
        topProducts: { name: string; quantity: number; total: string }[];
        couriers: CourierReport;
    };
    filters: {
        period: Period;
        dateTimeFrom: string;
        dateTimeTo: string;
    };
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const pageUrl = computed(() =>
    currentTeam.value ? reportsIndex(currentTeam.value.slug).url : '/',
);
const selectedPeriod = ref<Period>(props.filters.period);
const dateTimeFrom = ref(props.filters.dateTimeFrom);
const dateTimeTo = ref(props.filters.dateTimeTo);
const customPeriodError = ref('');
const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});
const periodOptions: { value: Period; label: string }[] = [
    { value: 'today', label: 'Hoje' },
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
    { value: 'custom', label: 'Personalizado' },
];
const maxDailyRevenue = computed(() =>
    Math.max(...props.report.dailySales.map((day) => Number(day.revenue)), 1),
);
const maxProductQuantity = computed(() =>
    Math.max(...props.report.topProducts.map((product) => product.quantity), 1),
);
const revenue = computed(() => Number(props.report.summary.revenue));

function money(value: string): string {
    return currency.format(Number(value));
}

function selectPeriod(period: Period): void {
    selectedPeriod.value = period;

    if (period === 'custom') {
        customPeriodError.value = '';
        return;
    }

    router.get(
        pageUrl.value,
        { period },
        { preserveState: true, replace: true },
    );
}

function applyCustomPeriod(): void {
    customPeriodError.value = '';

    if (!dateTimeFrom.value || !dateTimeTo.value) {
        customPeriodError.value = 'Informe o início e o fim do período.';
        return;
    }

    const from = new Date(dateTimeFrom.value);
    const to = new Date(dateTimeTo.value);

    if (from > to) {
        customPeriodError.value =
            'O fim do período precisa ser posterior ao início.';
        return;
    }

    if (to.getTime() - from.getTime() > 93 * 24 * 60 * 60 * 1000) {
        customPeriodError.value =
            'O período personalizado pode ter no máximo 93 dias.';
        return;
    }

    router.get(
        pageUrl.value,
        {
            period: 'custom',
            datetime_from: dateTimeFrom.value,
            datetime_to: dateTimeTo.value,
        },
        { preserveState: true, replace: true },
    );
}

function percentage(value: string): number {
    return revenue.value > 0 ? (Number(value) / revenue.value) * 100 : 0;
}

defineOptions({ layout: () => ({ breadcrumbs: [], showCreateOrder: true }) });
</script>

<template>
    <Head title="Relatórios" />

    <div class="workspace-page">
        <PageHeader
            title="Relatórios"
            eyebrow="Decisões com mais clareza"
            description="Um olhar sobre o que sua casa vende. Os resultados consideram as vendas finalizadas."
        >
            <CourierReportDialog
                :report="report.couriers"
                :from="filters.dateTimeFrom"
                :to="filters.dateTimeTo"
            />
            <div
                class="flex max-w-full gap-1 overflow-x-auto rounded-xl border bg-card p-1"
            >
                <button
                    v-for="option in periodOptions"
                    :key="option.value"
                    type="button"
                    class="h-9 shrink-0 rounded-lg px-3 text-sm font-medium transition"
                    :class="
                        selectedPeriod === option.value
                            ? 'bg-primary text-primary-foreground'
                            : 'border bg-background hover:bg-accent'
                    "
                    @click="selectPeriod(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </PageHeader>

        <form
            v-if="selectedPeriod === 'custom'"
            class="workspace-panel flex flex-wrap items-end gap-3 p-4"
            @submit.prevent="applyCustomPeriod"
        >
            <div class="grid gap-1.5">
                <label
                    for="report-from"
                    class="text-xs font-medium text-muted-foreground"
                    >Início</label
                >
                <input
                    id="report-from"
                    v-model="dateTimeFrom"
                    type="datetime-local"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    required
                />
            </div>
            <div class="grid gap-1.5">
                <label
                    for="report-to"
                    class="text-xs font-medium text-muted-foreground"
                    >Fim</label
                >
                <input
                    id="report-to"
                    v-model="dateTimeTo"
                    type="datetime-local"
                    :min="dateTimeFrom"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    required
                />
            </div>
            <button
                type="submit"
                class="h-10 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
            >
                Aplicar período
            </button>
            <p class="w-full text-xs text-muted-foreground">
                O período personalizado pode ter até 93 dias.
            </p>
            <p v-if="customPeriodError" class="w-full text-sm text-destructive">
                {{ customPeriodError }}
            </p>
        </form>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <MetricCard
                label="Faturamento"
                :value="money(report.summary.revenue)"
                detail="Vendas no período"
            >
                <template #icon><Banknote class="size-4" /></template>
            </MetricCard>
            <MetricCard
                label="Vendas finalizadas"
                :value="report.summary.orders"
                detail="Pedidos concluídos"
            >
                <template #icon><ReceiptText class="size-4" /></template>
            </MetricCard>
            <MetricCard
                label="Ticket médio"
                :value="money(report.summary.averageTicket)"
                tone="orange"
                detail="Valor médio por pedido"
            >
                <template #icon><WalletCards class="size-4" /></template>
            </MetricCard>
            <MetricCard
                label="Taxas de entrega"
                :value="money(report.summary.deliveryFees)"
                tone="amber"
                detail="Taxas das vendas finalizadas"
            >
                <template #icon><Truck class="size-4" /></template>
            </MetricCard>
            <MetricCard
                label="Novos clientes"
                :value="report.summary.newCustomers"
                tone="sky"
                detail="Cadastrados no período"
            >
                <template #icon><UserPlus class="size-4" /></template>
            </MetricCard>
        </section>

        <section class="workspace-panel p-5 sm:p-6">
            <div class="flex items-center gap-2">
                <CalendarDays class="size-5 text-primary" />
                <div>
                    <h2 class="font-semibold">Vendas por dia</h2>
                    <p class="text-sm text-muted-foreground">
                        Faturamento e quantidade de pedidos no período.
                    </p>
                </div>
            </div>
            <div
                v-if="report.dailySales.some((day) => day.orders > 0)"
                class="mt-6 overflow-x-auto pb-2"
            >
                <div class="flex h-64 min-w-max items-end gap-3 border-b px-1">
                    <div
                        v-for="day in report.dailySales"
                        :key="day.date"
                        class="flex w-14 flex-col items-center justify-end gap-2"
                    >
                        <div class="text-center text-[11px]">
                            <p class="font-medium">{{ money(day.revenue) }}</p>
                            <p class="text-muted-foreground">
                                {{ day.orders }} ped.
                            </p>
                        </div>
                        <div
                            class="w-8 rounded-t-lg bg-gradient-to-t from-primary to-[#6eaa75] transition-all hover:opacity-80"
                            :style="{
                                height: `${Math.max((Number(day.revenue) / maxDailyRevenue) * 150, day.orders ? 8 : 2)}px`,
                            }"
                        />
                        <span class="pb-2 text-xs text-muted-foreground">{{
                            day.label
                        }}</span>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="mt-5 flex min-h-44 flex-col items-center justify-center rounded-xl border border-dashed bg-muted/30 p-6 text-center"
            >
                <ChartNoAxesCombined class="size-7 text-primary/50" />
                <p class="mt-3 text-sm font-medium">
                    Ainda sem vendas neste período
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    As vendas finalizadas aparecerão neste gráfico.
                </p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <article class="workspace-panel p-5 sm:p-6">
                <h2 class="font-semibold">Vendas por pagamento</h2>
                <div class="mt-5 space-y-4">
                    <div
                        v-for="payment in report.payments"
                        :key="payment.value"
                    >
                        <div class="flex justify-between gap-3 text-sm">
                            <span
                                >{{ payment.label }} ·
                                {{ payment.orders }}</span
                            ><strong>{{ money(payment.total) }}</strong>
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{
                                    width: `${percentage(payment.total)}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </article>

            <article class="workspace-panel p-5 sm:p-6">
                <h2 class="font-semibold">Pedidos por modalidade</h2>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div
                        v-for="modality in report.modalities"
                        :key="modality.value"
                        class="rounded-xl border bg-background p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{ modality.label }}
                        </p>
                        <p class="mt-2 text-2xl font-semibold">
                            {{ modality.orders }}
                        </p>
                        <p class="mt-1 text-sm font-medium">
                            {{ money(modality.total) }}
                        </p>
                    </div>
                </div>
            </article>
        </section>

        <section class="workspace-panel p-5 sm:p-6">
            <div class="flex items-center gap-2">
                <PackageCheck class="size-5 text-primary" />
                <div>
                    <h2 class="font-semibold">Produtos mais vendidos</h2>
                    <p class="text-sm text-muted-foreground">
                        Ranking por quantidade vendida.
                    </p>
                </div>
            </div>
            <div v-if="report.topProducts.length" class="mt-5 space-y-4">
                <div
                    v-for="(product, index) in report.topProducts"
                    :key="product.name"
                    class="grid items-center gap-3 sm:grid-cols-[2rem_1fr_auto]"
                >
                    <span
                        class="grid size-8 place-items-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                        >{{ index + 1 }}</span
                    >
                    <div>
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="font-medium">{{ product.name }}</span
                            ><span class="text-muted-foreground"
                                >{{ product.quantity }} un.</span
                            >
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-emerald-500"
                                :style="{
                                    width: `${(product.quantity / maxProductQuantity) * 100}%`,
                                }"
                            />
                        </div>
                    </div>
                    <strong class="text-sm tabular-nums">{{
                        money(product.total)
                    }}</strong>
                </div>
            </div>
            <p
                v-else
                class="mt-5 rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Nenhuma venda finalizada neste período.
            </p>
        </section>
    </div>
</template>
