<script setup lang="ts">
import { Truck } from '@lucide/vue';
import CourierPerformance from '@/components/reports/CourierPerformance.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { CourierReport } from '@/types/reports';

defineProps<{ report: CourierReport; from: string; to: string }>();

function date(value: string): string {
    return new Date(value).toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="outline" class="shrink-0 gap-2">
                <Truck class="size-4" />
                Relatório dos motoboys
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-5xl">
            <DialogHeader class="pr-6">
                <DialogTitle>Relatório dos motoboys</DialogTitle>
                <DialogDescription>
                    Entregas finalizadas de {{ date(from) }} até {{ date(to) }}.
                    Usa o período aplicado na página de Relatórios.
                </DialogDescription>
            </DialogHeader>
            <CourierPerformance :report="report" />
        </DialogContent>
    </Dialog>
</template>
