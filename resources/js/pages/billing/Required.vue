<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CreditCard } from '@lucide/vue';
import { index as billingIndex } from '@/routes/billing';

const page = usePage();
</script>

<template>
    <section class="workspace-panel max-w-2xl space-y-4 p-6 sm:p-8">
        <Head title="Regularizar assinatura" />
        <CreditCard class="size-8 text-primary" />
        <h1 class="text-2xl font-semibold">Vamos regularizar sua assinatura</h1>
        <p class="text-sm leading-6 text-muted-foreground">
            Sua equipe precisa de uma assinatura ativa ou de um período de teste
            válido para usar os recursos do Pedix.
        </p>
        <Link
            v-if="
                page.props.permissions?.canManageBilling &&
                page.props.currentTeam
            "
            :href="billingIndex(page.props.currentTeam.slug).url"
            class="inline-flex rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
            >Gerenciar assinatura</Link
        >
        <p v-else class="text-sm text-muted-foreground">
            Peça ao proprietário da equipe para conferir o pagamento ou renovar
            a assinatura.
        </p>
    </section>
</template>
