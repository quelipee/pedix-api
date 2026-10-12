<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Confirme seu e-mail',
        description:
            'Para proteger sua conta, confirme o endereço pelo link que enviamos para sua caixa de entrada.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Confirme seu e-mail" />

    <div
        v-if="status === 'verification-link-sent'"
        role="status"
        class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
    >
        Enviamos um novo link de confirmação para o e-mail informado no cadastro.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Reenviar e-mail de confirmação
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Sair da conta
        </TextLink>
    </Form>
</template>
