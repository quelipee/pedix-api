<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { command as orderCommand } from '@/routes/orders';
import { Bike, Printer } from '@lucide/vue';
import type { OrderDetails, OrderStatus } from '@/types/orders';

defineProps<{ order: OrderDetails | null }>();

const emit = defineEmits<{ close: [] }>();
const page = usePage();

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const statusClasses: Record<OrderStatus, string> = {
    novo: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    em_preparo:
        'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    pronto: 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
    finalizado: 'bg-muted text-muted-foreground',
    cancelado:
        'bg-rose-500/15 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
};

function handleOpenChange(open: boolean): void {
    if (!open) {
        emit('close');
    }
}

function formatOrderDate(value: string | null): string {
    if (!value) {
        return 'data não informada';
    }

    if (!/^\d{4}-\d{2}-\d{2}T/.test(value)) {
        return value;
    }

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function commandUrl(orderId: number): string {
    const team = page.props.currentTeam;

    return team
        ? orderCommand({ current_team: team.slug, order: orderId }).url
        : '#';
}
</script>

<template>
    <Dialog :open="order !== null" @update:open="handleOpenChange">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Pedido #{{ order?.id }}</DialogTitle>
                <DialogDescription
                    >Detalhes do pedido registrado em
                    {{
                        formatOrderDate(order?.createdAt ?? null)
                    }}.</DialogDescription
                >
            </DialogHeader>

            <div v-if="order" class="space-y-5">
                <div
                    v-if="order.status === 'cancelado'"
                    class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm"
                >
                    <p class="font-semibold text-rose-700 dark:text-rose-300">
                        Pedido cancelado
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        {{
                            order.cancellationReason || 'Motivo não informado.'
                        }}
                    </p>
                    <p
                        v-if="order.cancelledBy"
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Cancelado por {{ order.cancelledBy.name }}
                        <template v-if="order.cancelledAt">
                            · {{ formatOrderDate(order.cancelledAt) }}
                        </template>
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border p-3">
                        <p class="text-xs text-muted-foreground">Cliente</p>
                        <p class="mt-1 font-medium">
                            {{ order.customerName || 'Não informado' }}
                        </p>
                    </div>
                    <div class="rounded-xl border p-3">
                        <p class="text-xs text-muted-foreground">Telefone</p>
                        <p class="mt-1 font-medium">
                            {{ order.customerPhone || 'Não informado' }}
                        </p>
                    </div>
                    <div class="rounded-xl border p-3">
                        <p class="text-xs text-muted-foreground">Modalidade</p>
                        <p class="mt-1 font-medium">
                            {{ order.modalityLabel }}
                        </p>
                    </div>
                    <div class="rounded-xl border p-3">
                        <p class="text-xs text-muted-foreground">Status</p>
                        <span
                            class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="statusClasses[order.status]"
                        >
                            {{ order.statusLabel }}
                        </span>
                    </div>
                    <div class="rounded-xl border p-3">
                        <p class="text-xs text-muted-foreground">Pagamento</p>
                        <p class="mt-1 font-medium">
                            {{ order.paymentMethodLabel || 'Não informado' }}
                        </p>
                    </div>
                    <div
                        v-if="order.changeAmount !== null"
                        class="rounded-xl border p-3"
                    >
                        <p class="text-xs text-muted-foreground">Troco</p>
                        <p class="mt-1 font-medium">
                            {{ currency.format(Number(order.changeAmount)) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Pagará com
                            {{ currency.format(Number(order.changeFor)) }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="order.courier"
                    class="flex items-center gap-3 rounded-xl border border-primary/20 bg-primary/5 p-3"
                >
                    <Bike class="size-5 text-primary" />
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Motoboy responsável
                        </p>
                        <p class="font-medium">{{ order.courier.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ order.courier.phone }}
                        </p>
                    </div>
                </div>

                <section
                    v-if="order.deliveryAddress"
                    class="space-y-1 rounded-xl border p-3"
                >
                    <h3 class="text-sm font-semibold">Endereço de entrega</h3>
                    <p class="text-sm">
                        {{ order.deliveryAddress.street }},
                        {{ order.deliveryAddress.number }}
                        <span v-if="order.deliveryAddress.complement"
                            >· {{ order.deliveryAddress.complement }}</span
                        >
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ order.deliveryAddress.neighborhood }} ·
                        {{ order.deliveryAddress.city }}/{{
                            order.deliveryAddress.state
                        }}
                        · CEP {{ order.deliveryAddress.postalCode }}
                    </p>
                </section>

                <section class="space-y-3">
                    <h3 class="font-semibold">Itens do pedido</h3>
                    <div
                        v-for="(item, index) in order.items"
                        :key="`${order.id}-${index}`"
                        class="flex items-start justify-between gap-4 rounded-xl border p-3"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ item.quantity }}× {{ item.productName }}
                            </p>
                            <p
                                v-if="item.notes"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                Observação: {{ item.notes }}
                            </p>
                            <ul
                                v-if="item.selectedOptions.length"
                                class="mt-1 space-y-0.5 text-sm text-muted-foreground"
                            >
                                <li
                                    v-for="option in item.selectedOptions"
                                    :key="option.id"
                                >
                                    {{ option.name }}
                                    <span v-if="Number(option.price) > 0"
                                        >(+
                                        {{
                                            currency.format(
                                                Number(option.price),
                                            )
                                        }})</span
                                    >
                                </li>
                            </ul>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ currency.format(Number(item.unitPrice)) }}
                                cada
                            </p>
                        </div>
                        <span class="shrink-0 text-sm font-medium tabular-nums">
                            {{
                                currency.format(
                                    Number(item.unitPrice) * item.quantity,
                                )
                            }}
                        </span>
                    </div>
                    <p
                        v-if="order.items.length === 0"
                        class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        Este pedido não possui itens registrados.
                    </p>
                </section>

                <div
                    v-if="order.deliveryFee !== null"
                    class="flex items-center justify-between text-sm"
                >
                    <span class="text-muted-foreground">Valor da corrida</span>
                    <span class="font-medium tabular-nums">{{
                        currency.format(Number(order.deliveryFee))
                    }}</span>
                </div>

                <div class="flex items-center justify-between border-t pt-4">
                    <span class="font-medium">Total</span>
                    <span class="text-xl font-semibold tabular-nums">
                        {{ currency.format(Number(order.total)) }}
                    </span>
                </div>

                <Button as-child class="w-full gap-2 rounded-xl">
                    <a
                        :href="commandUrl(order.id)"
                        target="_blank"
                        rel="noopener"
                    >
                        <Printer class="size-4" />
                        Abrir comanda para impressão
                    </a>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
