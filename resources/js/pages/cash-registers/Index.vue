<script setup lang="ts">
import { Head, router, useHttp, usePage } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    Banknote,
    CircleDollarSign,
    Eye,
    Landmark,
    LockKeyhole,
    WalletCards,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import MetricCard from '@/components/workspace/MetricCard.vue';
import PageHeader from '@/components/workspace/PageHeader.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { close, index, open } from '@/routes/cash-registers';
import { store as storeMovement } from '@/routes/cash-registers/movements';
import type { Team } from '@/types';

type CashRegister = {
    id: number;
    openedBy: string;
    openedAt: string;
    openingBalance: string;
    expectedCash: string;
    totalSales: string;
    supplies: string;
    withdrawals: string;
    sales: { method: string; label: string; total: string }[];
    movements: {
        id: number;
        type: 'suprimento' | 'sangria';
        typeLabel: string;
        amount: string;
        description: string;
        userName: string;
        createdAt: string | null;
    }[];
};

type ClosedRegister = CashRegister & {
    closedBy: string | null;
    closedAt: string | null;
    closingBalance: string | null;
    difference: string | null;
};

const props = defineProps<{
    cashRegister: CashRegister | null;
    recentRegisters: ClosedRegister[];
    currentTeam?: Team | null;
}>();

const page = usePage();
const currentTeam = computed(
    () => props.currentTeam ?? (page.props.currentTeam as Team | null),
);
const pageUrl = computed(() =>
    currentTeam.value ? index(currentTeam.value.slug).url : '/',
);
const openingForm = useHttp({ opening_balance: '' });
const movementForm = useHttp<{
    type: 'suprimento' | 'sangria';
    amount: string;
    description: string;
}>({ type: 'suprimento', amount: '', description: '' });
const closingForm = useHttp({ closing_balance: '' });
const movementDialogOpen = ref(false);
const closingDialogOpen = ref(false);
const selectedRegisterId = ref<number | null>(null);
const selectedRegister = computed(
    () =>
        props.recentRegisters.find(
            (register) => register.id === selectedRegisterId.value,
        ) ?? null,
);
const openingServerError = computed(
    () => (openingForm.errors as Record<string, string>).cash_register,
);

const currency = new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
});

function money(value: string | null): string {
    return currency.format(Number(value ?? 0));
}

