<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BellRing,
    CheckCircle2,
    ChefHat,
    ClipboardList,
    PackageOpen,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import OrderDetailsDialog from '@/components/OrderDetailsDialog.vue';
import { dashboard } from '@/routes';
import { manage as manageOrders } from '@/routes/orders';
import type { DashboardInvitation, Team } from '@/types';
import type { OrderDetails, OrderStatus } from '@/types/orders';

const props = defineProps<{
    stats: {
        todayOrders: number;
        newOrders: number;
        inPreparation: number;
        ready: number;
        finalized: number;
    };
    recentOrders: OrderDetails[];
    products: { id: number; name: string; price: string }[];
    pendingInvitations?: DashboardInvitation[];
}>();

const page = usePage();
const selectedOrder = ref<OrderDetails | null>(null);
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const ordersUrl = computed(() => page.props.currentTeam
    ? manageOrders(page.props.currentTeam.slug).url
    : '/');
const summaryCards = computed(() => [
    {
        label: 'Pedidos hoje',
        value: props.stats.todayOrders,
        detail: 'registrados no dia',
        icon: ClipboardList,
        iconClass:
            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    },
    {
        label: 'Em preparo',
        value: props.stats.inPreparation,
        detail: 'na cozinha agora',
        icon: ChefHat,
        iconClass:
            'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    },
    {
        label: 'Prontos',
        value: props.stats.ready,
        detail: 'aguardando o cliente',
        icon: BellRing,
        iconClass:
            'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
    },
]);

const orderFlow = computed(() => [
    {
        label: 'Novos',
        detail: 'Aguardando preparo',
        value: props.stats.newOrders,
        markerClass: 'bg-emerald-500',
    },
    {
        label: 'Em preparo',
        detail: 'Na cozinha',
        value: props.stats.inPreparation,
        markerClass: 'bg-amber-500',
    },
    {
        label: 'Prontos',
        detail: 'Aguardando retirada',
        value: props.stats.ready,
        markerClass: 'bg-sky-500',
    },
    {
        label: 'Finalizados',
        detail: 'Concluídos hoje',
        value: props.stats.finalized,
        markerClass: 'bg-muted-foreground/50',
    },
]);

const statusClasses: Record<OrderStatus, string> = {
    novo: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    em_preparo:
        'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    pronto: 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
    finalizado: 'bg-muted text-muted-foreground',
};

const today = new Intl.DateTimeFormat('pt-BR', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
}).format(new Date());

defineOptions({
    layout: (props: {
        currentTeam?: Team | null;
        products: { id: number; name: string; price: string }[];
    }) => ({
        breadcrumbs: [
            {
                title: 'Visão geral',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
        products: props.products,
        showCreateOrder: true,
    }),
});
</script>

<template>
    <Head title="Visão geral" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <OrderDetailsDialog :order="selectedOrder" @close="selectedOrder = null" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 xl:p-8"
    >
        <section class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <p
                    class="text-sm font-medium text-emerald-700 capitalize dark:text-emerald-300"
                >
                    {{ today }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Olá, {{ firstName }} 👋
                </h1>
                <p class="text-sm text-muted-foreground">
                    Aqui está o movimento de hoje na sua lanchonete.
                </p>
            </div>
        </section>

        <section
            aria-label="Indicadores do dia"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="card in summaryCards"
                :key="card.label"
                class="rounded-xl border bg-card p-4 text-card-foreground shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="space-y-3">
                        <p class="text-sm text-muted-foreground">
                            {{ card.label }}
                        </p>
                        <p class="text-3xl font-semibold tabular-nums">
                            {{ card.value }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ card.detail }}
                        </p>
                    </div>
                    <span
                        class="grid size-10 place-items-center rounded-xl"
                        :class="card.iconClass"
                    >
                        <component :is="card.icon" class="size-5" />
                    </span>
                </div>
            </article>
        </section>

        <section
            class="grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(260px,0.85fr)]"
        >
            <article
                class="rounded-xl border bg-card text-card-foreground shadow-sm"
            >
                <header
                    class="flex items-center justify-between gap-3 border-b px-5 py-4"
                >
                    <div>
                        <h2 class="font-semibold">Pedidos recentes</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Os últimos pedidos registrados.
                        </p>
                    </div>
                    <Link :href="ordersUrl" class="text-sm font-medium text-primary hover:underline">
                        Ver todos
                    </Link>
                </header>

                <div v-if="recentOrders.length" class="space-y-3 p-3 sm:p-4">
                    <button
                        v-for="order in recentOrders"
                        :key="order.id"
                        type="button"
                        class="flex w-full flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-xl border border-border/70 bg-background/40 px-4 py-3 text-left transition-colors hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                        :aria-label="`Ver detalhes do pedido número ${order.id}`"
                        @click="selectedOrder = order"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-xl bg-muted text-muted-foreground"
                            >
                                <ClipboardList class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium">
                                    Pedido #{{ order.id }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ order.modalityLabel }}
                                    <span aria-hidden="true">·</span>
                                    {{ order.createdAt }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="statusClasses[order.status]"
                        >
                            {{ order.statusLabel }}
                        </span>
                    </button>
                </div>

                <div
                    v-else
                    class="flex min-h-60 flex-col items-center justify-center px-6 py-10 text-center"
                >
                    <span
                        class="grid size-12 place-items-center rounded-2xl bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        <PackageOpen class="size-6" />
                    </span>
                    <h3 class="mt-4 font-medium">
                        Nenhum pedido por aqui ainda
                    </h3>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                        Quando você registrar pedidos, o movimento mais recente
                        aparecerá nesta área.
                    </p>
                </div>
            </article>

            <article
                class="rounded-xl border bg-card text-card-foreground shadow-sm"
            >
                <header class="border-b px-5 py-4">
                    <h2 class="font-semibold">Fluxo dos pedidos</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Acompanhe cada etapa de hoje.
                    </p>
                </header>

                <div class="space-y-1 p-3">
                    <div
                        v-for="stage in orderFlow"
                        :key="stage.label"
                        class="flex items-center gap-3 rounded-lg px-2 py-3"
                    >
                        <span
                            class="size-2 shrink-0 rounded-full"
                            :class="stage.markerClass"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ stage.label }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ stage.detail }}
                            </p>
                        </div>
                        <span class="text-lg font-semibold tabular-nums">
                            {{ stage.value }}
                        </span>
                    </div>
                </div>

                <footer
                    class="mx-5 flex items-center gap-2 border-t py-4 text-sm text-muted-foreground"
                >
                    <CheckCircle2
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                    <span v-if="stats.todayOrders === 0">
                        Tudo tranquilo por enquanto.
                    </span>
                    <span v-else>
                        {{ stats.todayOrders }}
                        {{
                            stats.todayOrders === 1
                                ? 'pedido registrado hoje'
                                : 'pedidos registrados hoje'
                        }}.
                    </span>
                </footer>
            </article>
        </section>
    </div>
</template>
