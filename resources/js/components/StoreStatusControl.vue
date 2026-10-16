<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { update } from '@/routes/store-status';

const page = usePage();
const team = computed(() => page.props.currentTeam);
const canManage = computed(() => page.props.permissions?.canManageStoreStatus);
const form = useForm({ is_open: false });
const label = computed(() =>
    team.value?.isOpen ? 'Loja aberta' : 'Loja fechada',
);

function setStatus(isOpen: boolean): void {
    if (!team.value || form.processing) return;
    form.is_open = isOpen;
    form.patch(update(team.value.slug).url, { preserveScroll: true });
}
</script>

<template>
    <div>
        <DropdownMenu v-if="canManage">
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-2 rounded-xl"
                    :disabled="form.processing"
                    :aria-label="`${label}. Alterar funcionamento da loja`"
                >
                    <span
                        class="size-2 shrink-0 rounded-full"
                        :class="team?.isOpen ? 'bg-emerald-500' : 'bg-rose-500'"
                    />
                    <span class="hidden sm:inline">{{ label }}</span>
                    <span class="sm:hidden">{{
                        team?.isOpen ? 'Aberta' : 'Fechada'
                    }}</span>
                    <ChevronDown class="hidden size-3.5 sm:block" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-64">
                <DropdownMenuLabel>Funcionamento da loja</DropdownMenuLabel>
                <p
                    class="px-2 pb-2 text-xs leading-relaxed text-muted-foreground"
                >
                    Define a disponibilidade para pedidos online. O atendimento
                    interno continua disponível. Ao fechar, a numeração dos
                    próximos pedidos recomeça em 1.
                </p>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    :disabled="team?.isOpen || form.processing"
                    @select="setStatus(true)"
                >
                    <span class="size-2 rounded-full bg-emerald-500" /> Abrir
                    loja
                </DropdownMenuItem>
                <DropdownMenuItem
                    :disabled="!team?.isOpen || form.processing"
                    @select="setStatus(false)"
                >
                    <span class="size-2 rounded-full bg-rose-500" /> Fechar loja
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
        <span
            v-else
            class="flex items-center gap-2 text-xs font-medium"
            role="status"
        >
            <span
                class="size-2 rounded-full"
                :class="team?.isOpen ? 'bg-emerald-500' : 'bg-rose-500'"
            />
            {{ label }}
        </span>
        <p
            v-if="form.errors.is_open"
            class="max-w-48 text-xs text-destructive"
            role="alert"
        >
            {{ form.errors.is_open }}
        </p>
    </div>
</template>
