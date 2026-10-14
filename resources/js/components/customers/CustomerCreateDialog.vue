<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { store as storeCustomer } from '@/routes/customers';

defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: []; created: [] }>();

const emptyAddress = () => ({
    postal_code: '',
    street: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
});

const form = useHttp(storeCustomer(), {
    name: '',
    phone: '',
    address: emptyAddress(),
});

function clearForm(): void {
    form.name = '';
    form.phone = '';
    form.address = emptyAddress();
    form.clearErrors();
}

function handleOpenChange(open: boolean): void {
    if (!open && !form.processing) {
        emit('close');
    }
}

function submit(): void {
    form.post(storeCustomer().url, {
        onSuccess: () => {
            clearForm();
            emit('created');
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Novo cliente</DialogTitle>
                <DialogDescription>
                    Cadastre os dados usados para identificar o cliente e fazer
                    entregas.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="modal-customer-name">Nome</Label>
                        <input
                            id="modal-customer-name"
                            v-model="form.name"
                            maxlength="120"
                            autocomplete="name"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="Ex.: Maria Souza"
                        />
                        <p
                            v-if="form.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="modal-customer-phone">Telefone</Label>
                        <input
                            id="modal-customer-phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="30"
                            autocomplete="tel"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="(11) 99999-9999"
                        />
                        <p
                            v-if="form.errors.phone"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.phone }}
                        </p>
                    </div>
                </div>

                <div class="border-t pt-5">
                    <h3 class="font-semibold">Endereço</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Endereço padrão para pedidos de entrega.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="modal-postal-code">CEP</Label>
                        <input
                            id="modal-postal-code"
                            v-model="form.address.postal_code"
                            maxlength="9"
                            inputmode="numeric"
                            autocomplete="postal-code"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="00000-000"
                        />
                        <p
                            v-if="form.errors['address.postal_code']"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors['address.postal_code'] }}
                        </p>
                    </div>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label for="modal-street">Rua</Label>
                        <input
                            id="modal-street"
                            v-model="form.address.street"
                            maxlength="255"
                            autocomplete="address-line1"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        />
                        <p
                            v-if="form.errors['address.street']"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors['address.street'] }}
                        </p>
                    </div>
                    <div class="grid gap-2 sm:col-span-1">
                        <Label for="modal-number">Número</Label>
                        <input
                            id="modal-number"
                            v-model="form.address.number"
                            maxlength="20"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        />
                        <p
                            v-if="form.errors['address.number']"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors['address.number'] }}
                        </p>
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="modal-neighborhood">Bairro</Label>
                        <input
                            id="modal-neighborhood"
                            v-model="form.address.neighborhood"
                            maxlength="120"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        />
                        <p
                            v-if="form.errors['address.neighborhood']"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors['address.neighborhood'] }}
                        </p>
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="modal-city">Cidade</Label>
                        <input
                            id="modal-city"
                            v-model="form.address.city"
                            maxlength="120"
                            autocomplete="address-level2"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                        />
                        <p
                            v-if="form.errors['address.city']"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors['address.city'] }}
                        </p>
                    </div>
                    <div class="grid gap-2 sm:col-span-1">
                        <Label for="modal-state">UF</Label>
                        <input
                            id="modal-state"
                            v-model="form.address.state"
                            maxlength="2"
                            autocomplete="address-level1"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm uppercase outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="SP"
                        />
                        <p
                            v-if="form.errors['address.state']"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors['address.state'] }}
                        </p>
                    </div>
                    <div class="grid gap-2 sm:col-span-1">
                        <Label for="modal-complement">Complemento</Label>
                        <input
                            id="modal-complement"
                            v-model="form.address.complement"
                            maxlength="120"
                            autocomplete="address-line2"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="Opcional"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t pt-5">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="emit('close')"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        class="gap-2"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" />
                        {{
                            form.processing
                                ? 'Salvando...'
                                : 'Cadastrar cliente'
                        }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
