<script setup lang="ts">
import type { CourierReport } from '@/types/reports';

defineProps<{ report: CourierReport }>();
const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});
const minutes = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

function money(value: string): string {
    return currency.format(Number(value));
}

function date(value: string | null): string {
    return value
        ? new Date(value).toLocaleString('pt-BR', {
              dateStyle: 'short',
              timeStyle: 'short',
          })
        : '—';
}
</script>

<template>
    <section class="min-w-0">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex flex-wrap gap-3 text-sm">
                <span class="rounded-xl bg-primary/10 px-3 py-2 text-primary"
                    ><strong>{{ report.deliveries }}</strong> entregas com
                    motoboy</span
                >
                <span class="rounded-xl bg-muted px-3 py-2"
                    ><strong>{{ money(report.deliveryFees) }}</strong> em
                    taxas</span
                >
            </div>
        </div>

        <p
            v-if="report.unassignedDeliveries"
            class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-sm"
        >
            {{ report.unassignedDeliveries }} entrega(s) finalizada(s) sem
            motoboy válido vinculado. Elas não entram no desempenho individual.
        </p>

        <div v-if="report.rows.length" class="mt-5 space-y-3">
            <details
                v-for="courier in report.rows"
                :key="courier.id"
                class="group rounded-xl border bg-background"
            >
                <summary
                    class="cursor-pointer rounded-xl p-4 focus-visible:outline-2 focus-visible:outline-primary"
                >
                    <span
                        class="inline-flex flex-wrap items-center gap-2 font-semibold"
                    >
                        {{ courier.name }}
                        <span
                            v-if="!courier.active"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs font-normal text-muted-foreground"
                            >Inativo</span
                        >
                    </span>
                    <span
                        class="mt-3 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3 xl:grid-cols-6"
                    >
                        <span
                            ><span class="block text-xs text-muted-foreground"
                                >Entregas</span
                            ><strong>{{ courier.deliveries }}</strong></span
                        >
                        <span
                            ><span class="block text-xs text-muted-foreground"
                                >Taxas acumuladas</span
                            ><strong>{{
                                money(courier.deliveryFees)
                            }}</strong></span
                        >
                        <span
                            ><span class="block text-xs text-muted-foreground"
                                >Taxa média</span
                            ><strong>{{
                                money(courier.averageFee)
                            }}</strong></span
                        >
                        <span
                            ><span class="block text-xs text-muted-foreground"
                                >Total dos pedidos</span
                            ><strong>{{
                                money(courier.ordersTotal)
                            }}</strong></span
                        >
                        <span
                            ><span class="block text-xs text-muted-foreground"
                                >Ticket médio</span
                            ><strong>{{
                                money(courier.averageTicket)
                            }}</strong></span
                        >
                        <span
                            :title="`Base: ${courier.timedDeliveries} entrega(s) com horários válidos`"
                            ><span class="block text-xs text-muted-foreground"
                                >Tempo médio no pedido</span
                            ><strong>{{
                                courier.averageDeliveryMinutes === null
                                    ? 'Sem dados'
                                    : `${minutes.format(courier.averageDeliveryMinutes)} min`
                            }}</strong></span
                        >
                    </span>
                    <span class="mt-3 block text-xs font-medium text-primary"
                        >Ver últimas entregas</span
                    >
                </summary>
                <div class="border-t p-4">
                    <p class="mb-3 text-xs text-muted-foreground">
                        Até 5 entregas mais recentes do período. O total do
                        pedido inclui a taxa de entrega.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-96 text-left text-sm">
                            <thead class="text-xs text-muted-foreground">
                                <tr>
                                    <th class="pb-2 font-medium">Pedido</th>
                                    <th class="pb-2 font-medium">
                                        Finalização
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        Taxa
                                    </th>
                                    <th class="pb-2 text-right font-medium">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="order in courier.recentDeliveries"
                                    :key="order.id"
                                >
                                    <td class="py-2 font-medium">
                                        #{{ order.number }}
                                    </td>
                                    <td class="py-2">
                                        {{ date(order.finalizedAt) }}
                                    </td>
                                    <td class="py-2 text-right tabular-nums">
                                        {{ money(order.deliveryFee) }}
                                    </td>
                                    <td class="py-2 text-right tabular-nums">
                                        {{ money(order.total) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>
        </div>
        <p
            v-else
            class="mt-5 rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Nenhuma entrega finalizada com motoboy neste período.
        </p>

        <p class="mt-4 text-xs leading-relaxed text-muted-foreground">
            As taxas são os valores cobrados dos clientes; não representam um
            repasse calculado ao motoboy. O tempo médio vai da primeira seleção
            do motoboy até a finalização registrada, podendo incluir o preparo.
            Não mede somente o tempo de corrida. Considera apenas horários
            válidos e atribui o resultado ao último motoboy vinculado.
        </p>
    </section>
</template>
