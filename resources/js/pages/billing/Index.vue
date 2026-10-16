<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import {
    ArrowUpRight,
    Check,
    CreditCard,
    RefreshCw,
    ShieldCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { checkout, portal } from '@/routes/billing';

type Plan = {
    value: 'monthly' | 'yearly';
    name: string;
    amount: number;
    available: boolean;
    current: boolean;
};

const props = defineProps<{
    billing: {
        configured: boolean;
        canManage: boolean;
        hasSubscription: boolean;
        trialDays: number;
        status: string | null;
        trialEndsAt: string | null;
        endsAt: string | null;
        plans: Plan[];
    };
}>();

const page = usePage();
const form = useForm({ plan: 'monthly', billing: '' });
const portalForm = useForm({ billing: '' });
const money = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});
const date = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' });
const checkoutFinished = computed(
    () =>
        new URLSearchParams(page.url.split('?')[1]).get('checkout') ===
        'success',
);
const statusLabels: Record<string, string> = {
    active: 'Assinatura ativa',
    trialing: 'Período de teste',
    past_due: 'Pagamento pendente',
    incomplete: 'Pagamento em confirmação',
    incomplete_expired: 'Contratação expirada',
    canceled: 'Assinatura cancelada',
    unpaid: 'Pagamento não concluído',
    paused: 'Assinatura pausada',
};
const statusLabel = computed(() =>
    props.billing.status
        ? (statusLabels[props.billing.status] ??
          'Confira a assinatura na Stripe')
        : 'Escolha seu plano',
);

function subscribe(plan: Plan): void {
    const team = page.props.currentTeam;
    if (!team || form.processing || !plan.available) return;
    form.plan = plan.value;
    form.post(checkout(team.slug).url, { preserveScroll: true });
}

function manage(): void {
    const team = page.props.currentTeam;
    if (team) portalForm.post(portal(team.slug).url, { preserveScroll: true });
}

useIntervalFn(() => {
    if (
        checkoutFinished.value &&
        !props.billing.hasSubscription &&
        document.visibilityState === 'visible'
    ) {
        router.reload({ only: ['billing'] });
    }
}, 5000);

defineOptions({ layout: () => ({ breadcrumbs: [] }) });
</script>

<template>
    <div class="space-y-6">
        <Head title="Assinatura" />
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="workspace-eyebrow mb-2">Pedix para sua equipe</p>
                <h2 class="text-2xl font-semibold tracking-tight">
                    {{ statusLabel }}
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Uma assinatura por equipe, com os recursos para organizar
                    sua operação.
                </p>
            </div>
            <Button
                v-if="billing.canManage"
                variant="outline"
                :disabled="portalForm.processing"
                @click="manage"
            >
                <CreditCard class="size-4" /> Gerenciar assinatura
            </Button>
        </header>

        <p
            v-if="!billing.configured"
            class="rounded-xl border bg-muted/50 p-4 text-sm text-muted-foreground"
        >
            A contratação estará disponível assim que a cobrança for ativada.
        </p>
        <p
            v-if="checkoutFinished && !billing.hasSubscription"
            role="status"
            class="flex items-start gap-2 rounded-xl border border-primary/20 bg-secondary/50 p-4 text-sm"
        >
            <RefreshCw class="mt-0.5 size-4 shrink-0" /> Checkout concluído.
            Aguardando a confirmação da Stripe; esta tela será atualizada
            automaticamente.
        </p>
        <p v-if="billing.trialEndsAt" class="text-sm text-muted-foreground">
            Seu teste termina em
            {{ date.format(new Date(billing.trialEndsAt)) }}.
        </p>
        <p v-if="billing.endsAt" class="text-sm text-muted-foreground">
            A assinatura está programada para encerrar em
            {{ date.format(new Date(billing.endsAt)) }}.
        </p>
        <p
            v-if="
                form.errors.plan ||
                form.errors.billing ||
                portalForm.errors.billing
            "
            role="alert"
            class="rounded-xl bg-destructive/10 p-4 text-sm text-destructive"
        >
            {{
                form.errors.plan ||
                form.errors.billing ||
                portalForm.errors.billing
            }}
        </p>

        <section class="grid gap-4 sm:grid-cols-2" aria-label="Planos do Pedix">
            <article
                v-for="plan in billing.plans"
                :key="plan.value"
                class="relative flex flex-col rounded-2xl border p-5 sm:p-6"
                :class="
                    plan.current
                        ? 'border-primary/40 bg-secondary/30'
                        : 'bg-muted/20'
                "
            >
                <span
                    v-if="plan.current"
                    class="mb-4 w-fit rounded-full bg-secondary px-3 py-1 text-xs font-medium text-primary"
                    >Plano atual</span
                >
                <p class="font-semibold">{{ plan.name }}</p>
                <p class="mt-5 text-3xl font-semibold tracking-tight">
                    {{ money.format(plan.amount / 100)
                    }}<span class="text-sm font-normal text-muted-foreground"
                        >/{{ plan.value === 'monthly' ? 'mês' : 'ano' }}</span
                    >
                </p>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{
                        plan.value === 'monthly'
                            ? 'Renovação mensal'
                            : 'Um pagamento a cada 12 meses'
                    }}
                </p>
                <ul
                    class="my-6 grid flex-1 gap-3 text-sm text-muted-foreground"
                >
                    <li class="flex items-center gap-2">
                        <Check class="size-4 text-primary" /> Pedidos e cozinha
                        organizados
                    </li>
                    <li class="flex items-center gap-2">
                        <Check class="size-4 text-primary" /> Cardápio, clientes
                        e relatórios
                    </li>
                    <li class="flex items-center gap-2">
                        <Check class="size-4 text-primary" /> Controle de caixa
                    </li>
                    <li
                        v-if="billing.trialDays > 0"
                        class="flex items-center gap-2"
                    >
                        <Check class="size-4 text-primary" />
                        {{ billing.trialDays }} dias grátis na primeira
                        assinatura
                    </li>
                </ul>
                <Button
                    v-if="!billing.hasSubscription"
                    class="w-full"
                    :disabled="!plan.available || form.processing"
                    @click="subscribe(plan)"
                >
                    {{
                        form.processing && form.plan === plan.value
                            ? 'Abrindo checkout...'
                            : 'Escolher plano'
                    }}
                    <ArrowUpRight class="size-4" />
                </Button>
                <p v-else class="text-xs text-muted-foreground">
                    Alterações e cancelamento em Gerenciar assinatura.
                </p>
            </article>
        </section>
        <p
            v-if="billing.trialDays > 0"
            class="text-xs leading-5 text-muted-foreground"
        >
            Após os {{ billing.trialDays }} dias de teste, a cobrança é
            automática no plano escolhido. O cancelamento pode ser feito em
            Gerenciar assinatura.
        </p>
        <p class="flex items-center gap-2 text-xs text-muted-foreground">
            <ShieldCheck class="size-4 shrink-0" /> Pagamento e dados de cartão
            são tratados no checkout seguro da Stripe.
        </p>
    </div>
</template>
