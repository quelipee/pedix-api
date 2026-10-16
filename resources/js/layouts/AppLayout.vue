<script setup lang="ts">
import AppLayout from '@/layouts/app/AppHeaderLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [], showCreateOrder = true } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    products?: { id: number; name: string; price: string }[];
    showCreateOrder?: boolean;
    fitViewport?: boolean;
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :products="products"
        :show-create-order="showCreateOrder"
        :fit-viewport="fitViewport"
    >
        <slot />
    </AppLayout>
</template>
