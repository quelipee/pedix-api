<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    products?: { id: number; name: string; price: string }[];
    showCreateOrder?: boolean;
    fitViewport?: boolean;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    showCreateOrder: true,
    fitViewport: false,
});
</script>

<template>
    <AppShell
        variant="header"
        class="pedix-workspace"
        :class="{ 'workspace-fit-viewport': fitViewport }"
    >
        <AppHeader
            :breadcrumbs="breadcrumbs"
            :products="products"
            :show-create-order="showCreateOrder"
        />
        <AppContent variant="header">
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
