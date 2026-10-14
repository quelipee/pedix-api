<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    MapPin,
    Phone,
    ReceiptText,
    ShoppingBag,
    Trophy,
    WalletCards,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import OrderDetailsDialog from '@/components/OrderDetailsDialog.vue';
import {
    index as customersIndex,
    show as showCustomer,
} from '@/routes/customers';
import type { Customer, Team } from '@/types';
import type { OrderDetails } from '@/types/orders';

const props = defineProps<{
    customer: Customer;
    summary: {
        ordersCount: number;
        finalizedOrdersCount: number;
        totalSpent: string;
        popularProduct: { name: string; quantity: number } | null;
    };
    orders: {
        data: OrderDetails[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
}>();

const page = usePage();
const selectedOrder = ref<OrderDetails | null>(null);
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const indexUrl = computed(() =>
    currentTeam.value ? customersIndex(currentTeam.value.slug).url : '/',
);
const showUrl = computed(() =>
    currentTeam.value
        ? showCustomer({
              current_team: currentTeam.value.slug,
              customer: props.customer.id,
          }).url
        : '/',
);
const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});
const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
});
const address = computed(() =>
    props.customer.address
        ? `${props.customer.address.street}, ${props.customer.address.number}${props.customer.address.complement ? ` · ${props.customer.address.complement}` : ''} · ${props.customer.address.neighborhood} · ${props.customer.address.city}/${props.customer.address.state} · CEP ${props.customer.address.postalCode}`
        : 'Endereço não informado',
);

function visit(pageNumber: number): void {
    router.get(showUrl.value, { page: pageNumber }, { preserveState: true });
}

defineOptions({ layout: () => ({ breadcrumbs: [], showCreateOrder: true }) });
</script>

<template>
    <Head :title="customer.name" />
    <OrderDetailsDialog :order="selectedOrder" @close="selectedOrder = null" />

    <div
        class="mx-auto flex w-full max-w-[1400px] flex-col gap-6 p-4 sm:p-6 xl:p-8"
    >
        <header class="flex items-start gap-4">
            <Link
                :href="indexUrl"
                class="grid size-10 shrink-0 place-items-center rounded-xl border bg-background hover:bg-accent"
                aria-label="Voltar para clientes"
                ><ArrowLeft class="size-4"
            /></Link>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ customer.name }}
                </h1>
                <p
                    class="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <Phone class="size-4" />{{ customer.phone }}
                </p>
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border bg-card p-5 shadow-sm">
                <ReceiptText class="size-5 text-primary" />
                <p class="mt-4 text-sm text-muted-foreground">
                    Pedidos realizados
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ summary.ordersCount }}
                </p>
            </article>
            <article class="rounded-2xl border bg-card p-5 shadow-sm">
                <ShoppingBag class="size-5 text-primary" />
                <p class="mt-4 text-sm text-muted-foreground">
                    Compras finalizadas
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ summary.finalizedOrdersCount }}
                </p>
            </article>
            <article class="rounded-2xl border bg-card p-5 shadow-sm">
                <WalletCards class="size-5 text-primary" />
                <p class="mt-4 text-sm text-muted-foreground">Total gasto</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ currency.format(Number(summary.totalSpent)) }}
                </p>
            </article>
            <article class="rounded-2xl border bg-card p-5 shadow-sm">
                <Trophy class="size-5 text-primary" />
                <p class="mt-4 text-sm text-muted-foreground">
                    Produto mais pedido
                </p>
                <p class="mt-1 font-semibold">
                    {{
                        summary.popularProduct?.name ??
                        'Sem compras finalizadas'
                    }}
                </p>
                <p
                    v-if="summary.popularProduct"
                    class="text-sm text-muted-foreground"
                >
                    {{ summary.popularProduct.quantity }} unidades
                </p>
            </article>
        </section>

        <section class="rounded-2xl border bg-card p-5 shadow-sm">
            <h2 class="flex items-center gap-2 font-semibold">
                <MapPin class="size-4 text-primary" />Endereço cadastrado
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">{{ address }}</p>
        </section>

        <section class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <header class="border-b p-5">
                <h2 class="font-semibold">Histórico de compras</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Todos os pedidos vinculados a este cliente.
                </p>
            </header>
            <div v-if="orders.data.length" class="divide-y">
                <button
                    v-for="order in orders.data"
                    :key="order.id"
                    type="button"
                    class="grid w-full gap-3 p-4 text-left transition hover:bg-muted/40 sm:grid-cols-[1fr_auto_auto_auto] sm:items-center sm:p-5"
                    @click="selectedOrder = order"
                >
                    <div>
                        <p class="font-semibold">Pedido #{{ order.id }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{
                                dateFormatter.format(
                                    new Date(order.createdAt ?? ''),
                                )
                            }}
                        </p>
                    </div>
                    <span class="text-sm">{{ order.modalityLabel }}</span
                    ><span
                        class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium"
                        >{{ order.statusLabel }}</span
                    ><span class="font-semibold tabular-nums">{{
                        currency.format(Number(order.total))
                    }}</span>
                </button>
            </div>
            <div
                v-else
                class="grid min-h-40 place-items-center p-6 text-sm text-muted-foreground"
            >
                Este cliente ainda não possui pedidos vinculados.
            </div>
        </section>

        <nav
            v-if="orders.lastPage > 1"
            class="flex items-center justify-between gap-4"
        >
            <button
                class="rounded-md border px-4 py-2 text-sm disabled:opacity-50"
                :disabled="orders.currentPage === 1"
                @click="visit(orders.currentPage - 1)"
            >
                Anterior</button
            ><span class="text-sm text-muted-foreground"
                >Página {{ orders.currentPage }} de {{ orders.lastPage }}</span
            ><button
                class="rounded-md border px-4 py-2 text-sm disabled:opacity-50"
                :disabled="orders.currentPage === orders.lastPage"
                @click="visit(orders.currentPage + 1)"
            >
                Próxima
            </button>
        </nav>
    </div>
</template>
