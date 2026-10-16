<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Minus,
    Plus,
    ShoppingBag,
    Trash2,
    UtensilsCrossed,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as sendOrder } from '@/routes/public-menu';
import type { MenuProduct } from '@/types/public-menu';

type CartItem = {
    product_id: number;
    quantity: number;
    notes: string;
    option_ids: number[];
};
type Address = {
    postal_code: string;
    street: string;
    number: string;
    complement: string;
    neighborhood: string;
    city: string;
    state: string;
};
type Checkout = {
    checkout_token: string;
    customer_name: string;
    customer_phone: string;
    modality: 'retirada' | 'entrega';
    payment_method: string;
    needs_change: boolean;
    change_for: string;
    delivery_address: Address;
    items: CartItem[];
};
type Confirmation = { number: number; total: string; checkoutToken: string };
const props = defineProps<{
    store: {
        name: string;
        slug: string;
        acceptingOrders: boolean;
        deliveryFee: string | null;
    };
    products: MenuProduct[];
    checkoutToken: string;
}>();
const cart = reactive<Record<number, CartItem>>({});
const search = ref('');
const category = ref('Todos');
const selectedProduct = ref<MenuProduct | null>(null);
const itemDraft = reactive({
    quantity: 1,
    notes: '',
    option_ids: [] as number[],
});
const optionError = ref('');
const requestError = ref('');
const confirmation = ref<Confirmation | null>(null);
const form = useHttp<Checkout, Confirmation>({
    checkout_token: props.checkoutToken,
    customer_name: '',
    customer_phone: '',
    modality: 'retirada',
    payment_method: 'dinheiro',
    needs_change: false,
    change_for: '',
    delivery_address: {
        postal_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    },
    items: [],
});
const categories = computed(() => [
    'Todos',
    ...new Set(props.products.map((product) => product.category)),
]);
const visibleProducts = computed(() =>
    props.products.filter(
        (product) =>
            (category.value === 'Todos' ||
                product.category === category.value) &&
            `${product.name} ${product.description ?? ''} ${product.ingredients.join(' ')}`
                .toLocaleLowerCase('pt-BR')
                .includes(search.value.toLocaleLowerCase('pt-BR')),
    ),
);
const cartRows = computed(() =>
    props.products
        .filter((product) => cart[product.id])
        .map((product) => ({ product, item: cart[product.id] })),
);
const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});
const subtotal = computed(() =>
    cartRows.value.reduce(
        (total, row) =>
            total +
            unitPrice(row.product, row.item.option_ids) * row.item.quantity,
        0,
    ),
);
const deliveryFee = computed(() =>
    form.modality === 'entrega' ? cents(props.store.deliveryFee ?? '0') : 0,
);
const total = computed(() => subtotal.value + deliveryFee.value);
const errors = computed(() => [
    ...new Set(Object.values(form.errors).map(String)),
]);
const addressFields: {
    key: keyof Address;
    label: string;
    max: number;
    required: boolean;
}[] = [
    { key: 'postal_code', label: 'CEP', max: 9, required: true },
    { key: 'street', label: 'Rua', max: 255, required: true },
    { key: 'number', label: 'Número', max: 20, required: true },
    { key: 'complement', label: 'Complemento', max: 120, required: false },
    { key: 'neighborhood', label: 'Bairro', max: 120, required: true },
    { key: 'city', label: 'Cidade', max: 120, required: true },
    { key: 'state', label: 'UF', max: 2, required: true },
];
function cents(value: string): number {
    return Math.round(Number(value) * 100);
}
function money(value: number): string {
    return currency.format(value / 100);
}
function unitPrice(product: MenuProduct, options: number[]): number {
    return (
        cents(product.price) +
        product.optionGroups
            .flatMap((group) => group.options)
            .filter((option) => options.includes(option.id))
            .reduce((sum, option) => sum + cents(option.price), 0)
    );
}
function edit(product: MenuProduct): void {
    selectedProduct.value = product;
    const item = cart[product.id];
    itemDraft.quantity = item?.quantity ?? 1;
    itemDraft.notes = item?.notes ?? '';
    itemDraft.option_ids = [...(item?.option_ids ?? [])];
    optionError.value = '';
}
function saveItem(): void {
    const product = selectedProduct.value;
    if (!product) return;
    for (const group of product.optionGroups) {
        const count = group.options.filter((option) =>
            itemDraft.option_ids.includes(option.id),
        ).length;
        if (count < group.minSelections || count > group.maxSelections) {
            optionError.value = `${group.name}: escolha de ${group.minSelections} até ${group.maxSelections} opção(ões).`;
            return;
        }
    }
    cart[product.id] = {
        product_id: product.id,
        quantity: itemDraft.quantity,
        notes: itemDraft.notes,
        option_ids: [...itemDraft.option_ids],
    };
    selectedProduct.value = null;
    confirmation.value = null;
}
function changeQuantity(id: number, delta: number): void {
    const quantity = cart[id].quantity + delta;
    if (quantity < 1) delete cart[id];
    else cart[id].quantity = Math.min(quantity, 20);
}
async function submit(): Promise<void> {
    if (
        form.processing ||
        !cartRows.value.length ||
        !props.store.acceptingOrders
    )
        return;
    requestError.value = '';
    form.items = cartRows.value.map((row) => ({
        ...row.item,
        option_ids: [...row.item.option_ids],
    }));
    try {
        const response = await form.post(sendOrder(props.store.slug).url);
        confirmation.value = response;
        form.checkout_token = response.checkoutToken;
        for (const key of Object.keys(cart)) delete cart[Number(key)];
        form.reset(
            'customer_name',
            'customer_phone',
            'delivery_address',
            'change_for',
            'needs_change',
            'items',
        );
        router.reload({ only: ['store', 'products'] });
    } catch {
        if (!form.hasErrors)
            requestError.value =
                'Não foi possível confirmar o envio. Tente novamente; a mesma tentativa não gera dois pedidos. Se persistir, atualize a página.';
    }
}
</script>

