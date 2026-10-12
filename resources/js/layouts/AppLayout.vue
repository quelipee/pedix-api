<script setup lang="ts">
import AppLayout from '@/layouts/app/AppHeaderLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    products?: { id: number; name: string; price: string }[];
    showCreateOrder?: boolean;
}>();
</script>

<template>
    <AppLayout
        :breadcrumbs="breadcrumbs"
        :products="products"
        :show-create-order="showCreateOrder"
    >
        <slot />
    </AppLayout>
</template>
