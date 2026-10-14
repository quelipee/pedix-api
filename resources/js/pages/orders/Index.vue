<script setup lang="ts">
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import {
    ArrowRight,
    Bike,
    ClipboardList,
    Clock3,
    CookingPot,
    PackageCheck,
    ScrollText,
    RefreshCw,
    UtensilsCrossed,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DispatchOrderDialog from '@/components/DispatchOrderDialog.vue';
import OrderDetailsDialog from '@/components/OrderDetailsDialog.vue';
import { Button } from '@/components/ui/button';
import {
    advance as advanceOrder,
    history as orderHistory,
} from '@/routes/orders';
import type { Team } from '@/types';
import type { Courier, OrderDetails, OrderStatus } from '@/types/orders';

type KitchenStatus = 'novo' | 'em_preparo' | 'pronto';

const props = defineProps<{
    columns: Record<KitchenStatus, OrderDetails[]>;
    counts: Record<KitchenStatus, number>;
    couriers: Courier[];
    refreshedAt: string;
}>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const historyUrl = computed(() =>
    currentTeam.value ? orderHistory(currentTeam.value.slug).url : '/',
);
const selectedOrder = ref<OrderDetails | null>(null);
const dispatchingOrder = ref<OrderDetails | null>(null);
const advancingOrderId = ref<number | null>(null);
const advanceError = ref('');
const refreshing = ref(false);
const now = ref(Date.now());
const advanceForm = useHttp<{ status: OrderStatus }>({ status: 'em_preparo' });

const columnDefinitions: {
    status: KitchenStatus;
    title: string;
    description: string;
    emptyMessage: string;
}[] = [
    {
        status: 'novo',
        title: 'Novos',
        description: 'Aguardando início',
        emptyMessage: 'Nenhum pedido aguardando preparo.',
    },
    {
        status: 'em_preparo',
        title: 'Em preparo',
        description: 'Na cozinha agora',
        emptyMessage: 'Nenhum pedido sendo preparado.',
    },
    {
        status: 'pronto',
        title: 'Prontos',
        description: 'Aguardando retirada',
        emptyMessage: 'Nenhum pedido pronto no momento.',
    },
];

const activeOrderCount = computed(() =>
    Object.values(props.counts).reduce((total, count) => total + count, 0),
);
const refreshedTime = computed(() =>
    new Intl.DateTimeFormat('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(new Date(props.refreshedAt)),
);

function refreshBoard(force = false): void {
    if (!force && (advanceForm.processing || dispatchingOrder.value !== null)) {
        return;
    }

    refreshing.value = true;
    router.reload({
        only: ['columns', 'counts', 'couriers', 'refreshedAt'],
        onFinish: () => {
            refreshing.value = false;
        },
    });
}

useIntervalFn(() => {
    if (
        typeof document === 'undefined' ||
        document.visibilityState === 'visible'
    ) {
        refreshBoard();
    }
}, 15000);

useIntervalFn(() => {
    now.value = Date.now();
}, 60000);

function minutesSince(value: string | null): number {
    if (!value) return 0;

    return Math.max(
        0,
        Math.floor((now.value - new Date(value).getTime()) / 60000),
    );
}

function waitingTime(value: string | null): string {
    const minutes = minutesSince(value);

    if (minutes < 1) return 'Agora';
    if (minutes < 60) return `${minutes} min`;

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    return remainingMinutes ? `${hours}h ${remainingMinutes}min` : `${hours}h`;
}

function waitingClass(order: OrderDetails): string {
    const minutes = minutesSince(order.createdAt);

    if (minutes >= 30) return 'bg-rose-500/15 text-rose-600 dark:text-rose-300';
    if (minutes >= 15)
        return 'bg-amber-500/15 text-amber-600 dark:text-amber-300';

    return 'bg-muted text-muted-foreground';
}

function nextStatus(order: OrderDetails): OrderStatus | null {
    if (order.status === 'novo') return 'em_preparo';
    if (order.status === 'em_preparo') return 'pronto';
    if (order.status === 'pronto') return 'finalizado';

    return null;
}

function advanceLabel(order: OrderDetails): string {
    if (order.status === 'novo') return 'Iniciar preparo';
    if (order.status === 'em_preparo') return 'Marcar pronto';

    return 'Finalizar pedido';
}

function advance(order: OrderDetails): void {
    const status = nextStatus(order);

    if (!status || advanceForm.processing) return;

    advancingOrderId.value = order.id;
    advanceError.value = '';
    advanceForm.status = status;
    advanceForm.patch(advanceOrder(order.id).url, {
        onSuccess: () => refreshBoard(true),
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

function canAdvance(order: OrderDetails): boolean {
    return !(
        (order.status === 'em_preparo' || order.status === 'pronto') &&
        order.modality === 'entrega' &&
        !order.courier
    );
}

function columnIcon(status: KitchenStatus) {
    return status === 'novo'
        ? UtensilsCrossed
        : status === 'em_preparo'
          ? CookingPot
          : PackageCheck;
}

defineOptions({
    layout: () => ({ breadcrumbs: [], showCreateOrder: true }),
});
</script>

<template>
    <Head title="Pedidos" />

    <OrderDetailsDialog :order="selectedOrder" @close="selectedOrder = null" />
    <DispatchOrderDialog
        :order="dispatchingOrder"
        :couriers="couriers"
        :reload-only="['columns', 'counts', 'couriers', 'refreshedAt']"
        @close="dispatchingOrder = null"
    />

    <div
        class="mx-auto flex w-full max-w-[1800px] flex-col gap-5 p-4 sm:p-6 xl:p-8"
    >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <ClipboardList class="size-7 text-primary" />
                    <h1
                        class="text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        Pedidos
                    </h1>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ activeOrderCount }} pedidos ativos · atualização
                    automática a cada 15 segundos · atualizado às
                    {{ refreshedTime }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" class="gap-2 rounded-xl">
                    <Link :href="historyUrl">
                        <ScrollText class="size-4" />
                        Histórico e busca
                    </Link>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="gap-2 rounded-xl"
                    :disabled="refreshing"
                    @click="refreshBoard"
                >
                    <RefreshCw
                        class="size-4"
                        :class="{ 'animate-spin': refreshing }"
                    />
                    Atualizar agora
                </Button>
            </div>
        </header>

        <p
            v-if="advanceError"
            role="alert"
            class="rounded-xl bg-destructive/10 p-3 text-sm text-destructive"
        >
            {{ advanceError }}
        </p>

        <main class="grid items-start gap-4 xl:grid-cols-3">
            <section
                v-for="column in columnDefinitions"
                :key="column.status"
                class="overflow-hidden rounded-2xl border bg-card shadow-sm"
            >
                <header
                    class="flex items-center justify-between gap-3 border-b p-4"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"
                        >
                            <component
                                :is="columnIcon(column.status)"
                                class="size-5"
                            />
                        </span>
                        <div>
                            <h2 class="font-semibold">{{ column.title }}</h2>
                            <p class="text-xs text-muted-foreground">
                                {{ column.description }}
                            </p>
                        </div>
                    </div>
                    <span
                        class="rounded-full bg-muted px-2.5 py-1 text-sm font-semibold tabular-nums"
                    >
                        {{ counts[column.status] }}
                    </span>
                </header>

                <div
                    class="max-h-[calc(100vh-14rem)] space-y-3 overflow-y-auto p-3"
                >
                    <article
                        v-for="order in columns[column.status]"
                        :key="order.id"
                        class="rounded-xl border bg-background p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <button
                                type="button"
                                class="text-left focus-visible:rounded-md focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                                @click="selectedOrder = order"
                            >
                                <span class="text-lg font-semibold">
                                    Pedido #{{ order.id }}
                                </span>
                                <span
                                    class="mt-0.5 block text-sm text-muted-foreground"
                                >
                                    {{ order.modalityLabel }}
                                    <template v-if="order.customerName">
                                        · {{ order.customerName }}
                                    </template>
                                </span>
                            </button>
                            <span
                                class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                                :class="waitingClass(order)"
                            >
                                <Clock3 class="size-3.5" />
                                {{ waitingTime(order.createdAt) }}
                            </span>
                        </div>

                        <div class="my-4 space-y-2 border-y py-3">
                            <div
                                v-for="item in order.items"
                                :key="item.id"
                                class="flex items-start gap-2 text-sm"
                            >
                                <span
                                    class="grid size-6 shrink-0 place-items-center rounded-md bg-primary/10 font-semibold text-primary"
                                >
                                    {{ item.quantity }}
                                </span>
                                <div class="min-w-0">
                                    <p class="font-medium">
                                        {{ item.productName }}
                                    </p>
                                    <p
                                        v-if="item.selectedOptions.length"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            item.selectedOptions
                                                .map((option) => option.name)
                                                .join(', ')
                                        }}
                                    </p>
                                    <p
                                        v-if="item.notes"
                                        class="mt-1 rounded-md bg-amber-500/10 px-2 py-1 text-xs font-medium text-amber-700 dark:text-amber-300"
                                    >
                                        Obs.: {{ item.notes }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="order.courier"
                            class="mb-3 flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Bike class="size-4 text-primary" />
                            Entrega com {{ order.courier.name }}
                        </div>

                        <div class="flex flex-wrap justify-between gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="selectedOrder = order"
                            >
                                Ver detalhes
                            </Button>

                            <div class="flex flex-wrap justify-end gap-2">
                                <Button
                                    v-if="
                                        order.status === 'em_preparo' &&
                                        order.modality === 'entrega'
                                    "
                                    type="button"
                                    size="sm"
                                    :variant="
                                        order.courier ? 'outline' : 'default'
                                    "
                                    class="gap-1.5"
                                    @click="dispatchingOrder = order"
                                >
                                    <Bike class="size-4" />
                                    {{ order.courier ? 'Trocar' : 'Motoboy' }}
                                </Button>
                                <Button
                                    v-if="nextStatus(order)"
                                    type="button"
                                    size="sm"
                                    class="gap-1.5"
                                    :disabled="
                                        advanceForm.processing ||
                                        !canAdvance(order)
                                    "
                                    @click="advance(order)"
                                >
                                    {{
                                        advancingOrderId === order.id
                                            ? 'Atualizando...'
                                            : advanceLabel(order)
                                    }}
                                    <ArrowRight
                                        v-if="advancingOrderId !== order.id"
                                        class="size-4"
                                    />
                                </Button>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="!columns[column.status].length"
                        class="grid min-h-40 place-items-center rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
                    >
                        {{ column.emptyMessage }}
                    </div>

                    <p
                        v-if="
                            counts[column.status] >
                            columns[column.status].length
                        "
                        class="py-2 text-center text-xs text-muted-foreground"
                    >
                        Exibindo os 50 pedidos mais antigos desta etapa.
                    </p>
                </div>
            </section>
        </main>
    </div>
</template>
