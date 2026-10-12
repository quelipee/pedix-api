<script setup lang="ts">
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { PackageOpen, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    create as createProduct,
    edit as editProduct,
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
};

const props = defineProps<{ products: Product[] }>();

const page = usePage();
const selectedCategory = ref<string | null>(null);
const searchTerm = ref('');
const updatingProductId = ref<number | null>(null);
const availabilityError = ref('');
const availabilityForm = useHttp<{
    name: string;
    category: string;
    description: string | null;
    ingredients: string[];
    price: string;
    active: boolean;
}>({
    name: '',
    category: '',
    description: null,
    ingredients: [],
    price: '',
    active: true,
});
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const createUrl = computed(() =>
    currentTeam.value ? createProduct(currentTeam.value.slug).url : '/',
);
const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

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

const selectedProducts = computed(() => {
    const products =
        productsByCategory.value.find(
            ([category]) => category === selectedCategory.value,
        )?.[1] ?? [];
    const search = searchTerm.value.trim().toLocaleLowerCase('pt-BR');

    if (!search) {
        return products;
    }

    return products.filter((product) =>
        `${product.name} ${product.description ?? ''} ${product.ingredients.join(' ')}`
            .toLocaleLowerCase('pt-BR')
            .includes(search),
    );
});

function editUrl(product: Product): string {
    return currentTeam.value
        ? editProduct({
              current_team: currentTeam.value.slug,
              product: product.id,
          }).url
        : '/';
}

function updateCategoryDialog(open: boolean): void {
    if (!open) {
        selectedCategory.value = null;
        searchTerm.value = '';
        availabilityError.value = '';
    }
}

function toggleAvailability(product: Product): void {
    if (availabilityForm.processing) {
        return;
    }

    updatingProductId.value = product.id;
    availabilityError.value = '';
    availabilityForm.name = product.name;
    availabilityForm.category = product.category;
    availabilityForm.description = product.description;
    availabilityForm.ingredients = product.ingredients;
    availabilityForm.price = product.price;
    availabilityForm.active = !product.active;

    availabilityForm.patch(updateProduct(product.id).url, {
        onSuccess: () => router.reload({ only: ['products'] }),
        onError: () => {
            availabilityError.value = `Não foi possível atualizar ${product.name}. Tente novamente.`;
        },
        onFinish: () => {
            updatingProductId.value = null;
        },
    });
}

defineOptions({
    layout: () => ({ breadcrumbs: [], showCreateOrder: true }),
});
</script>

<template>
    <Head title="Cardápio" />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 xl:p-8"
    >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Cardápio
                </h1>
                <p class="text-sm text-muted-foreground">
                    Escolha uma categoria para visualizar e gerenciar seus
                    produtos.
                </p>
            </div>
            <Link
                :href="createUrl"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
            >
                <Plus class="size-4" />
                Novo item
            </Link>
        </header>

        <section
            v-if="productsByCategory.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <button
                v-for="[category, categoryProducts] in productsByCategory"
                :key="category"
                type="button"
                class="group flex min-h-44 flex-col justify-between rounded-2xl border bg-card p-5 text-left text-card-foreground shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                @click="selectedCategory = category"
            >
                <span
                    class="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary"
                >
                    <PackageOpen class="size-5" />
                </span>
                <span class="mt-6 block w-full">
                    <span
                        class="flex items-end justify-between gap-3 font-semibold"
                    >
                        <span>{{ category }}</span>
                        <span class="text-sm font-medium text-primary"
                            >Ver itens</span
                        >
                    </span>
                    <span class="mt-2 block text-sm text-muted-foreground">
                        {{ categoryProducts.length }}
                        {{
                            categoryProducts.length === 1
                                ? 'produto cadastrado'
                                : 'produtos cadastrados'
                        }}
                    </span>
                    <span class="mt-1 block text-xs text-muted-foreground">
                        {{
                            categoryProducts.filter(({ active }) => active)
                                .length
                        }}
                        disponíveis
                    </span>
                </span>
            </button>
        </section>

        <section
            v-else
            class="flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed bg-card p-8 text-center"
        >
            <span
                class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary"
            >
                <PackageOpen class="size-6" />
            </span>
            <h2 class="mt-4 font-semibold">Nenhum item cadastrado</h2>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                Cadastre o primeiro produto para criar as categorias do seu
                cardápio.
            </p>
            <Link
                :href="createUrl"
                class="mt-4 text-sm font-medium text-primary hover:underline"
            >
                Criar primeiro item
            </Link>
        </section>

        <Dialog
            :open="selectedCategory !== null"
            @update:open="updateCategoryDialog"
        >
            <DialogContent class="max-h-[88vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ selectedCategory }}</DialogTitle>
                    <DialogDescription>
                        Visualize, edite e altere a disponibilidade dos itens
                        desta categoria.
                    </DialogDescription>
                </DialogHeader>

                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="searchTerm"
                        type="search"
                        aria-label="Buscar nesta categoria"
                        placeholder="Buscar por nome ou ingrediente"
                        class="h-10 w-full rounded-xl border border-input bg-background pr-3 pl-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                    />
                </div>

                <p
                    v-if="availabilityError"
                    role="alert"
                    class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ availabilityError }}
                </p>

                <div v-if="selectedProducts.length" class="grid gap-2.5">
                    <article
                        v-for="product in selectedProducts"
                        :key="product.id"
                        class="relative rounded-xl border bg-background/40 p-3.5 transition-colors hover:border-primary/40 hover:bg-background/70"
                    >
                        <Link
                            :href="editUrl(product)"
                            :aria-label="`Editar ${product.name}`"
                            class="absolute inset-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <div class="flex items-start justify-between gap-2">
                            <h2
                                class="min-w-0 text-sm leading-snug font-semibold"
                            >
                                {{ product.name }}
                            </h2>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    product.active
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{
                                    product.active
                                        ? 'Disponível'
                                        : 'Indisponível'
                                }}
                            </span>
                        </div>
                        <p
                            v-if="product.description"
                            class="mt-1.5 line-clamp-1 text-xs text-muted-foreground"
                        >
                            {{ product.description }}
                        </p>
                        <p
                            v-if="product.ingredients.length"
                            class="mt-1 truncate text-xs text-muted-foreground"
                            :title="product.ingredients.join(', ')"
                        >
                            {{ product.ingredients.join(' · ') }}
                        </p>
                        <div
                            class="mt-3 flex items-center justify-between gap-2"
                        >
                            <p class="text-sm font-semibold tabular-nums">
                                {{ currency.format(Number(product.price)) }}
                            </p>
                            <button
                                type="button"
                                class="relative z-10 rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors hover:bg-accent hover:text-accent-foreground disabled:cursor-wait disabled:opacity-50"
                                :disabled="availabilityForm.processing"
                                :aria-label="`${product.active ? 'Desativar' : 'Ativar'} ${product.name}`"
                                @click="toggleAvailability(product)"
                            >
                                {{
                                    updatingProductId === product.id
                                        ? 'Salvando...'
                                        : product.active
                                          ? 'Desativar'
                                          : 'Ativar'
                                }}
                            </button>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-dashed p-8 text-center"
                >
                    <Search class="mx-auto size-6 text-muted-foreground" />
                    <p class="mt-3 text-sm text-muted-foreground">
                        Nenhum item encontrado nesta busca.
                    </p>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
