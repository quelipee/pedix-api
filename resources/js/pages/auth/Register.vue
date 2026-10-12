<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type { TeamInvitationContext } from '@/types';

defineProps<{
    passwordRules: string;
    teamInvitation?: TeamInvitationContext | null;
}>();

defineOptions({
    layout: {
        title: 'Crie sua conta',
        description: 'Preencha os dados para começar a organizar sua operação.',
    },
});
</script>

<template>
    <Head title="Criar conta" />

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Criar conta"
    />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Seu nome</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Como podemos te chamar?"
                    :aria-invalid="Boolean(errors.name)"
                    :aria-describedby="
                        errors.name ? 'register-name-error' : undefined
                    "
                />
                <InputError id="register-name-error" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="voce@exemplo.com"
                    autocapitalize="none"
                    spellcheck="false"
                    :aria-invalid="Boolean(errors.email)"
                    :aria-describedby="
                        errors.email ? 'register-email-error' : undefined
                    "
                />
                <InputError id="register-email-error" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Crie uma senha</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Use uma senha forte"
                    :passwordrules="passwordRules"
                    :aria-invalid="Boolean(errors.password)"
                    :aria-describedby="
                        errors.password
                            ? 'register-password-error'
                            : 'register-password-hint'
                    "
                />
                <InputError
                    id="register-password-error"
                    :message="errors.password"
                />
                <p
                    id="register-password-hint"
                    class="text-xs leading-5 text-muted-foreground"
                >
                    Escolha uma senha longa e exclusiva. Em produção, o sistema
                    exige uma senha forte.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirme sua senha</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Digite a senha novamente"
                    :passwordrules="passwordRules"
                    :aria-invalid="Boolean(errors.password_confirmation)"
                    :aria-describedby="
                        errors.password_confirmation
                            ? 'register-confirmation-error'
                            : undefined
                    "
                />
                <InputError
                    id="register-confirmation-error"
                    :message="errors.password_confirmation"
                />
            </div>

            <Button
                type="submit"
                class="mt-1 h-11 w-full rounded-xl bg-[#24533f] text-white hover:bg-[#1b4432]"
                tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Criar minha conta
            </Button>
        </div>

        <div
            class="border-t border-border pt-5 text-center text-sm text-muted-foreground"
        >
            Já tem uma conta?
            <TextLink
                :href="
                    teamInvitation
                        ? login.url({
                              query: {
                                  invitation: teamInvitation.code,
                              },
                          })
                        : login()
                "
                class="font-medium text-[#356b52] underline-offset-4 hover:underline dark:text-emerald-300"
                :tabindex="6"
                data-test="team-invitation-login-link"
            >
                Entrar
            </TextLink>
        </div>
    </Form>
</template>
