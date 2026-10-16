<script setup lang="ts">
import {
    MapPin,
    Phone,
    ReceiptText,
    ShoppingBag,
    Trophy,
    WalletCards,
} from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import OrderDetailsDialog from '@/components/OrderDetailsDialog.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Customer } from '@/types';
import type { OrderDetails } from '@/types/orders';

type CustomerDetails = {
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
};

const props = defineProps<{ open: boolean; url: string | null }>();
const emit = defineEmits<{ close: [] }>();
const details = ref<CustomerDetails | null>(null);
const selectedOrder = ref<OrderDetails | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
let request: AbortController | null = null;

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});
const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
});

function address(customer: Customer): string {
    if (!customer.address) return 'Endereço não informado';

    return `${customer.address.street}, ${customer.address.number}${customer.address.complement ? ` · ${customer.address.complement}` : ''} · ${customer.address.neighborhood} · ${customer.address.city}/${customer.address.state} · CEP ${customer.address.postalCode}`;
}

async function load(page = 1): Promise<void> {
    if (!props.url) return;

    request?.abort();
    request = new AbortController();
    loading.value = true;
    error.value = null;

    try {
        const separator = props.url.includes('?') ? '&' : '?';
        const response = await fetch(`${props.url}${separator}page=${page}`, {
            headers: { Accept: 'application/json' },
            signal: request.signal,
        });

        if (!response.ok)
            throw new Error('Não foi possível carregar o cliente.');

        details.value = (await response.json()) as CustomerDetails;
    } catch (reason) {
        if (reason instanceof DOMException && reason.name === 'AbortError')
            return;
        error.value = 'Não foi possível carregar os detalhes do cliente.';
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.open, props.url] as const,
    ([open]) => {
        if (open) {
            details.value = null;
            void load();
        } else {
            request?.abort();
        }
    },
);

onBeforeUnmount(() => request?.abort());

function handleOpenChange(open: boolean): void {
    if (!open) emit('close');
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle>{{
                    details?.customer.name ?? 'Detalhes do cliente'
                }}</DialogTitle>
                <DialogDescription
                    v-if="details"
                    class="flex items-center gap-1.5"
                >
                    <Phone class="size-3.5" />{{ details.customer.phone }}
                </DialogDescription>
                <DialogDescription v-else
                    >Histórico e resumo de compras.</DialogDescription
                >
            </DialogHeader>

            <div
                v-if="loading"
                class="grid min-h-56 place-items-center text-sm text-muted-foreground"
            >
                Carregando cliente...
            </div>
            <div
                v-else-if="error"
                class="grid min-h-56 place-items-center text-center"
            >
                <div>
                    <p class="text-sm text-destructive">{{ error }}</p>
                    <button
                        type="button"
                        class="mt-3 rounded-md border px-4 py-2 text-sm"
                        @click="load()"
                    >
                        Tentar novamente
                    </button>
                </div>
            </div>
            <div v-else-if="details" class="space-y-5">
                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-xl border p-4">
                        <ReceiptText class="size-4 text-primary" />
                        <p class="mt-3 text-xs text-muted-foreground">
                            Pedidos realizados
                        </p>
                        <p class="mt-1 text-xl font-semibold">
                            {{ details.summary.ordersCount }}
                        </p>
                    </article>
                    <article class="rounded-xl border p-4">
                        <ShoppingBag class="size-4 text-primary" />
                        <p class="mt-3 text-xs text-muted-foreground">
                            Compras finalizadas
                        </p>
                        <p class="mt-1 text-xl font-semibold">
                            {{ details.summary.finalizedOrdersCount }}
                        </p>
                    </article>
                    <article class="rounded-xl border p-4">
                        <WalletCards class="size-4 text-primary" />
                        <p class="mt-3 text-xs text-muted-foreground">
                            Total gasto
                        </p>
                        <p class="mt-1 text-xl font-semibold">
                            {{
                                currency.format(
                                    Number(details.summary.totalSpent),
                                )
                            }}
                        </p>
                    </article>
                    <article class="rounded-xl border p-4">
                        <Trophy class="size-4 text-primary" />
                        <p class="mt-3 text-xs text-muted-foreground">
                            Produto mais pedido
                        </p>
                        <p class="mt-1 font-semibold">
                            {{
                                details.summary.popularProduct?.name ??
                                'Sem compras finalizadas'
                            }}
                        </p>
                        <p
                            v-if="details.summary.popularProduct"
                            class="text-xs text-muted-foreground"
                        >
                            {{ details.summary.popularProduct.quantity }}
                            unidades
                        </p>
                    </article>
                </section>

                <section class="rounded-xl border p-4">
                    <h3 class="flex items-center gap-2 font-semibold">
                        <MapPin class="size-4 text-primary" />Endereço
                        cadastrado
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ address(details.customer) }}
                    </p>
                </section>

                <section class="overflow-hidden rounded-xl border">
                    <header class="border-b p-4">
                        <h3 class="font-semibold">Histórico de compras</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Clique em um pedido para ver os detalhes.
                        </p>
                    </header>
                    <div v-if="details.orders.data.length" class="divide-y">
                        <button
                            v-for="order in details.orders.data"
                            :key="order.id"
                            type="button"
                            class="grid w-full gap-2 p-4 text-left hover:bg-muted/40 sm:grid-cols-[1fr_auto_auto_auto] sm:items-center"
                            @click="selectedOrder = order"
                        >
                            <span
                                ><strong>Pedido #{{ order.number }}</strong
                                ><small class="block text-muted-foreground">{{
                                    dateFormatter.format(
                                        new Date(order.createdAt ?? ''),
                                    )
                                }}</small></span
                            >
                            <span class="text-sm">{{
                                order.modalityLabel
                            }}</span>
                            <span
                                class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium"
                                >{{ order.statusLabel }}</span
                            >
                            <span class="font-semibold tabular-nums">{{
                                currency.format(Number(order.total))
                            }}</span>
                        </button>
                    </div>
                    <p
                        v-else
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        Este cliente ainda não possui pedidos.
                    </p>
                </section>

                <nav
                    v-if="details.orders.lastPage > 1"
                    class="flex items-center justify-between gap-4"
                >
                    <button
                        type="button"
                        class="rounded-md border px-4 py-2 text-sm disabled:opacity-50"
                        :disabled="details.orders.currentPage === 1"
                        @click="load(details.orders.currentPage - 1)"
                    >
                        Anterior
                    </button>
                    <span class="text-sm text-muted-foreground"
                        >Página {{ details.orders.currentPage }} de
                        {{ details.orders.lastPage }}</span
                    >
                    <button
                        type="button"
                        class="rounded-md border px-4 py-2 text-sm disabled:opacity-50"
                        :disabled="
                            details.orders.currentPage ===
                            details.orders.lastPage
                        "
                        @click="load(details.orders.currentPage + 1)"
                    >
                        Próxima
                    </button>
                </nav>
            </div>
        </DialogContent>
    </Dialog>

    <OrderDetailsDialog :order="selectedOrder" @close="selectedOrder = null" />
</template>
