<script setup lang="ts">
import { Head, Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/workspace/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    index as customersIndex,
    store as storeCustomer,
} from '@/routes/customers';
import type { Team } from '@/types';

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const indexUrl = computed(() =>
    currentTeam.value ? customersIndex(currentTeam.value.slug).url : '/',
);
const form = useHttp(storeCustomer(), {
    name: '',
    phone: '',
    address: {
        postal_code: '',
        street: '',
        number: '',
        complement: '',
        neighborhood: '',
        city: '',
        state: '',
    },
});

function submit(): void {
    form.post(storeCustomer().url, {
        onSuccess: () => router.visit(indexUrl.value),
    });
}

defineOptions({ layout: () => ({ breadcrumbs: [] }) });
</script>

<template>
    <Head title="Novo cliente" />

    <div class="workspace-page">
        <PageHeader
            title="Novo cliente"
            eyebrow="Um atendimento mais próximo"
            description="Guarde os dados de contato e o endereço para facilitar os próximos pedidos."
        >
            <Button as-child variant="outline" size="icon" class="rounded-xl">
                <Link :href="indexUrl" aria-label="Voltar para clientes">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="workspace-panel space-y-5 p-5 sm:p-6">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="customer-name">Nome</Label>
                        <input
                            id="customer-name"
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
                        <Label for="customer-phone">Telefone</Label>
                        <input
                            id="customer-phone"
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
                    <h2 class="font-semibold">Endereço</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Endereço padrão para pedidos de entrega.
                    </p>
                </div>

                <div class="grid gap-5 sm:grid-cols-6">
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="postal-code">CEP</Label>
                        <input
                            id="postal-code"
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
                        <Label for="street">Rua</Label>
                        <input
                            id="street"
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
                        <Label for="number">Número</Label>
                        <input
                            id="number"
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
                        <Label for="neighborhood">Bairro</Label>
                        <input
                            id="neighborhood"
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
                        <Label for="city">Cidade</Label>
                        <input
                            id="city"
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
                        <Label for="state">UF</Label>
                        <input
                            id="state"
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
                        <Label for="complement">Complemento</Label>
                        <input
                            id="complement"
                            v-model="form.address.complement"
                            maxlength="120"
                            autocomplete="address-line2"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            placeholder="Opcional"
                        />
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-3">
                <Button as-child variant="outline"
                    ><Link :href="indexUrl">Cancelar</Link></Button
                >
                <Button type="submit" class="gap-2" :disabled="form.processing">
                    <Save class="size-4" />
                    {{ form.processing ? 'Salvando...' : 'Cadastrar cliente' }}
                </Button>
            </div>
        </form>
    </div>
</template>