<template>
    <div class="min-h-dvh bg-[#f6f7f2] text-[#183c2d]">
        <Head :title="store.name" />
        <header class="border-b border-[#dde4da] bg-white">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="grid size-11 shrink-0 place-items-center rounded-2xl bg-[#fff4e9] text-[#ff7838]"
                        ><UtensilsCrossed class="size-6"
                    /></span>
                    <div>
                        <p class="text-xs text-[#68796c]">Seja bem-vindo</p>
                        <h1 class="text-xl font-bold tracking-tight">
                            {{ store.name }}
                        </h1>
                    </div>
                </div>
                <span
                    class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold"
                    :class="
                        store.acceptingOrders
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'bg-rose-50 text-rose-700'
                    "
                    >{{
                        store.acceptingOrders ? 'Loja aberta' : 'Loja fechada'
                    }}</span
                >
            </div>
        </header>
        <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
            <div
                v-if="!store.acceptingOrders"
                class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm"
                role="status"
            >
                Não estamos recebendo pedidos agora. Você pode consultar o
                cardápio e voltar mais tarde.
                <button
                    type="button"
                    class="ml-1 font-semibold underline"
                    @click="router.reload({ only: ['store', 'products'] })"
                >
                    Atualizar
                </button>
            </div>
            <div
                v-if="confirmation"
                class="rounded-2xl border border-emerald-300 bg-emerald-50 p-5"
                role="status"
            >
                <CheckCircle2 class="mb-2 size-7 text-emerald-600" />
                <h2 class="text-xl font-bold">
                    Pedido #{{ confirmation.number }} recebido!
                </h2>
                <p class="mt-1 text-sm">
                    Total: {{ money(cents(confirmation.total)) }}. Seu pedido já
                    foi enviado para o estabelecimento.
                </p>
                <p class="mt-1 text-sm">
                    O pagamento será feito na
                    {{ form.modality === 'entrega' ? 'entrega' : 'retirada' }}.
                    Nenhum pagamento foi realizado pelo site.
                </p>
            </div>
            <div class="grid items-start gap-6 lg:grid-cols-[1fr_360px]">
                <section class="min-w-0 space-y-5">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-widest text-[#6e8773] uppercase"
                        >
                            Feito para sua fome
                        </p>
                        <h2 class="mt-1 text-3xl font-bold tracking-tight">
                            O que vai ser hoje?
                        </h2>
                    </div>
                    <Input
                        v-model="search"
                        placeholder="Buscar no cardápio"
                        aria-label="Buscar produtos"
                        class="h-11 bg-white text-[#183c2d]"
                    />
                    <nav
                        class="flex gap-2 overflow-x-auto pb-1"
                        aria-label="Categorias"
                    >
                        <button
                            v-for="name in categories"
                            :key="name"
                            type="button"
                            class="shrink-0 rounded-full border px-4 py-2 text-sm font-medium"
                            :class="
                                category === name
                                    ? 'border-[#24533f] bg-[#24533f] text-white'
                                    : 'border-[#dde4da] bg-white'
                            "
                            :aria-pressed="category === name"
                            @click="category = name"
                        >
                            {{ name }}
                        </button>
                    </nav>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="product in visibleProducts"
                            :key="product.id"
                            type="button"
                            class="flex min-w-0 flex-col rounded-2xl border border-[#dde4da] bg-white p-5 text-left shadow-xs transition hover:border-[#94ad95]"
                            :disabled="
                                !store.acceptingOrders || form.processing
                            "
                            @click="edit(product)"
                        >
                            <span class="text-xs text-[#7a8b7d]">{{
                                product.category
                            }}</span
                            ><strong class="mt-1 text-base">{{
                                product.name
                            }}</strong
                            ><span
                                class="mt-2 text-sm leading-relaxed text-[#68796c]"
                                >{{
                                    product.description ||
                                    product.ingredients.join(', ')
                                }}</span
                            ><span
                                class="mt-4 flex w-full items-center justify-between gap-2"
                                ><strong>{{
                                    money(cents(product.price))
                                }}</strong
                                ><span
                                    class="grid size-8 place-items-center rounded-full bg-[#fff0e4] text-[#b95825]"
                                    ><Plus class="size-4" /></span
                            ></span>
                        </button>
                    </div>
                    <p
                        v-if="!visibleProducts.length"
                        class="rounded-2xl border border-dashed border-[#dde4da] p-8 text-center text-sm text-[#68796c]"
                    >
                        Nenhum produto disponível nesta busca.
                    </p>
                </section>
                <form
                    class="min-w-0 space-y-4 rounded-2xl border border-[#dde4da] bg-white p-5 lg:sticky lg:top-5"
                    @submit.prevent="submit"
                >
                    <h2 class="flex items-center gap-2 text-lg font-bold">
                        <ShoppingBag class="size-5" />Seu pedido
                    </h2>
                    <p
                        v-if="!cartRows.length"
                        class="py-4 text-sm text-[#68796c]"
                    >
                        Escolha seus favoritos no cardápio para começar.
                    </p>
                    <div
                        v-for="row in cartRows"
                        :key="row.product.id"
                        class="space-y-2 border-b border-[#e6ebe3] pb-3 text-sm"
                    >
                        <button
                            type="button"
                            class="font-semibold underline-offset-2 hover:underline"
                            :disabled="form.processing"
                            @click="edit(row.product)"
                        >
                            {{ row.product.name }}
                        </button>
                        <p
                            v-if="row.item.option_ids.length"
                            class="text-xs text-[#68796c]"
                        >
                            {{
                                row.product.optionGroups
                                    .flatMap((group) => group.options)
                                    .filter((option) =>
                                        row.item.option_ids.includes(option.id),
                                    )
                                    .map((option) => option.name)
                                    .join(', ')
                            }}
                        </p>
                        <p v-if="row.item.notes" class="text-xs text-[#68796c]">
                            {{ row.item.notes }}
                        </p>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    :aria-label="`Diminuir ${row.product.name}`"
                                    class="rounded-lg border p-1.5"
                                    :disabled="form.processing"
                                    @click="changeQuantity(row.product.id, -1)"
                                >
                                    <Minus class="size-3" /></button
                                ><span>{{ row.item.quantity }}</span
                                ><button
                                    type="button"
                                    :aria-label="`Aumentar ${row.product.name}`"
                                    class="rounded-lg border p-1.5"
                                    :disabled="
                                        form.processing ||
                                        row.item.quantity >= 20
                                    "
                                    @click="changeQuantity(row.product.id, 1)"
                                >
                                    <Plus class="size-3" /></button
                                ><button
                                    type="button"
                                    :aria-label="`Remover ${row.product.name}`"
                                    :disabled="form.processing"
                                    @click="delete cart[row.product.id]"
                                >
                                    <Trash2 class="size-4 text-rose-600" />
                                </button>
                            </div>
                            <strong>{{
                                money(
                                    unitPrice(
                                        row.product,
                                        row.item.option_ids,
                                    ) * row.item.quantity,
                                )
                            }}</strong>
                        </div>
                    </div>
                    <template v-if="cartRows.length">
                        <div class="space-y-2">
                            <Label for="public-name">Seu nome</Label
                            ><Input
                                id="public-name"
                                v-model="form.customer_name"
                                autocomplete="name"
                                maxlength="120"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="public-phone">Telefone / WhatsApp</Label
                            ><Input
                                id="public-phone"
                                v-model="form.customer_phone"
                                type="tel"
                                autocomplete="tel"
                                maxlength="20"
                                placeholder="(11) 99999-9999"
                                required
                            />
                        </div>
                        <fieldset class="space-y-2">
                            <legend class="text-sm font-medium">
                                Como você vai receber?
                            </legend>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    class="flex cursor-pointer items-center gap-2 rounded-xl border p-3 text-sm"
                                    :class="
                                        form.modality === 'retirada'
                                            ? 'border-[#24533f] bg-[#f0f5ed]'
                                            : ''
                                    "
                                    ><input
                                        v-model="form.modality"
                                        type="radio"
                                        value="retirada"
                                        name="public-modality"
                                    />Retirada</label
                                >
                                <label
                                    class="flex items-center gap-2 rounded-xl border p-3 text-sm"
                                    :class="
                                        store.deliveryFee === null
                                            ? 'cursor-not-allowed opacity-60'
                                            : form.modality === 'entrega'
                                              ? 'cursor-pointer border-[#24533f] bg-[#f0f5ed]'
                                              : 'cursor-pointer'
                                    "
                                    ><input
                                        v-model="form.modality"
                                        type="radio"
                                        value="entrega"
                                        name="public-modality"
                                        :disabled="store.deliveryFee === null"
                                    /><span
                                        >Entrega<span
                                            class="mt-0.5 block text-xs text-[#68796c]"
                                            >{{
                                                store.deliveryFee === null
                                                    ? 'Indisponível no momento'
                                                    : cents(
                                                            store.deliveryFee,
                                                        ) === 0
                                                      ? 'Grátis'
                                                      : money(
                                                            cents(
                                                                store.deliveryFee,
                                                            ),
                                                        )
                                            }}</span
                                        ></span
                                    ></label
                                >
                            </div>
                            <p
                                v-if="store.deliveryFee === null"
                                class="text-xs text-[#68796c]"
                            >
                                Este estabelecimento está aceitando somente
                                retirada no momento.
                            </p>
                        </fieldset>
                        <fieldset
                            v-if="form.modality === 'entrega'"
                            class="space-y-3 rounded-xl border p-3"
                        >
                            <legend class="px-1 text-sm font-semibold">
                                Endereço de entrega
                            </legend>
                            <div
                                v-for="field in addressFields"
                                :key="field.key"
                                class="space-y-1"
                            >
                                <Label :for="`public-address-${field.key}`">{{
                                    field.label
                                }}</Label
                                ><Input
                                    :id="`public-address-${field.key}`"
                                    v-model="form.delivery_address[field.key]"
                                    :maxlength="field.max"
                                    :required="field.required"
                                />
                            </div>
                        </fieldset>
                        <div class="space-y-2">
                            <Label for="public-payment"
                                >Pagamento na
                                {{
                                    form.modality === 'entrega'
                                        ? 'entrega'
                                        : 'retirada'
                                }}</Label
                            ><select
                                id="public-payment"
                                v-model="form.payment_method"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="dinheiro">Dinheiro</option>
                                <option value="pix">Pix</option>
                                <option value="cartao">Cartão</option>
                            </select>
                        </div>
                        <div
                            v-if="form.payment_method === 'dinheiro'"
                            class="space-y-2 rounded-xl bg-[#f6f7f2] p-3"
                        >
                            <label class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="form.needs_change"
                                    type="checkbox"
                                />Preciso de troco</label
                            >
                            <div v-if="form.needs_change" class="space-y-1">
                                <Label for="public-change"
                                    >Troco para quanto? (R$)</Label
                                ><Input
                                    id="public-change"
                                    v-model="form.change_for"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    required
                                />
                            </div>
                        </div>
                        <div class="space-y-2 border-t pt-3 text-sm">
                            <div class="flex justify-between">
                                <span>Produtos</span
                                ><span>{{ money(subtotal) }}</span>
                            </div>
                            <div
                                v-if="form.modality === 'entrega'"
                                class="flex justify-between"
                            >
                                <span>Entrega</span
                                ><span>{{ money(deliveryFee) }}</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold">
                                <span>Total</span
                                ><span>{{ money(total) }}</span>
                            </div>
                        </div>
                    </template>
                    <div
                        v-if="errors.length || requestError"
                        role="alert"
                        class="space-y-1 rounded-xl bg-rose-50 p-3 text-sm text-rose-700"
                    >
                        <p v-for="error in errors" :key="error">{{ error }}</p>
                        <p v-if="requestError">{{ requestError }}</p>
                    </div>
                    <Button
                        type="submit"
                        class="h-12 w-full bg-[#24533f] text-white hover:bg-[#183c2d]"
                        :disabled="
                            form.processing ||
                            !cartRows.length ||
                            !store.acceptingOrders
                        "
                        >{{
                            form.processing
                                ? 'Enviando pedido…'
                                : store.acceptingOrders
                                  ? 'Confirmar pedido'
                                  : 'Loja fechada'
                        }}</Button
                    >
                    <p class="text-center text-xs text-[#68796c]">
                        Pagamento combinado com o estabelecimento. Não cobramos
                        pelo site.
                    </p>
                </form>
            </div>
        </main>
        <footer
            class="flex items-center justify-center gap-2 px-4 py-8 text-xs text-[#68796c]"
        >
            <AppLogoIcon class="size-6 text-[#ff7838]" />Cardápio por Pedix
        </footer>
        <Dialog
            :open="selectedProduct !== null"
            @update:open="selectedProduct = null"
            ><DialogContent class="max-h-[90dvh] overflow-y-auto"
                ><DialogHeader
                    ><DialogTitle>{{ selectedProduct?.name }}</DialogTitle
                    ><DialogDescription
                        >Escolha os adicionais e personalize. As opções
                        escolhidas valem para todas as unidades deste
                        produto.</DialogDescription
                    ></DialogHeader
                >
                <form
                    v-if="selectedProduct"
                    class="space-y-4"
                    @submit.prevent="saveItem"
                >
                    <fieldset
                        v-for="group in selectedProduct.optionGroups"
                        :key="group.id"
                        class="space-y-2 rounded-xl border p-3"
                    >
                        <legend class="px-1 text-sm font-semibold">
                            {{ group.name }}
                        </legend>
                        <p class="text-xs text-muted-foreground">
                            Escolha de {{ group.minSelections }} até
                            {{ group.maxSelections }} opções.
                        </p>
                        <label
                            v-for="option in group.options"
                            :key="option.id"
                            class="flex items-center justify-between gap-3 text-sm"
                            ><span class="flex items-center gap-2"
                                ><input
                                    v-model="itemDraft.option_ids"
                                    type="checkbox"
                                    :value="option.id"
                                />{{ option.name }}</span
                            ><span
                                >+ {{ money(cents(option.price)) }}</span
                            ></label
                        >
                    </fieldset>
                    <div class="space-y-2">
                        <Label for="public-item-quantity">Quantidade</Label
                        ><Input
                            id="public-item-quantity"
                            v-model.number="itemDraft.quantity"
                            type="number"
                            min="1"
                            max="20"
                            required
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="public-item-notes">Observações</Label
                        ><textarea
                            id="public-item-notes"
                            v-model="itemDraft.notes"
                            class="min-h-20 w-full rounded-md border bg-background p-3 text-sm"
                            maxlength="500"
                            placeholder="Ex.: sem cebola"
                        />
                    </div>
                    <p
                        v-if="optionError"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ optionError }}
                    </p>
                    <Button type="submit" class="w-full"
                        >Salvar no pedido ·
                        {{
                            money(
                                unitPrice(
                                    selectedProduct,
                                    itemDraft.option_ids,
                                ) * itemDraft.quantity,
                            )
                        }}</Button
                    >
                </form></DialogContent
            ></Dialog
        >
    </div>
</template>
