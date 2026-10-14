<script setup lang="ts">
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bike,
    ClipboardList,
    PackageOpen,
    Search,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import OrderDetailsDialog from '@/components/OrderDetailsDialog.vue';
import CourierManagerDialog from '@/components/CourierManagerDialog.vue';
import DispatchOrderDialog from '@/components/DispatchOrderDialog.vue';
import { Button } from '@/components/ui/button';
import {
    advance as advanceOrder,
    create as createOrder,
    history as manageOrders,
    manage as activeOrders,
} from '@/routes/orders';
import type { Team } from '@/types';
import type { Courier, OrderDetails, OrderStatus } from '@/types/orders';

type StatusFilter = OrderStatus | 'todos';
type PeriodFilter = 'today' | '7d' | '30d' | 'custom' | 'all';

const props = defineProps<{
    orders: {
        data: OrderDetails[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
    filters: {
        status: StatusFilter;
        q: string;
        period: PeriodFilter;
        dateFrom: string | null;
        dateTo: string | null;
    };
    statusCounts: Record<StatusFilter, number>;
    couriers: Courier[];
    currentTeam?: Team | null;
}>();

const page = usePage();
const selectedOrder = ref<OrderDetails | null>(null);
const dispatchingOrder = ref<OrderDetails | null>(null);
const courierManagerOpen = ref(false);
const searchTerm = ref(props.filters.q);
const dateFrom = ref(props.filters.dateFrom ?? '');
const dateTo = ref(props.filters.dateTo ?? '');
const advancingOrderId = ref<number | null>(null);
const advanceError = ref('');
const advanceForm = useHttp<{ status: OrderStatus }>({ status: 'em_preparo' });
const currentTeam = computed(
    () => props.currentTeam ?? (page.props.currentTeam as Team | null),
);
const manageUrl = computed(() =>
    currentTeam.value ? manageOrders(currentTeam.value.slug).url : '/',
);
const activeOrdersUrl = computed(() =>
    currentTeam.value ? activeOrders(currentTeam.value.slug).url : '/',
);
const createUrl = computed(() =>
    currentTeam.value ? createOrder(currentTeam.value.slug).url : '/',
);
const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const statusTabs: { value: StatusFilter; label: string }[] = [
    { value: 'todos', label: 'Todos' },
    { value: 'novo', label: 'Novos' },
    { value: 'em_preparo', label: 'Em preparo' },
    { value: 'pronto', label: 'Prontos' },
    { value: 'finalizado', label: 'Finalizados' },
];
const periodTabs: { value: PeriodFilter; label: string }[] = [
    { value: 'today', label: 'Hoje' },
    { value: '7d', label: '7 dias' },
    { value: '30d', label: '30 dias' },
    { value: 'all', label: 'Todo o período' },
];

const statusClasses: Record<OrderStatus, string> = {
    novo: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    em_preparo:
        'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    pronto: 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
    finalizado: 'bg-muted text-muted-foreground',
};

const nextStatusLabels: Record<OrderStatus, string> = {
    novo: 'Iniciar preparo',
    em_preparo: 'Marcar pronto',
    pronto: 'Finalizar pedido',
    finalizado: '',
};

watch(
    () => props.filters.q,
    (query) => {
        searchTerm.value = query;
    },
);
watch(
    () => [props.filters.dateFrom, props.filters.dateTo],
    ([from, to]) => {
        dateFrom.value = from ?? '';
        dateTo.value = to ?? '';
    },
);

function visitOrders(
    status: StatusFilter,
    query: string,
    pageNumber = 1,
    period: PeriodFilter = props.filters.period,
): void {
    selectedOrder.value = null;
    advanceError.value = '';
    router.get(
        manageUrl.value,
        {
            status,
            q: query.trim(),
            ...(status === 'finalizado'
                ? {
                      period,
                      ...(period === 'custom'
                          ? { date_from: dateFrom.value, date_to: dateTo.value }
                          : {}),
                  }
                : {}),
            ...(pageNumber > 1 ? { page: pageNumber } : {}),
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function formatOrderDate(value: string | null): string {
    if (!value) {
        return 'Sem data';
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function advance(order: OrderDetails): void {
    if (!order.nextStatus || advanceForm.processing) {
        return;
    }

    advancingOrderId.value = order.id;
    advanceError.value = '';
    advanceForm.status = order.nextStatus;
    advanceForm.patch(advanceOrder(order.id).url, {
        onSuccess: () => router.reload({ only: ['orders', 'statusCounts'] }),
        onError: (errors) => {
            advanceError.value = String(
                errors.status ?? 'Não foi possível atualizar o pedido.',
            );
        },
        onFinish: () => {
            advancingOrderId.value = null;
        },
    });
}

defineOptions({
    layout: () => ({ breadcrumbs: [], showCreateOrder: true }),
});
</script>

<template>
    <Head title="Pedidos" />

    <OrderDetailsDialog :order="selectedOrder" @close="selectedOrder = null" />
    <CourierManagerDialog
        v-model:open="courierManagerOpen"
        :couriers="couriers"
    />
    <DispatchOrderDialog
        :order="dispatchingOrder"
        :couriers="couriers"
        @close="dispatchingOrder = null"
    />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 xl:p-8"
    >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Histórico de pedidos
                </h1>
                <p class="text-sm text-muted-foreground">
                    Consulte pedidos anteriores, pesquise clientes e filtre por
                    período ou situação.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" class="gap-2 rounded-xl">
                    <Link :href="activeOrdersUrl">
                        <ClipboardList class="size-4" />
                        Pedidos ativos
                    </Link>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="gap-2 rounded-xl"
                    @click="courierManagerOpen = true"
                >
                    <Bike class="size-4" />
                    Motoboys
                </Button>
            </div>
        </header>

        <section
            class="rounded-2xl border bg-card text-card-foreground shadow-sm"
        >
            <div class="space-y-4 border-b p-4 sm:p-5">
                <nav
                    aria-label="Filtrar pedidos por etapa"
                    class="flex gap-2 overflow-x-auto pb-1"
                >
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        type="button"
                        class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border px-3 text-sm font-medium transition-colors"
                        :class="
                            filters.status === tab.value
                                ? 'border-primary/30 bg-primary/10 text-primary'
                                : 'border-transparent text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        :aria-pressed="filters.status === tab.value"
                        @click="
                            visitOrders(
                                tab.value,
                                searchTerm,
                                1,
                                tab.value === 'finalizado'
                                    ? 'today'
                                    : filters.period,
                            )
                        "
                    >
                        {{ tab.label }}
                        <span
                            class="rounded-full bg-background/70 px-2 py-0.5 text-xs tabular-nums"
                        >
                            {{ statusCounts[tab.value] }}
                        </span>
                    </button>
                </nav>

                <div v-if="filters.status === 'finalizado'" class="space-y-3">
                    <p class="text-sm font-medium">Data de finalização</p>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="period in periodTabs"
                            :key="period.value"
                            type="button"
                            size="sm"
                            :variant="
                                filters.period === period.value
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="
                                visitOrders(
                                    'finalizado',
                                    searchTerm,
                                    1,
                                    period.value,
                                )
                            "
                        >
                            {{ period.label }}
                        </Button>
                    </div>
                    <form
                        class="flex flex-wrap items-end gap-2"
                        @submit.prevent="
                            visitOrders('finalizado', searchTerm, 1, 'custom')
                        "
                    >
                        <label class="grid gap-1 text-xs text-muted-foreground">
                            De
                            <input
                                v-model="dateFrom"
                                type="date"
                                :max="dateTo || undefined"
                                class="h-9 rounded-lg border border-input bg-background px-3 text-sm text-foreground"
                            />
                        </label>
                        <label class="grid gap-1 text-xs text-muted-foreground">
                            Até
                            <input
                                v-model="dateTo"
                                type="date"
                                :min="dateFrom || undefined"
                                class="h-9 rounded-lg border border-input bg-background px-3 text-sm text-foreground"
                            />
                        </label>
                        <Button type="submit" size="sm" variant="outline">
                            Aplicar datas
                        </Button>
                    </form>
                </div>

                <form
                    class="flex flex-wrap gap-2"
                    role="search"
                    @submit.prevent="visitOrders(filters.status, searchTerm)"
                >
                    <div class="relative min-w-52 flex-1 sm:max-w-md">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="searchTerm"
                            type="search"
                            aria-label="Buscar pedido"
                            placeholder="Número, nome ou telefone"
                            class="h-10 w-full rounded-xl border border-input bg-background pr-3 pl-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        />
                    </div>
                    <Button type="submit" variant="outline" class="rounded-xl"
                        >Buscar</Button
                    >
                    <Button
                        v-if="filters.q"
                        type="button"
                        variant="ghost"
                        class="rounded-xl"
                        @click="
                            searchTerm = '';
                            visitOrders(filters.status, '');
                        "
                    >
                        Limpar
                    </Button>
                </form>
            </div>

            <p
                v-if="advanceError"
                role="alert"
                class="mx-4 mt-4 rounded-xl bg-destructive/10 p-3 text-sm text-destructive sm:mx-5"
            >
                {{ advanceError }}
            </p>

            <div
                v-if="orders.data.length"
                class="grid gap-3 p-4 sm:p-5 lg:grid-cols-2"
            >
                <article
                    v-for="order in orders.data"
                    :key="order.id"
                    class="flex flex-col gap-4 rounded-xl border bg-background/40 p-4 transition-colors hover:border-primary/30"
                >
                    <div class="flex items-start justify-between gap-3">
                        <button
                            type="button"
                            class="min-w-0 text-left focus-visible:rounded-md focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                            :aria-label="`Ver detalhes do pedido número ${order.id}`"
                            @click="selectedOrder = order"
                        >
                            <span class="flex items-center gap-2 font-semibold">
                                <ClipboardList class="size-4 text-primary" />
                                Pedido #{{ order.id }}
                            </span>
                            <span
                                class="mt-1 block text-sm text-muted-foreground"
                            >
                                {{
                                    order.customerName ||
                                    'Cliente não informado'
                                }}
                                · {{ order.modalityLabel }}
                            </span>
                        </button>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="statusClasses[order.status]"
                        >
                            {{ order.statusLabel }}
                        </span>
                    </div>

                    <div
                        v-if="order.courier"
                        class="flex items-center gap-2 rounded-lg bg-primary/5 px-3 py-2 text-sm"
                    >
                        <Bike class="size-4 text-primary" />
                        <span>
                            Motoboy:
                            <strong>{{ order.courier.name }}</strong>
                        </span>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-2 text-sm"
                    >
                        <span class="text-muted-foreground">
                            {{
                                formatOrderDate(
                                    order.status === 'finalizado'
                                        ? order.finalizedAt
                                        : order.createdAt,
                                )
                            }}
                            ·
                            {{ order.items.length }}
                            {{ order.items.length === 1 ? 'item' : 'itens' }}
                        </span>
                        <span class="font-semibold tabular-nums">{{
                            currency.format(Number(order.total))
                        }}</span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-2 border-t pt-3"
                    >
                        <button
                            type="button"
                            class="text-sm font-medium text-primary hover:underline"
                            @click="selectedOrder = order"
                        >
                            Ver detalhes
                        </button>
                        <div class="flex flex-wrap justify-end gap-2">
                            <Button
                                v-if="
                                    (order.status === 'em_preparo' ||
                                        order.status === 'pronto') &&
                                    order.modality === 'entrega'
                                "
                                type="button"
                                size="sm"
                                :variant="order.courier ? 'outline' : 'default'"
                                class="gap-2 rounded-lg"
                                @click="dispatchingOrder = order"
                            >
                                <Bike class="size-4" />
                                {{
                                    order.courier
                                        ? 'Trocar motoboy'
                                        : 'Selecionar motoboy'
                                }}
                            </Button>
                            <Button
                                v-if="
                                    order.nextStatus &&
                                    (order.status === 'novo' ||
                                        order.modality !== 'entrega' ||
                                        order.courier)
                                "
                                type="button"
                                size="sm"
                                class="gap-2 rounded-lg"
                                :disabled="advanceForm.processing"
                                @click="advance(order)"
                            >
                                {{
                                    advancingOrderId === order.id
                                        ? 'Atualizando...'
                                        : nextStatusLabels[order.status]
                                }}
                                <ArrowRight
                                    v-if="advancingOrderId !== order.id"
                                    class="size-4"
                                />
                            </Button>
                            <span
                                v-if="!order.nextStatus"
                                class="self-center text-xs text-muted-foreground"
                                >Concluído</span
                            >
                        </div>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="flex min-h-72 flex-col items-center justify-center p-8 text-center"
            >
                <span
                    class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary"
                >
                    <PackageOpen class="size-6" />
                </span>
                <h2 class="mt-4 font-semibold">Nenhum pedido encontrado</h2>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    {{
                        filters.q
                            ? 'Tente outro número, nome ou telefone.'
                            : filters.status === 'finalizado'
                              ? 'Escolha outro período para consultar os pedidos finalizados.'
                              : 'Os pedidos desta etapa aparecerão aqui.'
                    }}
                </p>
                <Link
                    v-if="statusCounts.todos === 0"
                    :href="createUrl"
                    class="mt-4 text-sm font-medium text-primary hover:underline"
                >
                    Criar primeiro pedido
                </Link>
            </div>

            <footer
                v-if="orders.lastPage > 1"
                class="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm sm:px-5"
            >
                <span class="text-muted-foreground">
                    Página {{ orders.currentPage }} de {{ orders.lastPage }} ·
                    {{ orders.total }} pedidos
                </span>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="orders.currentPage <= 1"
                        @click="
                            visitOrders(
                                filters.status,
                                filters.q,
                                orders.currentPage - 1,
                            )
                        "
                    >
                        Anterior
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="orders.currentPage >= orders.lastPage"
                        @click="
                            visitOrders(
                                filters.status,
                                filters.q,
                                orders.currentPage + 1,
                            )
                        "
                    >
                        Próxima
                    </Button>
                </div>
            </footer>
        </section>
    </div>
</template>
