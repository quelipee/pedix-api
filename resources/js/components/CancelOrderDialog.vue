<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Ban } from '@lucide/vue';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { cancel as cancelOrder } from '@/routes/orders';
import type { OrderDetails } from '@/types/orders';

const props = defineProps<{ order: OrderDetails | null }>();
const emit = defineEmits<{ close: []; cancelled: [] }>();
const form = useHttp<{ reason: string }>({ reason: '' });

watch(
    () => props.order?.id,
    () => {
        form.reason = '';
        form.clearErrors();
    },
);

function handleOpenChange(open: boolean): void {
    if (!open && !form.processing) emit('close');
}

function submit(): void {
    if (!props.order || form.processing) return;

    form.patch(cancelOrder(props.order.id).url, {
        onSuccess: () => emit('cancelled'),
    });
}
</script>

<template>
    <Dialog :open="order !== null" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Cancelar pedido #{{ order?.id }}</DialogTitle>
                <DialogDescription>
                    O pedido sairá do painel ativo e continuará disponível no
                    histórico como cancelado.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="cancellation-reason">Motivo do cancelamento</Label>
                    <textarea
                        id="cancellation-reason"
                        v-model="form.reason"
                        rows="3"
                        maxlength="500"
                        class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50"
                        placeholder="Ex.: cliente desistiu do pedido (opcional)"
                    />
                    <p class="text-xs text-muted-foreground">
                        {{ form.reason.length }}/500 caracteres
                    </p>
                    <p v-if="form.errors.reason" class="text-sm text-destructive">
                        {{ form.errors.reason }}
                    </p>
                    <p v-if="form.errors.order" class="text-sm text-destructive">
                        {{ form.errors.order }}
                    </p>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <Button type="button" variant="outline" :disabled="form.processing" @click="emit('close')">
                        Voltar
                    </Button>
                    <Button type="submit" variant="destructive" class="gap-2" :disabled="form.processing">
                        <Ban class="size-4" />
                        {{ form.processing ? 'Cancelando...' : 'Confirmar cancelamento' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
