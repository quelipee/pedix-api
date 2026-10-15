<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

type CommandItem = {
    productName: string;
    quantity: number;
    unitPrice: string;
    subtotal: string;
    notes: string | null;
    selectedOptions: {
        id: number;
        group: string;
        name: string;
        price: string;
    }[];
};

type OrderCommand = {
    number: number;
    teamName: string;
    createdAt: string;
    modality: string;
    customerName: string | null;
    customerPhone: string | null;
    deliveryAddress: {
        postal_code: string;
        street: string;
        number: string;
        complement?: string | null;
        neighborhood: string;
        city: string;
        state: string;
    } | null;
    items: CommandItem[];
    deliveryFee: string | null;
    total: string;
    paymentMethod: string | null;
    changeFor: string | null;
    changeAmount: string | null;
    courierName: string | null;
};

defineProps<{ command: OrderCommand }>();

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

function money(value: string): string {
    return currency.format(Number(value));
}

function dateTime(value: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function printCommand(): void {
    window.print();
}

function goBack(): void {
    window.history.back();
}
</script>

<template>
    <Head :title="`Comanda do pedido #${command.number}`" />

    <main
        class="min-h-screen bg-[#efefec] px-4 py-6 text-black print:bg-white print:p-0"
    >
        <div
            class="print-hidden mx-auto mb-4 flex w-full max-w-sm items-center justify-between gap-3"
        >
            <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl border border-black/10 bg-white px-4 text-sm font-semibold shadow-sm transition hover:bg-[#f7f7f4]"
                @click="goBack"
            >
                <ArrowLeft class="size-4" /> Voltar
            </button>
            <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#163c2c] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0f2e21]"
                @click="printCommand"
            >
                <Printer class="size-4" /> Imprimir comanda
            </button>
        </div>

        <article
            class="receipt mx-auto w-full max-w-sm bg-white px-5 py-6 font-mono text-[12px] leading-5 shadow-xl print:max-w-none print:p-0 print:shadow-none"
        >
            <header
                class="border-b border-dashed border-black pb-4 text-center"
            >
                <div
                    class="mx-auto mb-2 flex size-10 items-center justify-center rounded-lg border border-black/15 text-[#163c2c] print:text-black"
                >
                    <AppLogoIcon class-name="size-9" />
                </div>
                <p class="text-base font-bold tracking-wide uppercase">
                    {{ command.teamName }}
                </p>
                <p>COMANDA DE PEDIDO</p>
            </header>

            <section class="border-b border-dashed border-black py-4">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-[10px] uppercase">Pedido</p>
                        <p class="text-3xl font-black">#{{ command.number }}</p>
                    </div>
                    <p class="text-right">{{ dateTime(command.createdAt) }}</p>
                </div>
                <div class="mt-3 flex justify-between gap-3">
                    <span>Modalidade</span>
                    <strong class="text-right uppercase">{{
                        command.modality
                    }}</strong>
                </div>
            </section>

            <section
                v-if="command.customerName || command.customerPhone"
                class="border-b border-dashed border-black py-4"
            >
                <p class="mb-1 font-bold uppercase">Cliente</p>
                <p v-if="command.customerName">{{ command.customerName }}</p>
                <p v-if="command.customerPhone">{{ command.customerPhone }}</p>
            </section>

            <section
                v-if="command.deliveryAddress"
                class="border-b border-dashed border-black py-4"
            >
                <p class="mb-1 font-bold uppercase">Endereço de entrega</p>
                <p>
                    {{ command.deliveryAddress.street }},
                    {{ command.deliveryAddress.number }}
                </p>
                <p v-if="command.deliveryAddress.complement">
                    {{ command.deliveryAddress.complement }}
                </p>
                <p>{{ command.deliveryAddress.neighborhood }}</p>
                <p>
                    {{ command.deliveryAddress.city }}/{{
                        command.deliveryAddress.state
                    }}
                    · CEP {{ command.deliveryAddress.postal_code }}
                </p>
            </section>

            <section class="border-b border-dashed border-black py-4">
                <div
                    class="mb-3 grid grid-cols-[1fr_auto] gap-3 border-b border-black pb-1 font-bold uppercase"
                >
                    <span>Itens</span><span>Valor</span>
                </div>
                <div
                    v-for="(item, index) in command.items"
                    :key="`${item.productName}-${index}`"
                    class="mb-4 last:mb-0"
                >
                    <div class="grid grid-cols-[1fr_auto] gap-3 font-bold">
                        <span>{{ item.quantity }}x {{ item.productName }}</span>
                        <span>{{ money(item.subtotal) }}</span>
                    </div>
                    <p class="text-[10px]">{{ money(item.unitPrice) }} cada</p>
                    <ul
                        v-if="item.selectedOptions.length"
                        class="mt-1 pl-3 text-[11px]"
                    >
                        <li
                            v-for="option in item.selectedOptions"
                            :key="option.id"
                        >
                            + {{ option.name
                            }}<span v-if="Number(option.price) > 0">
                                ({{ money(option.price) }})</span
                            >
                        </li>
                    </ul>
                    <p
                        v-if="item.notes"
                        class="mt-1 border-l-2 border-black pl-2 font-bold"
                    >
                        OBS: {{ item.notes }}
                    </p>
                </div>
            </section>

            <section class="border-b border-dashed border-black py-4">
                <div
                    v-if="command.deliveryFee !== null"
                    class="flex justify-between gap-3"
                >
                    <span>Taxa de entrega</span
                    ><span>{{ money(command.deliveryFee) }}</span>
                </div>
                <div
                    class="mt-2 flex items-end justify-between gap-3 text-base font-black"
                >
                    <span>TOTAL</span><span>{{ money(command.total) }}</span>
                </div>
            </section>

            <section class="border-b border-dashed border-black py-4">
                <div class="flex justify-between gap-3">
                    <span>Pagamento</span
                    ><strong>{{
                        command.paymentMethod || 'Não informado'
                    }}</strong>
                </div>
                <div
                    v-if="command.changeFor !== null"
                    class="mt-1 flex justify-between gap-3"
                >
                    <span>Troco para</span
                    ><span>{{ money(command.changeFor) }}</span>
                </div>
                <div
                    v-if="command.changeAmount !== null"
                    class="mt-1 flex justify-between gap-3"
                >
                    <span>Troco</span
                    ><strong>{{ money(command.changeAmount) }}</strong>
                </div>
                <div
                    v-if="command.courierName"
                    class="mt-1 flex justify-between gap-3"
                >
                    <span>Motoboy</span
                    ><strong>{{ command.courierName }}</strong>
                </div>
            </section>

            <footer class="pt-4 text-center text-[10px]">
                <p>Impresso pela Pedix</p>
                <p>Pedido #{{ command.number }}</p>
            </footer>
        </article>
    </main>
</template>

<style>
@media print {
    @page {
        size: 80mm auto;
        margin: 4mm;
    }

    .print-hidden {
        display: none !important;
    }

    .receipt {
        width: 72mm;
    }
}
</style>
