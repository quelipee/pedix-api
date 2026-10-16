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
    UtensilsCrossed,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CreateOrderCard from '@/components/CreateOrderCard.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import StoreStatusControl from '@/components/StoreStatusControl.vue';
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
import { manage as manageOrders, create as createOrder } from '@/routes/orders';
import { catalog as productCatalog } from '@/routes/products';
import { index as reportsIndex } from '@/routes/reports';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        products?: { id: number; name: string; price: string }[];
        showCreateOrder?: boolean;
    }>(),
    { breadcrumbs: () => [], showCreateOrder: true },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const permissions = computed(() => page.props.permissions);
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);
const navigation = computed(() => {
    const team = page.props.currentTeam;
    if (!team) return [];

    return [
        {
            label: 'Visão geral',
            icon: LayoutGrid,
            href: dashboard(team.slug).url,
            allowed: permissions.value?.canViewDashboard,
        },
        {
            label: 'Pedidos',
            icon: ClipboardList,
            href: manageOrders(team.slug).url,
            allowed: permissions.value?.canViewOrders,
        },
        {
            label: 'Cardápio',
            icon: ShoppingBag,
            href: productCatalog(team.slug).url,
            allowed: permissions.value?.canViewProducts,
        },
        {
            label: 'Clientes',
            icon: Users,
            href: customersIndex(team.slug).url,
            allowed: permissions.value?.canViewCustomers,
        },
        {
            label: 'Relatórios',
            icon: ChartNoAxesCombined,
            href: reportsIndex(team.slug).url,
            allowed: permissions.value?.canViewReports,
        },
        {
            label: 'Caixa',
            icon: Landmark,
            href: cashRegisterIndex(team.slug).url,
            allowed: permissions.value?.canViewCashRegister,
        },
    ].filter((item) => item.allowed);
});
const hasMultipleTeams = computed(() => (page.props.teams ?? []).length > 1);
const firstName = computed(() => user.value.name.split(' ')[0]);

function isActive(href: string): boolean {
    const path = page.url.split('?')[0];
    const team = page.props.currentTeam;
    if (team && path === createOrder(team.slug).url) return false;

    return path === href || path.startsWith(`${href}/`);
}
</script>

<template>
    <header class="sticky top-0 z-40 border-b bg-card/95 backdrop-blur-xl">
        <div
            class="mx-auto flex h-18 w-full max-w-[1600px] items-center justify-between gap-3 px-4 sm:px-6 lg:px-10"
        >
            <div class="flex min-w-0 items-center gap-5">
                <Link
                    :href="dashboardUrl"
                    class="flex shrink-0 items-center gap-2.5"
                    aria-label="Ir para a visão geral"
                >
                    <span
                        class="grid size-10 place-items-center rounded-xl border border-black/8 bg-white text-[#ff7838] shadow-xs"
                    >
                        <AppLogoIcon class="size-8" />
                    </span>
                    <span class="text-xl font-semibold tracking-[-0.04em]">{{
                        page.props.name
                    }}</span>
                </Link>
                <div
                    class="hidden min-w-0 items-center gap-2 border-l pl-5 md:flex"
                >
                    <span
                        class="grid size-8 shrink-0 place-items-center rounded-lg bg-muted text-primary"
                    >
                        <UtensilsCrossed class="size-4" />
                    </span>
                    <span
                        class="max-w-44 truncate text-xs font-medium text-muted-foreground"
                    >
                        {{ page.props.currentTeam?.name ?? 'Seu restaurante' }}
                    </span>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                <StoreStatusControl v-if="page.props.currentTeam" />
                <CreateOrderCard
                    v-if="showCreateOrder && permissions?.canCreateOrder"
                    :products="products ?? []"
                />
                <div class="hidden h-7 w-px bg-border sm:block" />
                <TeamSwitcher v-if="hasMultipleTeams" :in-header="true" />
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            class="h-10 gap-2 rounded-xl px-1.5 sm:px-2"
                            aria-label="Abrir opções da conta"
                        >
                            <Avatar class="size-8 overflow-hidden rounded-full">
                                <AvatarImage
                                    v-if="user.avatar"
                                    :src="user.avatar"
                                    :alt="user.name"
                                />
                                <AvatarFallback
                                    class="rounded-full bg-secondary text-xs font-semibold text-secondary-foreground"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span
                                class="hidden text-sm font-medium sm:inline"
                                >{{ firstName }}</span
                            >
                            <ChevronDown
                                class="hidden size-3.5 text-muted-foreground sm:inline"
                            />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <nav
            aria-label="Navegação principal"
            class="workspace-navigation mx-auto flex w-full max-w-[1600px] items-center gap-1 overflow-x-auto px-4 pb-2 sm:px-6 lg:px-10"
        >
            <Link
                v-for="item in navigation"
                :key="item.href"
                :href="item.href"
                :aria-label="item.label"
                :aria-current="isActive(item.href) ? 'page' : undefined"
                class="workspace-nav-link"
            >
                <component :is="item.icon" class="size-4" />
                <span>{{ item.label }}</span>
            </Link>
        </nav>

        <div v-if="breadcrumbs.length > 1" class="border-t">
            <div
                class="mx-auto w-full max-w-[1600px] px-4 py-2 sm:px-6 lg:px-10"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </header>
</template>
