<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { Bike, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { availability, store } from '@/routes/couriers';
import type { Courier } from '@/types/orders';

defineProps<{ couriers: Courier[] }>();

const open = defineModel<boolean>('open', { required: true });
const createForm = useHttp({ name: '', phone: '' });
const changingCourierId = ref<number | null>(null);
const actionError = ref('');

function createCourier(): void {
    actionError.value = '';
    createForm.post(store().url, {
        onSuccess: () => {
            createForm.reset();
            router.reload({ only: ['couriers'] });
        },
    });
}

function toggleCourier(courier: Courier): void {
    changingCourierId.value = courier.id;
    actionError.value = '';
    const form = useHttp({ active: !courier.active });
    form.patch(availability(courier.id).url, {
        onSuccess: () => router.reload({ only: ['couriers'] }),
        onError: () => {
            actionError.value = 'Não foi possível alterar o motoboy.';
        },
        onFinish: () => {
            changingCourierId.value = null;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[88vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Motoboys</DialogTitle>
                <DialogDescription>
                    Cadastre a equipe de entrega e controle quem pode receber
                    novos pedidos.
                </DialogDescription>
            </DialogHeader>

            <form
                class="grid gap-3 rounded-xl border bg-muted/30 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                @submit.prevent="createCourier"
            >
                <div class="space-y-1.5">
                    <Label for="courier-name">Nome</Label>
                    <Input
                        id="courier-name"
                        v-model="createForm.name"
                        placeholder="Ex.: João Silva"
                        required
                    />
                    <p
                        v-if="createForm.errors.name"
                        class="text-xs text-destructive"
                    >
                        {{ createForm.errors.name }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="courier-phone">Telefone</Label>
                    <Input
                        id="courier-phone"
                        v-model="createForm.phone"
                        placeholder="(11) 99999-0000"
                        required
                    />
                    <p
                        v-if="createForm.errors.phone"
                        class="text-xs text-destructive"
                    >
                        {{ createForm.errors.phone }}
                    </p>
                </div>
                <Button
                    type="submit"
                    class="gap-2"
                    :disabled="createForm.processing"
                >
                    <Plus class="size-4" />
                    {{ createForm.processing ? 'Salvando...' : 'Cadastrar' }}
                </Button>
            </form>

            <p v-if="actionError" role="alert" class="text-sm text-destructive">
                {{ actionError }}
            </p>

            <div v-if="couriers.length" class="grid gap-2">
                <div
                    v-for="courier in couriers"
                    :key="courier.id"
                    class="flex items-center justify-between gap-3 rounded-xl border p-3"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Bike class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ courier.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ courier.phone }} ·
                                <span v-if="courier.activeDeliveriesCount">
                                    {{ courier.activeDeliveriesCount }}
                                    {{
                                        courier.activeDeliveriesCount === 1
                                            ? 'entrega em andamento'
                                            : 'entregas em andamento'
                                    }}
                                </span>
                                <span v-else-if="courier.active"
                                    >disponível</span
                                >
                                <span v-else>indisponível</span>
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="changingCourierId === courier.id"
                        @click="toggleCourier(courier)"
                    >
                        {{ courier.active ? 'Desativar' : 'Ativar' }}
                    </Button>
                </div>
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Nenhum motoboy cadastrado ainda.
            </div>
        </DialogContent>
    </Dialog>
</template>
