<script setup lang="ts">
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Minus, Plus, ShoppingBag } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatMoneyInput, parseMoneyInput } from '@/lib/money';
import { store as storeOrder } from '@/routes/orders';
import { dashboard } from '@/routes';
import type { Team } from '@/types';

type Product = {
    id: number;
    name: string;
    category: string;
    description: string | null;
    ingredients: string[];
    price: string;
    optionGroups: {
        id: number;
        name: string;
        minSelections: number;
        maxSelections: number;
        options: { id: number; name: string; price: string; active: boolean }[];
    }[];
};

type OrderModality = 'local' | 'retirada' | 'entrega';
type PaymentMethod = 'dinheiro' | 'pix' | 'debito' | 'credito';

type DeliveryAddress = {
    postal_code: string;
    street: string;
    number: string;
    complement: string;
    neighborhood: string;
    city: string;
    state: string;
};

function emptyDeliveryAddress(): DeliveryAddress {
    return {
        postal_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    };
}

type OrderItemInput = {
    product_id: number;
    quantity: number;
    notes: string;
    option_ids: number[];
};

const props = defineProps<{
    products: Product[];
    modalities: { value: OrderModality; label: string }[];
    paymentMethods: { value: PaymentMethod; label: string }[];
}>();

const page = usePage();
const selectedCategory = ref<string | null>(null);
const addressDialogOpen = ref(false);
const changeForDisplay = ref('');
const deliveryFeeDisplay = ref('');
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const dashboardUrl = computed(() =>
    currentTeam.value ? dashboard(currentTeam.value.slug).url : '/',
);

const form = useHttp<
    {
        modality: OrderModality;
        customer_name: string;
        customer_phone: string;
        payment_method: PaymentMethod;
        needs_change: boolean;
        change_for: string;
        delivery_address: DeliveryAddress;
        delivery_fee: string;
        items: OrderItemInput[];
    },
    { data: { id: number } }
>(storeOrder(), {
    modality: 'local',
    customer_name: '',
    customer_phone: '',
    payment_method: 'dinheiro',
    needs_change: false,
    change_for: '',
    delivery_address: emptyDeliveryAddress(),
    delivery_fee: '',
    items: [],
});

watch(
    () => form.modality,
    (modality) => {
        if (modality !== 'entrega') {
            addressDialogOpen.value = false;
            form.delivery_fee = '';
            deliveryFeeDisplay.value = '';
            form.clearErrors('delivery_fee');
        }
    },
);

watch(
    () => form.payment_method,
    (paymentMethod) => {
        if (paymentMethod !== 'dinheiro') {
            form.needs_change = false;
            form.change_for = '';
            changeForDisplay.value = '';
            form.clearErrors('needs_change', 'change_for');
        }
    },
);

watch(
    () => form.needs_change,
    (needsChange) => {
        if (!needsChange) {
            form.change_for = '';
            changeForDisplay.value = '';
            form.clearErrors('change_for');
        }
    },
);

const selectedProducts = computed(() =>
    form.items.flatMap((item) => {
        const product = props.products.find(({ id }) => id === item.product_id);

        return product ? [{ ...product, ...item }] : [];
    }),
);

const productsByCategory = computed(() => {
    const categories = new Map<string, Product[]>();

    for (const product of props.products) {
        const products = categories.get(product.category) ?? [];
        products.push(product);
        categories.set(product.category, products);
    }

    return [...categories.entries()].sort(([first], [second]) =>
        first.localeCompare(second, 'pt-BR'),
    );
});

const activeCategoryProducts = computed(
    () =>
        productsByCategory.value.find(
            ([category]) => category === selectedCategory.value,
        )?.[1] ?? [],
);

const deliveryAddressSummary = computed(() => {
    const address = form.delivery_address;

    if (!address.street || !address.number || !address.city || !address.state) {
        return null;
    }

    return `${address.street}, ${address.number} · ${address.city}/${address.state}`;
});

const subtotal = computed(() =>
    selectedProducts.value.reduce((sum, item) => {
        const optionsTotal = item.optionGroups
            .flatMap((group) => group.options)
            .filter((option) => item.option_ids.includes(option.id))
            .reduce(
                (optionsSum, option) => optionsSum + Number(option.price),
                0,
            );

        return sum + (Number(item.price) + optionsTotal) * item.quantity;
    }, 0),
);
const deliveryFee = computed(() =>
    form.modality === 'entrega' ? Number(form.delivery_fee) || 0 : 0,
);
const total = computed(() => subtotal.value + deliveryFee.value);

