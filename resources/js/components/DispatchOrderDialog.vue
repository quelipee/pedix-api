<script setup lang="ts">
import { router, useHttp } from '@inertiajs/vue3';
import { Bike } from '@lucide/vue';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { dispatch } from '@/routes/orders';
import type { Courier, OrderDetails } from '@/types/orders';

const props = defineProps<{
    order: OrderDetails | null;
    couriers: Courier[];
}>();
const emit = defineEmits<{ close: [] }>();
const form = useHttp<{ courier_id: number | null }>({ courier_id: null });
const availableCouriers = computed(() =>
    props.couriers.filter(
        (courier) =>
            courier.available || courier.id === props.order?.courier?.id,
    ),
);
const serverError = computed(
    () => (form.errors as Record<string, string>).order,
);

watch(
    () => props.order,
    (order) => {
        form.courier_id = order?.courier?.id ?? null;
    },
);

function handleOpenChange(open: boolean): void {
    if (!open) {
        form.reset();
        form.clearErrors();
        emit('close');
    }
}

function submit(): void {
    if (!props.order || form.courier_id === null) {
        return;
    }

    form.patch(dispatch(props.order.id).url, {
        onSuccess: () => {
            handleOpenChange(false);
            router.reload({ only: ['orders', 'couriers'] });
        },
    });
}
</script>

<template>
    <Dialog :open="order !== null" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        order?.courier ? 'Trocar motoboy' : 'Selecionar motoboy'
                    }}
                    #{{ order?.id }}
                </DialogTitle>
                <DialogDescription>
                    Escolha o motoboy responsável por esta entrega.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div v-if="availableCouriers.length" class="grid gap-2">
                    <label
                        v-for="courier in availableCouriers"
                        :key="courier.id"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                    >
                        <input
                            v-model="form.courier_id"
                            type="radio"
                            name="courier"
                            :value="courier.id"
                            class="size-4 accent-primary"
                        />
                        <Bike class="size-5 text-primary" />
                        <span class="min-w-0">
                            <span class="block font-medium">{{
                                courier.name
                            }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                courier.phone
                            }}</span>
                        </span>
                    </label>
                </div>
                <div
                    v-else
                    class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
                >
                    Não há motoboys disponíveis. Cadastre ou libere um motoboy
                    antes de despachar.
                </div>

                <p
                    v-if="form.errors.courier_id || serverError"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.courier_id || serverError }}
                </p>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleOpenChange(false)"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || form.courier_id === null"
                    >
                        {{
                            form.processing
                                ? 'Salvando...'
                                : order?.courier
                                  ? 'Confirmar troca'
                                  : 'Confirmar seleção'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
