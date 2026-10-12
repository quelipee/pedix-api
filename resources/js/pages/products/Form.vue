<script setup lang="ts">
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Plus, ShoppingBag, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatMoneyInput, parseMoneyInput } from '@/lib/money';
import {
    catalog as productCatalog,
    destroy as destroyProduct,
    store as storeProduct,
    update as updateProduct,
} from '@/routes/products';
import type { Team } from '@/types';

type Product = {
    id: number;
    name: string;
    category: string;
    description: string | null;
    ingredients: string[];
    price: string;
    active: boolean;
    optionGroups: ProductOptionGroupResource[];
};

type ProductOptionGroupResource = {
    name: string;
    minSelections: number;
    maxSelections: number;
    options: { name: string; price: string; active: boolean }[];
};

type ProductOptionGroupInput = {
    name: string;
    min_selections: number;
    max_selections: number;
    options: { name: string; price: string; active: boolean }[];
};

const props = defineProps<{ categories: string[]; product?: Product }>();

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const catalogUrl = computed(() =>
    currentTeam.value ? productCatalog(currentTeam.value.slug).url : '/',
);
const priceDisplay = ref(
    props.product
        ? (formatMoneyInput(props.product.price)?.display ??
              props.product.price)
        : '',
);
const ingredientDraft = ref('');
const ingredientError = ref('');
const deleteDialogOpen = ref(false);
const deleteError = ref('');
const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

const form = useHttp<{
    name: string;
    category: string;
    description: string;
    ingredients: string[];
    price: string;
    active: boolean;
    option_groups: ProductOptionGroupInput[];
}>({
    name: props.product?.name ?? '',
    category: props.product?.category ?? 'Lanches',
    description: props.product?.description ?? '',
    ingredients: [...(props.product?.ingredients ?? [])],
    price: props.product?.price ?? '',
    active: props.product?.active ?? true,
    option_groups: (props.product?.optionGroups ?? []).map((group) => ({
        name: group.name,
        min_selections: group.minSelections,
        max_selections: group.maxSelections,
        options: group.options.map((option) => ({ ...option })),
    })),
});
const deleteForm = useHttp({});

function updatePrice(event: Event): void {
    priceDisplay.value = (event.target as HTMLInputElement).value;
    const amount = parseMoneyInput(priceDisplay.value);
    form.price = amount === null ? priceDisplay.value : amount.toFixed(2);
    form.clearErrors('price');
}

function formatPrice(): void {
    const formatted = formatMoneyInput(priceDisplay.value);

    if (formatted) {
        priceDisplay.value = formatted.display;
        form.price = formatted.decimal;
    }
}

function addIngredient(): void {
    const ingredient = ingredientDraft.value.trim();

    if (!ingredient) {
        return;
    }

    if (
        form.ingredients.some(
            (existing) =>
                existing.toLocaleLowerCase('pt-BR') ===
                ingredient.toLocaleLowerCase('pt-BR'),
        )
    ) {
        ingredientError.value = 'Este ingrediente já foi adicionado.';
        return;
    }

    form.ingredients.push(ingredient);
    ingredientDraft.value = '';
    ingredientError.value = '';
    form.clearErrors('ingredients');
}

function removeIngredient(index: number): void {
    form.ingredients.splice(index, 1);
    ingredientError.value = '';
    form.clearErrors('ingredients');
}

function addOptionGroup(): void {
    form.option_groups.push({
        name: '',
        min_selections: 0,
        max_selections: 1,
        options: [{ name: '', price: '0.00', active: true }],
    });
}

function removeOptionGroup(groupIndex: number): void {
    form.option_groups.splice(groupIndex, 1);
}

function addOption(groupIndex: number): void {
    form.option_groups[groupIndex]?.options.push({
        name: '',
        price: '0.00',
        active: true,
    });
}

function removeOption(groupIndex: number, optionIndex: number): void {
    const group = form.option_groups[groupIndex];
    if (group && group.options.length > 1) {
        group.options.splice(optionIndex, 1);
    }
}

