<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TeamInvitationAlert from '@/components/TeamInvitationAlert.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { redirect as googleLoginRedirect } from '@/routes/google';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import type { TeamInvitationContext } from '@/types';

defineOptions({
    layout: {
        title: 'Bem-vindo de volta',
        description: 'Entre com seus dados para acessar sua área de trabalho.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    teamInvitation?: TeamInvitationContext | null;
    googleAuthError?: string | null;
}>();
</script>

<template>
    <Head title="Entrar" />

    <div
        v-if="status"
        role="status"
        class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
    >
        Sua senha foi atualizada. Entre com sua nova senha.
    </div>

    <TeamInvitationAlert
        v-if="teamInvitation"
        :invitation="teamInvitation"
        action="Entrar"
    />

    <div class="grid gap-3">
        <a
            :href="googleLoginRedirect().url"
            class="flex h-11 w-full items-center justify-center gap-3 rounded-xl border border-border bg-background text-sm font-semibold transition hover:bg-muted"
            data-test="google-login-button"
        >
            <svg aria-hidden="true" viewBox="0 0 48 48" class="h-5 w-5">
                <path
                    fill="#4285F4"
                    d="M43.6 24.5c0-1.4-.1-2.8-.4-4.1H24v7.8h11a9.4 9.4 0 0 1-4.1 6.2v5.1h6.6c3.9-3.6 6.1-8.8 6.1-15Z"
                />
                <path
                    fill="#34A853"
                    d="M24 44c5.5 0 10.1-1.8 13.5-4.9l-6.6-5.1c-1.8 1.2-4 1.9-6.9 1.9-5.3 0-9.8-3.6-11.4-8.4H5.8v5.3A20 20 0 0 0 24 44Z"
                />
                <path
                    fill="#FBBC05"
                    d="M12.6 27.5a12 12 0 0 1 0-7v-5.3H5.8a20 20 0 0 0 0 17.6l6.8-5.3Z"
                />
                <path
                    fill="#EA4335"
                    d="M24 12c3 0 5.7 1 7.8 3l5.8-5.8A19.4 19.4 0 0 0 24 4 20 20 0 0 0 5.8 15.2l6.8 5.3C14.2 15.6 18.7 12 24 12Z"
                />
            </svg>
            Entrar com Google
        </a>
        <p
            v-if="googleAuthError"
            role="alert"
            class="text-center text-sm text-destructive"
        >
            {{ googleAuthError }}
        </p>
    </div>

    <div class="relative my-6">
        <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-border" />
        </div>
        <div class="relative flex justify-center text-xs uppercase">
            <span class="bg-background px-2 text-muted-foreground"
                >ou entre com e-mail e senha</span
            >
        </div>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="voce@exemplo.com"
                    inputmode="email"
                    autocapitalize="none"
                    spellcheck="false"
                    :aria-invalid="Boolean(errors.email)"
                    :aria-describedby="
                        errors.email ? 'login-email-error' : undefined
                    "
                />
                <InputError id="login-email-error" :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Senha</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs font-medium text-[#356b52] underline-offset-4 hover:underline dark:text-emerald-300"
                        :tabindex="5"
                    >
                        Esqueceu a senha?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Digite sua senha"
                    :aria-invalid="Boolean(errors.password)"
                    :aria-describedby="
                        errors.password ? 'login-password-error' : undefined
                    "
                />
                <InputError
                    id="login-password-error"
                    :message="errors.password"
                />
            </div>

            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="flex items-center gap-2 text-sm font-normal text-muted-foreground"
                >
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Manter conectado neste dispositivo</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-1 h-11 w-full rounded-xl bg-[#24533f] text-white hover:bg-[#1b4432]"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Entrar na minha conta
            </Button>
        </div>

        <div
            class="border-t border-border pt-5 text-center text-sm text-muted-foreground"
        >
            Ainda não tem acesso?
            <TextLink
                :href="
                    register({
                        query: {
                            invitation: teamInvitation?.code,
                        },
                    })
                "
                :tabindex="5"
                data-test="register-link"
            >
                Criar minha conta
            </TextLink>
        </div>
    </Form>
</template>