const itemCount = computed(() =>
    form.items.reduce((sum, item) => sum + item.quantity, 0),
);

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

function updateChangeFor(event: Event): void {
    changeForDisplay.value = (event.target as HTMLInputElement).value;

    const amount = parseMoneyInput(changeForDisplay.value);
    form.change_for =
        amount === null ? changeForDisplay.value : amount.toFixed(2);
    form.clearErrors('change_for');
}

function formatChangeFor(): void {
    const formatted = formatMoneyInput(changeForDisplay.value);

    if (formatted) {
        changeForDisplay.value = formatted.display;
        form.change_for = formatted.decimal;
    }
}

function updateDeliveryFee(event: Event): void {
    deliveryFeeDisplay.value = (event.target as HTMLInputElement).value;

    const amount = parseMoneyInput(deliveryFeeDisplay.value);
    form.delivery_fee =
        amount === null ? deliveryFeeDisplay.value : amount.toFixed(2);
    form.clearErrors('delivery_fee');
}

function formatDeliveryFee(): void {
    const formatted = formatMoneyInput(deliveryFeeDisplay.value);

    if (formatted) {
        deliveryFeeDisplay.value = formatted.display;
        form.delivery_fee = formatted.decimal;
    }
}

function quantityFor(productId: number): number {
    return (
        form.items.find((item) => item.product_id === productId)?.quantity ?? 0
    );
}

function updateNotes(productId: number, event: Event) {
    const item = form.items.find(({ product_id }) => product_id === productId);

    if (item) {
        item.notes = (event.target as HTMLTextAreaElement).value;
    }
}

function updateCategoryDialog(open: boolean) {
    if (!open) {
        selectedCategory.value = null;
    }
}

function updateAddressDialog(open: boolean) {
    addressDialogOpen.value = open;
}

function updateQuantity(productId: number, change: number) {
    const item = form.items.find(({ product_id }) => product_id === productId);

    if (!item && change > 0) {
        form.items.push({
            product_id: productId,
            quantity: change,
            notes: '',
            option_ids: [],
        });
    } else if (item) {
        item.quantity += change;

        if (item.quantity <= 0) {
            form.items = form.items.filter(
                ({ product_id }) => product_id !== productId,
            );
        }
    }

    form.clearErrors('items');
}

function selectedCount(item: OrderItemInput, optionIds: number[]): number {
    return optionIds.filter((optionId) => item.option_ids.includes(optionId))
        .length;
}

function toggleOption(
    item: OrderItemInput,
    optionId: number,
    groupOptionIds: number[],
    maxSelections: number,
): void {
    if (item.option_ids.includes(optionId)) {
        item.option_ids = item.option_ids.filter((id) => id !== optionId);
    } else if (selectedCount(item, groupOptionIds) < maxSelections) {
        item.option_ids.push(optionId);
    }

    form.clearErrors(`items.${form.items.indexOf(item)}.option_ids`);
}

function itemUnitPrice(item: Product & OrderItemInput): number {
    const optionsTotal = item.optionGroups
        .flatMap((group) => group.options)
        .filter((option) => item.option_ids.includes(option.id))
        .reduce((sum, option) => sum + Number(option.price), 0);

    return Number(item.price) + optionsTotal;
}

function submitOrder() {
    form.post(storeOrder.url(), {
        onError: (errors) => {
            if (
                errors.delivery_fee ||
                Object.keys(errors).some((key) =>
                    key.startsWith('delivery_address'),
                )
            ) {
                addressDialogOpen.value = true;
            }
        },
        onSuccess: () => router.visit(dashboardUrl.value),
    });
}

defineOptions({
    layout: () => ({ breadcrumbs: [], showCreateOrder: true }),
});
</script>