function dateTime(value: string | null): string {
    if (!value) return '—';

    return new Intl.DateTimeFormat('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function refresh(): void {
    router.reload({ only: ['cashRegister', 'recentRegisters'] });
}

function submitOpening(): void {
    openingForm.post(open().url, {
        onSuccess: () => {
            openingForm.reset();
            refresh();
        },
    });
}

function openMovement(type: 'suprimento' | 'sangria'): void {
    movementForm.reset();
    movementForm.type = type;
    movementDialogOpen.value = true;
}

function submitMovement(): void {
    if (!props.cashRegister) return;

    movementForm.post(storeMovement(props.cashRegister.id).url, {
        onSuccess: () => {
            movementDialogOpen.value = false;
            movementForm.reset();
            refresh();
        },
    });
}

function submitClosing(): void {
    if (!props.cashRegister) return;

    closingForm.patch(close(props.cashRegister.id).url, {
        onSuccess: () => {
            closingDialogOpen.value = false;
            closingForm.reset();
            refresh();
        },
    });
}

function showRegisterDetails(register: ClosedRegister): void {
    selectedRegisterId.value = register.id;
}

defineOptions({ layout: () => ({ breadcrumbs: [] }) });
</script>

<template>
    <Head title="Caixa" />

    <div class="workspace-page">
        <PageHeader
            title="Caixa"
            eyebrow="As contas da sua operação"
            description="Acompanhe cada entrada, organize as movimentações e feche o turno com clareza."
        />

        <section
            v-if="!cashRegister"
            class="grid gap-4 lg:grid-cols-[1fr_0.8fr]"
        >
            <form class="workspace-panel p-5" @submit.prevent="submitOpening">
                <div class="flex items-center gap-3">
                    <span
                        class="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary"
                    >
                        <Landmark class="size-5" />
                    </span>
                    <div>
                        <h2 class="font-semibold">Abrir caixa</h2>
                        <p class="text-sm text-muted-foreground">
                            Informe o dinheiro disponível no início do turno.
                        </p>
                    </div>
                </div>
                <div class="mt-5 max-w-sm space-y-2">
                    <Label for="opening-balance">Saldo inicial</Label>
                    <Input
                        id="opening-balance"
                        v-model="openingForm.opening_balance"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="0,00"
                        required
                    />
                    <p
                        v-if="openingForm.errors.opening_balance"
                        class="text-sm text-destructive"
                    >
                        {{ openingForm.errors.opening_balance }}
                    </p>
                    <p
                        v-if="openingServerError"
                        class="text-sm text-destructive"
                    >
                        {{ openingServerError }}
                    </p>
                </div>
                <Button
                    type="submit"
                    class="mt-5"
                    :disabled="openingForm.processing"
                >
                    {{ openingForm.processing ? 'Abrindo...' : 'Abrir caixa' }}
                </Button>
            </form>

            <div class="rounded-2xl border bg-muted/30 p-5">
                <h2 class="font-semibold">Como funciona</h2>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    As vendas finalizadas durante o turno entram neste caixa.
                    Suprimentos e sangrias ajustam o dinheiro esperado no
                    fechamento.
                </p>
            </div>
        </section>

        <template v-else>
            <section
                class="workspace-panel flex flex-wrap items-center justify-between gap-4 border-primary/20 bg-secondary/40 p-5"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-emerald-500"></span>
                        <h2 class="font-semibold">Caixa aberto</h2>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Aberto por {{ cashRegister.openedBy }} em
                        {{ dateTime(cashRegister.openedAt) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2"
                        @click="openMovement('suprimento')"
                    >
                        <ArrowDownToLine class="size-4" /> Suprimento
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2"
                        @click="openMovement('sangria')"
                    >
                        <ArrowUpFromLine class="size-4" /> Sangria
                    </Button>
                    <Button
                        type="button"
                        class="gap-2"
                        @click="closingDialogOpen = true"
                    >
                        <LockKeyhole class="size-4" /> Fechar caixa
                    </Button>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Saldo inicial"
                    :value="money(cashRegister.openingBalance)"
                    detail="Dinheiro na abertura"
                >
                    <template #icon><Landmark class="size-4" /></template>
                </MetricCard>
                <MetricCard
                    label="Vendas do turno"
                    :value="money(cashRegister.totalSales)"
                    detail="Todas as formas de pagamento"
                >
                    <template #icon><WalletCards class="size-4" /></template>
                </MetricCard>
                <div class="workspace-stat">
                    <p class="text-sm text-muted-foreground">
                        Suprimentos / sangrias
                    </p>
                    <p class="mt-2 font-semibold text-emerald-600">
                        + {{ money(cashRegister.supplies) }}
                    </p>
                    <p class="text-sm font-semibold text-rose-600">
                        − {{ money(cashRegister.withdrawals) }}
                    </p>
                </div>
                <div class="workspace-feature p-5">
                    <p class="text-sm text-muted-foreground">
                        Dinheiro esperado
                    </p>
                    <p
                        class="mt-4 text-3xl font-semibold tracking-tight tabular-nums"
                    >
                        {{ money(cashRegister.expectedCash) }}
                    </p>
                    <p class="mt-2 text-xs text-white/60">
                        Saldo físico previsto na gaveta
                    </p>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <div class="workspace-panel p-5">
                    <div class="flex items-center gap-2">
                        <WalletCards class="size-5 text-primary" />
                        <h2 class="font-semibold">Vendas por pagamento</h2>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="sale in cashRegister.sales"
                            :key="sale.method"
                            class="rounded-xl border bg-background/40 p-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ sale.label }}
                            </p>
                            <p class="mt-1 font-semibold">
                                {{ money(sale.total) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="workspace-panel p-5">
                    <div class="flex items-center gap-2">
                        <CircleDollarSign class="size-5 text-primary" />
                        <h2 class="font-semibold">Movimentações</h2>
                    </div>
                    <div
                        v-if="cashRegister.movements.length"
                        class="mt-4 space-y-2"
                    >
                        <div
                            v-for="movement in cashRegister.movements"
                            :key="movement.id"
                            class="flex items-center justify-between gap-3 rounded-xl border p-3"
                        >
                            <div>
                                <p class="text-sm font-medium">
                                    {{ movement.description }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ movement.typeLabel }} ·
                                    {{ movement.userName }} ·
                                    {{ dateTime(movement.createdAt) }}
                                </p>
                            </div>
                            <span
                                class="font-semibold"
                                :class="
                                    movement.type === 'suprimento'
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                                >{{
                                    movement.type === 'suprimento' ? '+' : '−'
                                }}
                                {{ money(movement.amount) }}</span
                            >
                        </div>
                    </div>
                    <p
                        v-else
                        class="mt-6 text-center text-sm text-muted-foreground"
                    >
                        Nenhuma movimentação registrada.
                    </p>
                </div>
            </section>
        </template>

        <section v-if="recentRegisters.length" class="workspace-panel p-5">
            <h2 class="font-semibold">Fechamentos recentes</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="text-muted-foreground">
                        <tr>
                            <th class="pb-3 font-medium">Período</th>
                            <th class="pb-3 font-medium">Responsáveis</th>
                            <th class="pb-3 font-medium">Vendas</th>
                            <th class="pb-3 font-medium">Esperado</th>
                            <th class="pb-3 font-medium">Contado</th>
                            <th class="pb-3 font-medium">Diferença</th>
                            <th class="pb-3 font-medium">Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="register in recentRegisters"
                            :key="register.id"
                            class="border-t"
                        >
                            <td class="py-3">
                                {{ dateTime(register.openedAt) }} até
                                {{ dateTime(register.closedAt) }}
                            </td>
                            <td class="py-3">
                                {{ register.openedBy }} /
                                {{ register.closedBy }}
                            </td>
                            <td class="py-3">
                                {{ money(register.totalSales) }}
                            </td>
                            <td class="py-3">
                                {{ money(register.expectedCash) }}
                            </td>
                            <td class="py-3">
                                {{ money(register.closingBalance) }}
                            </td>
                            <td
                                class="py-3 font-medium"
                                :class="
                                    Number(register.difference) < 0
                                        ? 'text-rose-600'
                                        : 'text-emerald-600'
                                "
                            >
                                {{ money(register.difference) }}
                            </td>
                            <td class="py-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="gap-1.5"
                                    @click="showRegisterDetails(register)"
                                >
                                    <Eye class="size-4" /> Ver detalhes
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <Dialog v-model:open="movementDialogOpen">
        <DialogContent>
            <DialogHeader
                ><DialogTitle>{{
                    movementForm.type === 'suprimento'
                        ? 'Registrar suprimento'
                        : 'Registrar sangria'
                }}</DialogTitle
                ><DialogDescription
                    >Informe o valor e o motivo desta
                    movimentação.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submitMovement">
                <div class="space-y-2">
                    <Label for="movement-amount">Valor</Label
                    ><Input
                        id="movement-amount"
                        v-model="movementForm.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    />
                    <p
                        v-if="movementForm.errors.amount"
                        class="text-sm text-destructive"
                    >
                        {{ movementForm.errors.amount }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label for="movement-description">Motivo</Label
                    ><Input
                        id="movement-description"
                        v-model="movementForm.description"
                        maxlength="255"
                        required
                    />
                    <p
                        v-if="movementForm.errors.description"
                        class="text-sm text-destructive"
                    >
                        {{ movementForm.errors.description }}
                    </p>
                </div>
                <DialogFooter
                    ><Button
                        type="button"
                        variant="outline"
                        @click="movementDialogOpen = false"
                        >Cancelar</Button
                    ><Button type="submit" :disabled="movementForm.processing"
                        >Salvar</Button
                    ></DialogFooter
                >
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="closingDialogOpen">
        <DialogContent>
            <DialogHeader
                ><DialogTitle>Fechar caixa</DialogTitle
                ><DialogDescription
                    >Conte o dinheiro disponível na gaveta. O sistema calculará
                    a diferença.</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submitClosing">
                <div v-if="cashRegister" class="space-y-3">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div
                            v-for="sale in cashRegister.sales"
                            :key="sale.method"
                            class="rounded-xl border p-3"
                        >
                            <p class="text-muted-foreground">
                                {{ sale.label }}
                            </p>
                            <p class="font-semibold">{{ money(sale.total) }}</p>
                        </div>
                    </div>
                    <div
                        class="flex justify-between rounded-xl bg-muted/40 p-3 text-sm"
                    >
                        <span>Vendas do turno</span>
                        <strong>{{ money(cashRegister.totalSales) }}</strong>
                    </div>
                    <div
                        class="flex justify-between rounded-xl bg-muted/40 p-3 text-sm"
                    >
                        <span>Dinheiro esperado na gaveta</span>
                        <strong>{{ money(cashRegister.expectedCash) }}</strong>
                    </div>
                </div>
                <div class="space-y-2">
                    <Label for="closing-balance">Dinheiro contado</Label
                    ><Input
                        id="closing-balance"
                        v-model="closingForm.closing_balance"
                        type="number"
                        step="0.01"
                        required
                    />
                    <p
                        v-if="closingForm.errors.closing_balance"
                        class="text-sm text-destructive"
                    >
                        {{ closingForm.errors.closing_balance }}
                    </p>
                </div>
                <DialogFooter
                    ><Button
                        type="button"
                        variant="outline"
                        @click="closingDialogOpen = false"
                        >Cancelar</Button
                    ><Button type="submit" :disabled="closingForm.processing"
                        >Confirmar fechamento</Button
                    ></DialogFooter
                >
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="selectedRegister !== null"
        @update:open="selectedRegisterId = null"
    >
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle
                    >Fechamento #{{ selectedRegister?.id }}</DialogTitle
                >
                <DialogDescription v-if="selectedRegister">
                    {{ dateTime(selectedRegister.openedAt) }} até
                    {{ dateTime(selectedRegister.closedAt) }} · Aberto por
                    {{ selectedRegister.openedBy }} e fechado por
                    {{ selectedRegister.closedBy }}.
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedRegister" class="space-y-5">
                <section>
                    <h3 class="mb-2 text-sm font-semibold">
                        Vendas por forma de pagamento
                    </h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div
                            v-for="sale in selectedRegister.sales"
                            :key="sale.method"
                            class="rounded-xl border p-3"
                        >
                            <p class="text-muted-foreground">
                                {{ sale.label }}
                            </p>
                            <p class="font-semibold">{{ money(sale.total) }}</p>
                        </div>
                    </div>
                    <div
                        class="mt-2 flex justify-between rounded-xl bg-muted/40 p-3 text-sm"
                    >
                        <span>Total de vendas</span>
                        <strong>{{
                            money(selectedRegister.totalSales)
                        }}</strong>
                    </div>
                </section>

                <section class="grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl border p-3">
                        <p class="text-muted-foreground">Saldo inicial</p>
                        <p class="font-semibold">
                            {{ money(selectedRegister.openingBalance) }}
                        </p>
                    </div>
                    <div class="rounded-xl border p-3">
                        <p class="text-muted-foreground">Dinheiro esperado</p>
                        <p class="font-semibold">
                            {{ money(selectedRegister.expectedCash) }}
                        </p>
                    </div>
                    <div class="rounded-xl border p-3">
                        <p class="text-muted-foreground">Dinheiro contado</p>
                        <p class="font-semibold">
                            {{ money(selectedRegister.closingBalance) }}
                        </p>
                    </div>
                    <div class="rounded-xl border p-3">
                        <p class="text-muted-foreground">Diferença</p>
                        <p
                            class="font-semibold"
                            :class="
                                Number(selectedRegister.difference) < 0
                                    ? 'text-rose-600'
                                    : 'text-emerald-600'
                            "
                        >
                            {{ money(selectedRegister.difference) }}
                        </p>
                    </div>
                </section>

                <section>
                    <h3 class="mb-2 text-sm font-semibold">
                        Movimentações em dinheiro
                    </h3>
                    <p class="mb-2 text-sm text-muted-foreground">
                        Suprimentos: {{ money(selectedRegister.supplies) }} ·
                        Sangrias: {{ money(selectedRegister.withdrawals) }}
                    </p>
                    <div
                        v-for="movement in selectedRegister.movements"
                        :key="movement.id"
                        class="mb-2 flex justify-between gap-3 rounded-xl border p-3 text-sm"
                    >
                        <div>
                            <p class="font-medium">
                                {{ movement.description }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ movement.typeLabel }} ·
                                {{ movement.userName }} ·
                                {{ dateTime(movement.createdAt) }}
                            </p>
                        </div>
                        <strong>{{ money(movement.amount) }}</strong>
                    </div>
                    <p
                        v-if="!selectedRegister.movements.length"
                        class="text-sm text-muted-foreground"
                    >
                        Nenhuma movimentação registrada.
                    </p>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>
