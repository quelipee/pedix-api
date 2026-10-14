<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    Phone,
    Plus,
    Search,
    ShoppingBag,
    Trophy,
    UserRound,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CustomerCreateDialog from '@/components/customers/CustomerCreateDialog.vue';
import CustomerDetailsDialog from '@/components/customers/CustomerDetailsDialog.vue';
import {
    index as customersIndex,
    show as showCustomer,
} from '@/routes/customers';
import type { Customer, Team } from '@/types';

const props = defineProps<{
    customers: {
        data: Customer[];
        currentPage: number;
        lastPage: number;
        total: number;
    };
    filters: { q: string };
}>();

const page = usePage();
const search = ref(props.filters.q);
const createDialogOpen = ref(false);
const selectedCustomer = ref<Customer | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | null = null;
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const indexUrl = computed(() =>
    currentTeam.value ? customersIndex(currentTeam.value.slug).url : '/',
);
const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
});

function customerUrl(customer: Customer): string {
    return currentTeam.value
        ? showCustomer({
              current_team: currentTeam.value.slug,
              customer: customer.id,
          }).url
        : '/';
}

const selectedCustomerUrl = computed(() =>
    selectedCustomer.value ? customerUrl(selectedCustomer.value) : null,
);

function customerCreated(): void {
    createDialogOpen.value = false;
    router.reload({ only: ['customers'] });
}

function visit(pageNumber = 1): void {
    router.get(
        indexUrl.value,
        { q: search.value.trim() || undefined, page: pageNumber },
        { preserveState: true, replace: true },
    );
}

watch(search, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => visit(), 300);
});

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});

defineOptions({ layout: () => ({ breadcrumbs: [], showCreateOrder: true }) });
</script>

<template>
    <Head title="Clientes" />
    <CustomerCreateDialog
        :open="createDialogOpen"
        @close="createDialogOpen = false"
        @created="customerCreated"
    />
    <CustomerDetailsDialog
        :open="selectedCustomer !== null"
        :url="selectedCustomerUrl"
        @close="selectedCustomer = null"
    />

    <div
        class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6 xl:p-8"
    >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Clientes
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Cadastre clientes e acompanhe o histórico de compras.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                @click="createDialogOpen = true"
            >
                <Plus class="size-4" /> Novo cliente
            </button>
        </header>

        <section class="rounded-2xl border bg-card p-4 shadow-sm sm:p-5">
            <div>
                <label class="relative flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="search"
                        class="h-10 w-full rounded-md border border-input bg-background pr-3 pl-10 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        placeholder="Buscar por nome ou telefone"
                        aria-label="Buscar clientes por nome ou telefone"
                    />
                </label>
            </div>
        </section>

        <section
            v-if="customers.data.length"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <button
                v-for="customer in customers.data"
                :key="customer.id"
                type="button"
                class="group rounded-2xl border bg-card p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                @click="selectedCustomer = customer"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"
                            ><UserRound class="size-5"
                        /></span>
                        <div class="min-w-0">
                            <h2 class="truncate font-semibold">
                                {{ customer.name }}
                            </h2>
                            <p
                                class="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Phone class="size-3.5" />{{ customer.phone }}
                            </p>
                        </div>
                    </div>
                    <span
                        class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium"
                        >{{ customer.ordersCount ?? 0 }} pedidos</span
                    >
                </div>
                <div
                    class="mt-5 grid gap-3 border-t pt-4 text-sm sm:grid-cols-2"
                >
                    <div>
                        <p
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Trophy class="size-3.5" />Mais pedido
                        </p>
                        <p class="mt-1 font-medium">
                            {{
                                customer.popularProduct?.name ??
                                'Sem compras finalizadas'
                            }}
                        </p>
                        <p
                            v-if="customer.popularProduct"
                            class="text-xs text-muted-foreground"
                        >
                            {{ customer.popularProduct.quantity }} unidades
                        </p>
                    </div>
                    <div>
                        <p
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <CalendarClock class="size-3.5" />Último pedido
                        </p>
                        <p class="mt-1 font-medium">
                            {{
                                customer.lastOrderAt
                                    ? dateFormatter.format(
                                          new Date(customer.lastOrderAt),
                                      )
                                    : 'Nenhum pedido'
                            }}
                        </p>
                    </div>
                </div>
            </button>
        </section>

        <section
            v-else
            class="grid min-h-72 place-items-center rounded-2xl border border-dashed p-8 text-center"
        >
            <div>
                <span
                    class="mx-auto grid size-12 place-items-center rounded-xl bg-muted"
                    ><ShoppingBag class="size-5 text-muted-foreground"
                /></span>
                <h2 class="mt-4 font-semibold">Nenhum cliente encontrado</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Cadastre o primeiro cliente ou altere a busca.
                </p>
            </div>
        </section>

        <nav
            v-if="customers.lastPage > 1"
            class="flex items-center justify-between gap-4"
            aria-label="Paginação dos clientes"
        >
            <button
                class="rounded-md border px-4 py-2 text-sm disabled:opacity-50"
                :disabled="customers.currentPage === 1"
                @click="visit(customers.currentPage - 1)"
            >
                Anterior
            </button>
            <span class="text-sm text-muted-foreground"
                >Página {{ customers.currentPage }} de
                {{ customers.lastPage }}</span
            >
            <button
                class="rounded-md border px-4 py-2 text-sm disabled:opacity-50"
                :disabled="customers.currentPage === customers.lastPage"
                @click="visit(customers.currentPage + 1)"
            >
                Próxima
            </button>
        </nav>
    </div>
</template>
