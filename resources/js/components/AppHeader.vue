<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    ClipboardList,
    Landmark,
    LayoutGrid,
    ChartNoAxesCombined,
    ShoppingBag,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CreateOrderCard from '@/components/CreateOrderCard.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes';
import { index as cashRegisterIndex } from '@/routes/cash-registers';
import { index as customersIndex } from '@/routes/customers';
import { manage as manageOrders } from '@/routes/orders';
import { catalog as productCatalog } from '@/routes/products';
import { index as reportsIndex } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        products?: { id: number; name: string; price: string }[];
        showCreateOrder?: boolean;
    }>(),
    {
        breadcrumbs: () => [],
        showCreateOrder: true,
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const permissions = computed(() => page.props.permissions);
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);
const ordersUrl = computed(() =>
    page.props.currentTeam
        ? manageOrders(page.props.currentTeam.slug).url
        : '/',
);
const productsUrl = computed(() =>
    page.props.currentTeam
        ? productCatalog(page.props.currentTeam.slug).url
        : '/',
);
const cashRegisterUrl = computed(() =>
    page.props.currentTeam
        ? cashRegisterIndex(page.props.currentTeam.slug).url
        : '/',
);
const customersUrl = computed(() =>
    page.props.currentTeam
        ? customersIndex(page.props.currentTeam.slug).url
        : '/',
);
const reportsUrl = computed(() =>
    page.props.currentTeam
        ? reportsIndex(page.props.currentTeam.slug).url
        : '/',
);
const hasMultipleTeams = computed(() => (page.props.teams ?? []).length > 1);
const firstName = computed(() => user.value.name.split(' ')[0]);
</script>

<template>
    <header
        class="sticky top-0 z-40 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80"
    >
        <div
            class="mx-auto flex h-16 w-full max-w-[1600px] items-center gap-4 px-4 sm:px-6 lg:px-8"
        >
            <Link
                :href="dashboardUrl"
                class="flex min-w-0 shrink-0 items-center gap-2"
                aria-label="Ir para a visão geral"
            >
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-5 fill-current" />
                </span>
                <span
                    class="hidden max-w-48 truncate text-sm font-semibold sm:block"
                >
                    {{ page.props.name }}
                </span>
            </Link>

            <nav
                aria-label="Navegação principal"
                class="flex flex-1 items-center gap-2"
            >
                <Link
                    v-if="permissions?.canViewDashboard"
                    :href="dashboardUrl"
                    aria-label="Visão geral"
                    class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium transition-colors"
                    :class="
                        page.url === dashboardUrl ||
                        page.url.startsWith(`${dashboardUrl}/`)
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    <LayoutGrid class="size-4" />
                    <span class="hidden sm:inline">Visão geral</span>
                </Link>
                <Link
                    v-if="permissions?.canViewOrders"
                    :href="ordersUrl"
                    aria-label="Gerenciar pedidos"
                    class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium transition-colors"
                    :class="
                        page.url.split('?')[0] === ordersUrl ||
                        page.url.split('?')[0].startsWith(`${ordersUrl}/`)
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    <ClipboardList class="size-4" />
                    <span class="hidden sm:inline">Pedidos</span>
                </Link>
                <Link
                    v-if="permissions?.canViewProducts"
                    :href="productsUrl"
                    aria-label="Cardápio"
                    class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium transition-colors"
                    :class="
                        page.url.split('?')[0] === productsUrl ||
                        page.url.split('?')[0].startsWith(`${productsUrl}/`)
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    <ShoppingBag class="size-4" />
                    <span class="hidden sm:inline">Cardápio</span>
                </Link>
                <Link
                    v-if="permissions?.canViewCustomers"
                    :href="customersUrl"
                    aria-label="Clientes"
                    class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium transition-colors"
                    :class="
                        page.url.split('?')[0] === customersUrl ||
                        page.url.split('?')[0].startsWith(`${customersUrl}/`)
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    <Users class="size-4" />
                    <span class="hidden lg:inline">Clientes</span>
                </Link>
                <Link
                    v-if="permissions?.canViewReports"
                    :href="reportsUrl"
                    aria-label="Relatórios"
                    class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium transition-colors"
                    :class="
                        page.url.split('?')[0] === reportsUrl
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    <ChartNoAxesCombined class="size-4" />
                    <span class="hidden xl:inline">Relatórios</span>
                </Link>
                <Link
                    v-if="permissions?.canViewCashRegister"
                    :href="cashRegisterUrl"
                    aria-label="Caixa"
                    class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium transition-colors"
                    :class="
                        page.url.split('?')[0] === cashRegisterUrl
                            ? 'bg-primary/10 text-primary'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    <Landmark class="size-4" />
                    <span class="hidden md:inline">Caixa</span>
                </Link>
                <CreateOrderCard
                    v-if="showCreateOrder && permissions?.canCreateOrder"
                    :products="products ?? []"
                />
            </nav>

            <div class="ml-auto flex shrink-0 items-center gap-2">
                <TeamSwitcher v-if="hasMultipleTeams" :in-header="true" />

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            class="h-10 gap-2 rounded-xl px-1.5 sm:pr-3 sm:pl-2"
                            aria-label="Abrir opções da conta"
                        >
                            <Avatar class="size-8 overflow-hidden rounded-lg">
                                <AvatarImage
                                    v-if="user.avatar"
                                    :src="user.avatar"
                                    :alt="user.name"
                                />
                                <AvatarFallback class="rounded-lg">
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="hidden text-sm font-medium sm:inline">
                                {{ firstName }}
                            </span>
                            <ChevronDown
                                class="hidden size-4 text-muted-foreground sm:inline"
                            />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <div v-if="breadcrumbs.length > 1" class="border-t">
            <div
                class="mx-auto w-full max-w-[1600px] px-4 py-2 sm:px-6 lg:px-8"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </header>
</template>