<template>
    <Head title="Novo pedido" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 xl:p-8"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Novo pedido
                </h1>
                <p class="text-sm text-muted-foreground">
                    Escolha os produtos e personalize cada item.
                </p>
            </div>
            <Link
                :href="dashboardUrl"
                class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
            >
                <ArrowLeft class="size-4" />
                Voltar à visão geral
            </Link>
        </div>

        <form
            class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]"
            @submit.prevent="submitOrder"
        >
            <div class="space-y-5">
                <section
                    class="rounded-2xl border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
                >
                    <div class="mb-5">
                        <h2 class="font-semibold">Cardápio</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Adicione os produtos que fazem parte deste pedido.
                        </p>
                    </div>

                    <div
                        v-if="productsByCategory.length"
                        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <button
                            v-for="[
                                category,
                                categoryProducts,
                            ] in productsByCategory"
                            :key="category"
                            type="button"
                            class="group flex min-h-32 flex-col justify-between rounded-xl border bg-background/40 p-4 text-left transition-colors hover:border-primary/50 hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                            :aria-label="`Abrir categoria ${category} com ${categoryProducts.length} produtos`"
                            @click="selectedCategory = category"
                        >
                            <span
                                class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary"
                            >
                                <ShoppingBag class="size-5" />
                            </span>
                            <span
                                class="mt-5 flex w-full items-end justify-between gap-3"
                            >
                                <span>
                                    <span class="block font-semibold">{{
                                        category
                                    }}</span>
                                    <span
                                        class="mt-1 block text-sm text-muted-foreground"
                                    >
                                        {{ categoryProducts.length }}
                                        {{
                                            categoryProducts.length === 1
                                                ? 'produto'
                                                : 'produtos'
                                        }}
                                    </span>
                                </span>
                                <span class="text-sm font-medium text-primary"
                                    >Ver itens</span
                                >
                            </span>
                        </button>
                    </div>
                    <div
                        v-else
                        class="rounded-xl border border-dashed p-8 text-center"
                    >
                        <ShoppingBag
                            class="mx-auto size-8 text-muted-foreground"
                        />
                        <h3 class="mt-3 font-medium">
                            Nenhum produto disponível
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Cadastre produtos ativos para começar a montar
                            pedidos.
                        </p>
                    </div>

                    <Dialog
                        :open="selectedCategory !== null"
                        @update:open="updateCategoryDialog"
                    >
                        <DialogContent
                            class="max-h-[85vh] overflow-y-auto sm:max-w-3xl"
                        >
                            <DialogHeader>
                                <DialogTitle>{{
                                    selectedCategory
                                }}</DialogTitle>
                                <DialogDescription>
                                    Escolha os itens desta categoria para
                                    adicionar ao pedido.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <article
                                    v-for="product in activeCategoryProducts"
                                    :key="product.id"
                                    class="flex items-center gap-3 rounded-xl border bg-background/40 p-4"
                                >
                                    <div class="min-w-0 flex-1">
                                        <h3
                                            class="truncate text-sm font-medium"
                                        >
                                            {{ product.name }}
                                        </h3>
                                        <p
                                            v-if="product.description"
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ product.description }}
                                        </p>
                                        <p
                                            v-if="product.ingredients.length"
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Ingredientes:
                                            {{ product.ingredients.join(', ') }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                currency.format(
                                                    Number(product.price),
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-2"
                                    >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon-sm"
                                            :aria-label="`Remover uma unidade de ${product.name}`"
                                            :disabled="
                                                quantityFor(product.id) === 0
                                            "
                                            @click="
                                                updateQuantity(product.id, -1)
                                            "
                                        >
                                            <Minus />
                                        </Button>
                                        <span
                                            class="w-5 text-center text-sm font-medium tabular-nums"
                                        >
                                            {{ quantityFor(product.id) }}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon-sm"
                                            :aria-label="`Adicionar uma unidade de ${product.name}`"
                                            @click="
                                                updateQuantity(product.id, 1)
                                            "
                                        >
                                            <Plus />
                                        </Button>
                                    </div>
                                </article>
                            </div>
                        </DialogContent>
                    </Dialog>
                </section>

                <section
                    v-if="selectedProducts.length"
                    class="rounded-2xl border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
                >
                    <div class="mb-5">
                        <h2 class="font-semibold">Personalização dos itens</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Anote pedidos especiais, como bacon extra ou sem
                            salada.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="item in selectedProducts"
                            :key="item.product_id"
                            class="grid gap-2 border-b pb-4 last:border-b-0 last:pb-0"
                        >
                            <Label :for="`notes-${item.product_id}`">
                                {{ item.quantity }}× {{ item.name }}
                            </Label>
                            <div
                                v-for="group in item.optionGroups"
                                :key="group.id"
                                class="space-y-2 rounded-xl border bg-background/40 p-3"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span class="text-sm font-medium">{{
                                        group.name
                                    }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            group.minSelections > 0
                                                ? `Escolha de ${group.minSelections} a ${group.maxSelections}`
                                                : `Até ${group.maxSelections}`
                                        }}
                                    </span>
                                </div>
                                <label
                                    v-for="option in group.options.filter(
                                        ({ active }) => active,
                                    )"
                                    :key="option.id"
                                    class="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-2 py-1.5 hover:bg-muted/50"
                                >
                                    <span
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            class="size-4 accent-primary"
                                            :checked="
                                                item.option_ids.includes(
                                                    option.id,
                                                )
                                            "
                                            :disabled="
                                                !item.option_ids.includes(
                                                    option.id,
                                                ) &&
                                                selectedCount(
                                                    item,
                                                    group.options.map(
                                                        ({ id }) => id,
                                                    ),
                                                ) >= group.maxSelections
                                            "
                                            @change="
                                                toggleOption(
                                                    item,
                                                    option.id,
                                                    group.options.map(
                                                        ({ id }) => id,
                                                    ),
                                                    group.maxSelections,
                                                )
                                            "
                                        />
                                        {{ option.name }}
                                    </span>
                                    <span
                                        class="text-xs text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            Number(option.price) > 0
                                                ? `+ ${currency.format(Number(option.price))}`
                                                : 'Sem custo'
                                        }}
                                    </span>
                                </label>
                            </div>
                            <p
                                v-if="
                                    form.errors[
                                        `items.${form.items.findIndex(({ product_id }) => product_id === item.product_id)}.option_ids`
                                    ]
                                "
                                class="text-sm text-destructive"
                            >
                                {{
                                    form.errors[
                                        `items.${form.items.findIndex(({ product_id }) => product_id === item.product_id)}.option_ids`
                                    ]
                                }}
                            </p>
                            <textarea
                                :id="`notes-${item.product_id}`"
                                :value="item.notes"
                                @input="updateNotes(item.product_id, $event)"
                                rows="2"
                                maxlength="500"
                                class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
                                placeholder="Ex.: adicionar bacon, extra queijo, sem salada"
                            />
                        </div>
                    </div>
                </section>
            </div>

            <aside
                class="space-y-4 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm xl:sticky xl:top-24"
            >
                <div>
                    <h2 class="font-semibold">Resumo do pedido</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ itemCount }}
                        {{
                            itemCount === 1
                                ? 'item selecionado'
                                : 'itens selecionados'
                        }}
                    </p>
                </div>

                <div v-if="selectedProducts.length" class="space-y-3">
                    <div
                        v-for="item in selectedProducts"
                        :key="item.product_id"
                        class="flex justify-between gap-3 text-sm"
                    >
                        <span class="min-w-0 truncate text-muted-foreground">
                            {{ item.quantity }}× {{ item.name }}
                        </span>
                        <span class="shrink-0 font-medium tabular-nums">
                            {{
                                currency.format(
                                    itemUnitPrice(item) * item.quantity,
                                )
                            }}
                        </span>
                    </div>
                </div>
                <p
                    v-else
                    class="rounded-xl bg-muted/50 p-4 text-sm text-muted-foreground"
                >
                    Selecione produtos do cardápio para montar o pedido.
                </p>

                <p v-if="form.errors.items" class="text-sm text-destructive">
                    {{ form.errors.items }}
                </p>

                <div
                    v-if="form.modality === 'entrega'"
                    class="space-y-2 border-t pt-4 text-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground"
                            >Subtotal dos itens</span
                        >
                        <span class="tabular-nums">{{
                            currency.format(subtotal)
                        }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground"
                            >Valor da corrida</span
                        >
                        <span class="tabular-nums">{{
                            form.delivery_fee === ''
                                ? 'A definir'
                                : currency.format(deliveryFee)
                        }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t pt-4">
                    <span class="font-medium">Total</span>
                    <span class="text-xl font-semibold tabular-nums">
                        {{ currency.format(total) }}
                    </span>
                </div>

                <section class="space-y-4 border-t pt-4">
                    <div>
                        <h2 class="font-semibold">
                            Identificação e modalidade
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Dados opcionais para identificar o cliente.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="order-customer-name">Nome do cliente</Label>
                        <input
                            id="order-customer-name"
                            v-model="form.customer_name"
                            maxlength="120"
                            autocomplete="name"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="Ex.: Maria"
                        />
                        <p
                            v-if="form.errors.customer_name"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.customer_name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="order-customer-phone">Telefone</Label>
                        <input
                            id="order-customer-phone"
                            v-model="form.customer_phone"
                            type="tel"
                            inputmode="tel"
                            maxlength="20"
                            autocomplete="tel"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="(11) 99999-9999"
                        />
                        <p
                            v-if="form.modality === 'entrega'"
                            class="text-xs text-muted-foreground"
                        >
                            Necessário para pedidos de entrega.
                        </p>
                        <p
                            v-if="form.errors.customer_phone"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.customer_phone }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="order-modality">Modalidade do pedido</Label>
                        <select
                            id="order-modality"
                            v-model="form.modality"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        >
                            <option
                                v-for="modality in modalities"
                                :key="modality.value"
                                :value="modality.value"
                            >
                                {{ modality.label }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.modality"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.modality }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="order-payment-method"
                            >Forma de pagamento</Label
                        >
                        <select
                            id="order-payment-method"
                            v-model="form.payment_method"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        >
                            <option
                                v-for="method in paymentMethods"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.payment_method"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.payment_method }}
                        </p>
                    </div>

                    <div
                        v-if="form.payment_method === 'dinheiro'"
                        class="space-y-3 rounded-xl border bg-muted/30 p-4"
                    >
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="form.needs_change"
                                type="checkbox"
                                class="mt-1 size-4 accent-primary"
                            />
                            <span>
                                <span class="block text-sm font-medium"
                                    >Precisa de troco?</span
                                >
                                <span
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                >
                                    Marque se o cliente vai pagar com uma nota
                                    maior.
                                </span>
                            </span>
                        </label>

                        <div v-if="form.needs_change" class="grid gap-2">
                            <Label for="order-change-for"
                                >Cliente pagará com quanto?</Label
                            >
                            <input
                                id="order-change-for"
                                :value="changeForDisplay"
                                type="text"
                                inputmode="decimal"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                placeholder="Ex.: 50,00"
                                @input="updateChangeFor"
                                @blur="formatChangeFor"
                            />
                            <p
                                v-if="form.errors.change_for"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.change_for }}
                            </p>
                            <p
                                v-else-if="
                                    Number(form.change_for) > 0 &&
                                    Number(form.change_for) >= total
                                "
                                class="text-sm text-muted-foreground"
                            >
                                Troco:
                                {{
                                    currency.format(
                                        Number(form.change_for) - total,
                                    )
                                }}
                            </p>
                            <p
                                v-else-if="Number(form.change_for) > 0"
                                class="text-sm text-destructive"
                            >
                                O valor precisa cobrir o total de
                                {{ currency.format(total) }}.
                            </p>
                        </div>
                    </div>

                    <div v-if="form.modality === 'entrega'" class="space-y-2">
                        <Label>Endereço de entrega</Label>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-auto min-h-11 w-full justify-start py-2 text-left whitespace-normal"
                            @click="addressDialogOpen = true"
                        >
                            <span class="min-w-0">
                                <span class="block font-medium">
                                    {{
                                        deliveryAddressSummary
                                            ? 'Editar endereço'
                                            : 'Informar endereço'
                                    }}
                                </span>
                                <span
                                    class="mt-0.5 block truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        deliveryAddressSummary ||
                                        'CEP, rua, número e bairro'
                                    }}
                                </span>
                            </span>
                        </Button>
                        <p
                            v-if="
                                form.errors.delivery_address ||
                                Object.keys(form.errors).some((key) =>
                                    key.startsWith('delivery_address.'),
                                )
                            "
                            class="text-sm text-destructive"
                        >
                            Confira o endereço de entrega.
                        </p>
                    </div>
                </section>

                <Dialog
                    :open="addressDialogOpen"
                    @update:open="updateAddressDialog"
                >
                    <DialogContent
                        class="max-h-[85vh] overflow-y-auto sm:max-w-2xl"
                    >
                        <DialogHeader>
                            <DialogTitle>Endereço de entrega</DialogTitle>
                            <DialogDescription>
                                Informe o endereço e o valor da corrida.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="delivery-postal-code">CEP</Label>
                                <input
                                    id="delivery-postal-code"
                                    v-model="form.delivery_address.postal_code"
                                    inputmode="numeric"
                                    autocomplete="postal-code"
                                    maxlength="9"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                    placeholder="00000-000"
                                />
                                <p
                                    v-if="
                                        form.errors[
                                            'delivery_address.postal_code'
                                        ]
                                    "
                                    class="text-sm text-destructive"
                                >
                                    {{
                                        form.errors[
                                            'delivery_address.postal_code'
                                        ]
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="delivery-street"
                                    >Rua ou avenida</Label
                                >
                                <input
                                    id="delivery-street"
                                    v-model="form.delivery_address.street"
                                    autocomplete="address-line1"
                                    maxlength="255"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                    placeholder="Nome da rua ou avenida"
                                />
                                <p
                                    v-if="
                                        form.errors['delivery_address.street']
                                    "
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors['delivery_address.street'] }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="delivery-number">Número</Label>
                                <input
                                    id="delivery-number"
                                    v-model="form.delivery_address.number"
                                    autocomplete="address-line2"
                                    maxlength="20"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                    placeholder="Nº"
                                />
                                <p
                                    v-if="
                                        form.errors['delivery_address.number']
                                    "
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors['delivery_address.number'] }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="delivery-complement"
                                    >Complemento</Label
                                >
                                <input
                                    id="delivery-complement"
                                    v-model="form.delivery_address.complement"
                                    maxlength="120"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                    placeholder="Apto, casa, referência"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="delivery-neighborhood"
                                    >Bairro</Label
                                >
                                <input
                                    id="delivery-neighborhood"
                                    v-model="form.delivery_address.neighborhood"
                                    autocomplete="address-level3"
                                    maxlength="120"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                    placeholder="Bairro"
                                />
                                <p
                                    v-if="
                                        form.errors[
                                            'delivery_address.neighborhood'
                                        ]
                                    "
                                    class="text-sm text-destructive"
                                >
                                    {{
                                        form.errors[
                                            'delivery_address.neighborhood'
                                        ]
                                    }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="delivery-city">Cidade</Label>
                                <input
                                    id="delivery-city"
                                    v-model="form.delivery_address.city"
                                    autocomplete="address-level2"
                                    maxlength="120"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                    placeholder="Cidade"
                                />
                                <p
                                    v-if="form.errors['delivery_address.city']"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors['delivery_address.city'] }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="delivery-state">UF</Label>
                                <input
                                    id="delivery-state"
                                    v-model="form.delivery_address.state"
                                    autocomplete="address-level1"
                                    maxlength="2"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm uppercase outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                    placeholder="SP"
                                />
                                <p
                                    v-if="form.errors['delivery_address.state']"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors['delivery_address.state'] }}
                                </p>
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="delivery-fee"
                                    >Valor da corrida</Label
                                >
                                <div class="relative">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                        >R$</span
                                    >
                                    <input
                                        id="delivery-fee"
                                        :value="deliveryFeeDisplay"
                                        type="text"
                                        inputmode="decimal"
                                        class="h-10 w-full rounded-md border border-input bg-background pr-3 pl-10 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                        placeholder="Ex.: 8,00"
                                        @input="updateDeliveryFee"
                                        @blur="formatDeliveryFee"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Informe 0,00 se a entrega for gratuita.
                                </p>
                                <p
                                    v-if="form.errors.delivery_fee"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.delivery_fee }}
                                </p>
                            </div>
                        </div>

                        <Button
                            type="button"
                            class="w-full sm:w-auto sm:justify-self-end"
                            @click="addressDialogOpen = false"
                        >
                            Confirmar entrega
                        </Button>
                    </DialogContent>
                </Dialog>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing || form.items.length === 0"
                    data-test="submit-order"
                >
                    <ShoppingBag class="size-4" />
                    {{
                        form.processing
                            ? 'Registrando pedido...'
                            : 'Registrar pedido'
                    }}
                </Button>
            </aside>
        </form>
    </div>
</template>