function submitProduct(): void {
    if (ingredientDraft.value.trim()) {
        addIngredient();
    }

    if (ingredientError.value) {
        return;
    }

    for (const group of form.option_groups) {
        for (const option of group.options) {
            const parsedPrice = parseMoneyInput(option.price);
            if (parsedPrice !== null) {
                option.price = parsedPrice.toFixed(2);
            }
        }
    }

    const onSuccess = () => router.visit(catalogUrl.value);

    if (props.product) {
        form.patch(updateProduct(props.product.id).url, { onSuccess });
    } else {
        form.post(storeProduct.url(), { onSuccess });
    }
}

function deleteItem(): void {
    if (!props.product || deleteForm.processing) {
        return;
    }

    deleteError.value = '';
    deleteForm.delete(destroyProduct(props.product.id).url, {
        onSuccess: () => router.visit(catalogUrl.value),
        onError: () => {
            deleteError.value =
                'Não foi possível excluir o item. Tente novamente.';
        },
    });
}

defineOptions({
    layout: () => ({ breadcrumbs: [], showCreateOrder: true }),
});
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 xl:p-8"
    >
        <Head :title="product ? 'Editar item' : 'Novo item'" />
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    {{ product ? 'Editar item' : 'Novo item' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        product
                            ? 'Atualize as informações deste item do cardápio.'
                            : 'Cadastre um produto para usar no seu cardápio e nos pedidos.'
                    }}
                </p>
            </div>
            <Link
                :href="catalogUrl"
                class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            >
                <ArrowLeft class="size-4" />
                Voltar ao cardápio
            </Link>
        </header>

        <form
            class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]"
            @submit.prevent="submitProduct"
        >
            <div class="space-y-5">
                <section
                    class="space-y-5 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
                >
                    <div>
                        <h2 class="font-semibold">Informações do item</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Nome, categoria, preço e apresentação.
                        </p>
                    </div>

                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="product-name">Nome do item</Label>
                            <input
                                id="product-name"
                                v-model="form.name"
                                maxlength="255"
                                autocomplete="off"
                                placeholder="Ex.: X-Salada"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="product-category">Categoria</Label>
                            <input
                                id="product-category"
                                v-model="form.category"
                                list="product-categories"
                                maxlength="80"
                                placeholder="Ex.: Lanches"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            />
                            <datalist id="product-categories">
                                <option
                                    v-for="category in categories"
                                    :key="category"
                                    :value="category"
                                />
                            </datalist>
                            <p class="text-xs text-muted-foreground">
                                Escolha uma sugestão ou digite uma nova
                                categoria.
                            </p>
                            <p
                                v-if="form.errors.category"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.category }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="product-price">Preço</Label>
                            <div class="relative">
                                <span
                                    class="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                    >R$</span
                                >
                                <input
                                    id="product-price"
                                    :value="priceDisplay"
                                    type="text"
                                    inputmode="decimal"
                                    placeholder="Ex.: 22,00"
                                    class="h-10 w-full rounded-md border border-input bg-background pr-3 pl-10 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                    @input="updatePrice"
                                    @blur="formatPrice"
                                />
                            </div>
                            <p
                                v-if="form.errors.price"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.price }}
                            </p>
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="product-description">Descrição</Label>
                            <textarea
                                id="product-description"
                                v-model="form.description"
                                rows="3"
                                maxlength="1000"
                                placeholder="Ex.: Hambúrguer artesanal com salada fresca."
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            />
                            <p class="text-xs text-muted-foreground">
                                Uma apresentação curta para identificar o
                                produto.
                            </p>
                            <p
                                v-if="form.errors.description"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.description }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="space-y-5 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
                >
                    <div>
                        <h2 class="font-semibold">Ingredientes</h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Liste o que compõe o item, um ingrediente de cada
                            vez.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <input
                            v-model="ingredientDraft"
                            type="text"
                            maxlength="80"
                            aria-label="Novo ingrediente"
                            placeholder="Ex.: Tomate"
                            class="h-10 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            @keydown.enter.prevent="addIngredient"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2"
                            @click="addIngredient"
                        >
                            <Plus class="size-4" /> Adicionar
                        </Button>
                    </div>
                    <p v-if="ingredientError" class="text-sm text-destructive">
                        {{ ingredientError }}
                    </p>
                    <p
                        v-if="form.errors.ingredients"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.ingredients }}
                    </p>
                    <p
                        v-for="[field, message] in Object.entries(
                            form.errors,
                        ).filter(([key]) => key.startsWith('ingredients.'))"
                        :key="field"
                        class="text-sm text-destructive"
                    >
                        {{ message }}
                    </p>
                    <div
                        v-if="form.ingredients.length"
                        class="flex flex-wrap gap-2"
                    >
                        <span
                            v-for="(ingredient, index) in form.ingredients"
                            :key="`${ingredient}-${index}`"
                            class="inline-flex items-center gap-1 rounded-full border bg-background px-3 py-1.5 text-sm"
                        >
                            {{ ingredient }}
                            <button
                                type="button"
                                :aria-label="`Remover ${ingredient}`"
                                class="rounded-full p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="removeIngredient(index)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </span>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Você pode deixar sem ingredientes para bebidas ou itens
                        simples.
                    </p>
                </section>

                <section
                    class="space-y-5 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h2 class="font-semibold">Opções e adicionais</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Crie grupos como “Adicionais” ou “Ponto da
                                carne”.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2"
                            @click="addOptionGroup"
                            ><Plus class="size-4" /> Novo grupo</Button
                        >
                    </div>
                    <div v-if="form.option_groups.length" class="space-y-4">
                        <div
                            v-for="(group, groupIndex) in form.option_groups"
                            :key="groupIndex"
                            class="space-y-4 rounded-xl border bg-background/40 p-4"
                        >
                            <div class="flex items-start gap-3">
                                <div class="grid min-w-0 flex-1 gap-2">
                                    <Label :for="`group-name-${groupIndex}`"
                                        >Nome do grupo</Label
                                    >
                                    <input
                                        :id="`group-name-${groupIndex}`"
                                        v-model="group.name"
                                        maxlength="120"
                                        placeholder="Ex.: Adicionais"
                                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="mt-7 text-destructive"
                                    aria-label="Remover grupo"
                                    @click="removeOptionGroup(groupIndex)"
                                    ><X class="size-4"
                                /></Button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label :for="`group-min-${groupIndex}`"
                                        >Mínimo de escolhas</Label
                                    ><input
                                        :id="`group-min-${groupIndex}`"
                                        v-model.number="group.min_selections"
                                        type="number"
                                        min="0"
                                        max="20"
                                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`group-max-${groupIndex}`"
                                        >Máximo de escolhas</Label
                                    ><input
                                        :id="`group-max-${groupIndex}`"
                                        v-model.number="group.max_selections"
                                        type="number"
                                        min="1"
                                        max="20"
                                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    />
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div
                                    v-for="(
                                        option, optionIndex
                                    ) in group.options"
                                    :key="optionIndex"
                                    class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_150px_auto_auto]"
                                >
                                    <div class="grid gap-2">
                                        <Label
                                            :for="`option-name-${groupIndex}-${optionIndex}`"
                                            >Opção</Label
                                        ><input
                                            :id="`option-name-${groupIndex}-${optionIndex}`"
                                            v-model="option.name"
                                            maxlength="120"
                                            placeholder="Ex.: Bacon extra"
                                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            :for="`option-price-${groupIndex}-${optionIndex}`"
                                            >Preço adicional</Label
                                        ><input
                                            :id="`option-price-${groupIndex}-${optionIndex}`"
                                            v-model="option.price"
                                            inputmode="decimal"
                                            placeholder="0.00"
                                            class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        />
                                    </div>
                                    <label
                                        class="flex h-10 items-center gap-2 text-sm"
                                        ><input
                                            v-model="option.active"
                                            type="checkbox"
                                            class="size-4 accent-primary"
                                        />
                                        Disponível</label
                                    >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        :disabled="group.options.length === 1"
                                        aria-label="Remover opção"
                                        @click="
                                            removeOption(
                                                groupIndex,
                                                optionIndex,
                                            )
                                        "
                                        ><X class="size-4"
                                    /></Button>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="gap-2"
                                    @click="addOption(groupIndex)"
                                    ><Plus class="size-4" /> Adicionar
                                    opção</Button
                                >
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="rounded-xl border border-dashed p-5 text-sm text-muted-foreground"
                    >
                        Este item ainda não possui personalizações.
                    </p>
                    <p
                        v-for="[field, message] in Object.entries(
                            form.errors,
                        ).filter(([key]) => key.startsWith('option_groups'))"
                        :key="field"
                        class="text-sm text-destructive"
                    >
                        {{ message }}
                    </p>
                </section>
            </div>

            <aside class="space-y-5">
                <section
                    class="rounded-2xl border bg-card p-5 text-card-foreground shadow-sm sm:p-6"
                >
                    <h2 class="font-semibold">Prévia do item</h2>
                    <div class="mt-5 rounded-xl border bg-background/40 p-4">
                        <span
                            class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"
                        >
                            <ShoppingBag class="size-5" />
                        </span>
                        <p class="mt-4 text-xs text-muted-foreground">
                            {{ form.category || 'Categoria' }}
                        </p>
                        <h3 class="mt-1 font-semibold">
                            {{ form.name || 'Nome do item' }}
                        </h3>
                        <p
                            v-if="form.description"
                            class="mt-2 text-sm text-muted-foreground"
                        >
                            {{ form.description }}
                        </p>
                        <p
                            v-if="form.ingredients.length"
                            class="mt-3 text-xs text-muted-foreground"
                        >
                            {{ form.ingredients.join(' · ') }}
                        </p>
                        <p class="mt-5 text-xl font-semibold tabular-nums">
                            {{ currency.format(Number(form.price) || 0) }}
                        </p>
                    </div>
                    <label
                        class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl border p-3"
                    >
                        <input
                            v-model="form.active"
                            type="checkbox"
                            class="mt-1 size-4 accent-primary"
                        />
                        <span>
                            <span class="block text-sm font-medium"
                                >Disponível para pedidos</span
                            >
                            <span
                                class="mt-1 block text-xs text-muted-foreground"
                                >Itens indisponíveis não aparecem ao criar
                                pedidos.</span
                            >
                        </span>
                    </label>
                    <p
                        v-if="form.errors.active"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ form.errors.active }}
                    </p>
                </section>
                <Button
                    type="submit"
                    class="h-11 w-full rounded-xl"
                    :disabled="form.processing"
                >
                    {{
                        form.processing
                            ? 'Salvando...'
                            : product
                              ? 'Salvar alterações'
                              : 'Salvar item'
                    }}
                </Button>
                <Button
                    v-if="product"
                    type="button"
                    variant="outline"
                    class="h-11 w-full rounded-xl text-destructive hover:text-destructive"
                    @click="deleteDialogOpen = true"
                >
                    Excluir item
                </Button>
            </aside>
        </form>

        <Dialog
            :open="deleteDialogOpen"
            @update:open="deleteDialogOpen = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Excluir {{ product?.name }}?</DialogTitle>
                    <DialogDescription>
                        O item sairá do cardápio e não poderá ser incluído em
                        novos pedidos. Os pedidos anteriores continuarão
                        registrados.
                    </DialogDescription>
                </DialogHeader>
                <p
                    v-if="deleteError"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ deleteError }}
                </p>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="deleteForm.processing"
                        @click="deleteDialogOpen = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="deleteItem"
                    >
                        {{
                            deleteForm.processing
                                ? 'Excluindo...'
                                : 'Excluir item'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
