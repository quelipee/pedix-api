<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { create as createOrder } from '@/routes/orders';

const page = usePage();
const href = computed(() => {
    const team = page.props.currentTeam;

    return team ? createOrder(team.slug).url : '/';
});
const isActive = computed(() => page.url.split('?')[0] === href.value);
</script>

<template>
    <Link
        :href="href"
        aria-label="Criar pedido"
        class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl px-3 text-sm font-medium transition-colors"
        :class="
            isActive
                ? 'bg-primary/10 text-primary'
                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
        "
        :aria-current="isActive ? 'page' : undefined"
        data-test="create-order-card"
    >
        <Plus class="size-4" />
        <span class="hidden sm:inline">Criar pedido</span>
    </Link>
</template>
