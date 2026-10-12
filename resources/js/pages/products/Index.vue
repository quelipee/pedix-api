<script setup lang="ts">
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { PackageOpen, Plus, Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import { create as createProduct, edit as editProduct, update as updateProduct } from '@/routes/products';
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
const selectedCategory = ref('Todos');
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
const createUrl = computed(() => currentTeam.value ? createProduct(currentTeam.value.slug).url : '/');
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function editUrl(product: Product): string {
    return currentTeam.value
        ? editProduct({ current_team: currentTeam.value.slug, product: product.id }).url
        : '/';
}

const categories = computed(() => [
    'Todos',
    ...new Set(props.products.map((product) => product.category)),
]);

const visibleProducts = computed(() => {
    const search = searchTerm.value.trim().toLocaleLowerCase('pt-BR');

    return props.products.filter((product) =>
        (selectedCategory.value === 'Todos' || product.category === selectedCategory.value) &&
        (!search || `${product.name} ${product.description ?? ''} ${product.ingredients.join(' ')}`
            .toLocaleLowerCase('pt-BR').includes(search)),
    );
});

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

    <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 xl:p-8">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Cardápio</h1>
                <p class="text-sm text-muted-foreground">
                    Veja os itens da loja e cadastre novos produtos.
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

        <section class="rounded-2xl border bg-card text-card-foreground shadow-sm">
            <div class="space-y-4 border-b p-4 sm:p-5">
                <nav aria-label="Categorias do cardápio" class="flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="category in categories"
                        :key="category"
                        type="button"
                        class="min-h-10 shrink-0 rounded-xl border px-3 text-sm font-medium transition-colors"
                        :class="selectedCategory === category ? 'border-primary/30 bg-primary/10 text-primary' : 'border-transparent text-muted-foreground hover:bg-muted hover:text-foreground'"
                        :aria-pressed="selectedCategory === category"
                        @click="selectedCategory = category"
                    >
                        {{ category }}
                    </button>
                </nav>
                <div class="relative max-w-md">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <input
                        v-model="searchTerm"
                        type="search"
                        aria-label="Buscar item do cardápio"
                        placeholder="Buscar por nome ou ingrediente"
                        class="h-10 w-full rounded-xl border border-input bg-background pr-3 pl-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                    />
                </div>
            </div>

            <p v-if="availabilityError" role="alert" class="mx-4 mt-4 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive sm:mx-5">
                {{ availabilityError }}
            </p>

            <div v-if="visibleProducts.length" class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,260px),1fr))] gap-3 p-4 sm:p-5">
                <article
                    v-for="product in visibleProducts"
                    :key="product.id"
                    class="group relative flex flex-col rounded-xl border bg-background/40 p-3.5 transition-colors hover:border-primary/50 hover:bg-background/70"
                >
                    <Link
                        :href="editUrl(product)"
                        :aria-label="`Editar ${product.name}`"
                        class="absolute inset-0 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <div class="flex items-center justify-between gap-3">
                        <span class="min-w-0 truncate text-xs font-medium text-muted-foreground">{{ product.category }}</span>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="product.active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-muted text-muted-foreground'"
                        >
                            {{ product.active ? 'Disponível' : 'Indisponível' }}
                        </span>
                    </div>
                    <h2 class="mt-2 font-semibold leading-tight">{{ product.name }}</h2>
                    <p v-if="product.description" class="mt-1 line-clamp-2 text-sm text-muted-foreground">
                        {{ product.description }}
                    </p>
                    <p v-if="product.ingredients.length" class="mt-1 truncate text-xs text-muted-foreground" :title="product.ingredients.join(', ')">
                        {{ product.ingredients.join(' · ') }}
                    </p>
                    <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                        <p class="text-base font-semibold tabular-nums">{{ currency.format(Number(product.price)) }}</p>
                        <button
                            type="button"
                            class="relative z-10 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors hover:bg-accent hover:text-accent-foreground disabled:cursor-wait disabled:opacity-50"
                            :disabled="availabilityForm.processing"
                            :aria-label="`${product.active ? 'Desativar' : 'Ativar'} ${product.name}`"
                            @click="toggleAvailability(product)"
                        >
                            {{ updatingProductId === product.id ? 'Salvando...' : product.active ? 'Desativar' : 'Ativar' }}
                        </button>
                    </div>
                </article>
            </div>

            <div v-else class="flex min-h-72 flex-col items-center justify-center p-8 text-center">
                <span class="grid size-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                    <PackageOpen class="size-6" />
                </span>
                <h2 class="mt-4 font-semibold">Nenhum item encontrado</h2>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    {{ products.length ? 'Tente outra categoria ou busca.' : 'Cadastre o primeiro item do seu cardápio.' }}
                </p>
                <Link v-if="!products.length" :href="createUrl" class="mt-4 text-sm font-medium text-primary hover:underline">
                    Criar primeiro item
                </Link>
            </div>
        </section>
    </div>
</template>
