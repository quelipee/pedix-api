<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Check, Clock3, ClipboardList } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <main
        class="min-h-svh bg-[#fbfaf7] text-[#20251f] lg:grid lg:grid-cols-[1.04fr_0.96fr] dark:bg-background dark:text-foreground"
    >
        <section
            class="relative hidden min-h-svh overflow-hidden bg-[#17372d] px-12 py-10 text-white lg:flex lg:flex-col xl:px-20"
        >
            <div
                class="pointer-events-none absolute -top-36 -right-32 size-[30rem] rounded-full border border-white/10"
            />
            <div
                class="pointer-events-none absolute -top-16 -right-12 size-[22rem] rounded-full border border-white/10"
            />
            <div
                class="pointer-events-none absolute -bottom-48 -left-32 size-[32rem] rounded-full bg-[#d98645]/15 blur-3xl"
            />
            <Link
                :href="home()"
                class="relative z-10 inline-flex w-fit items-center gap-3 rounded-full focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-2xl bg-white text-[#17372d] shadow-sm"
                    ><AppLogoIcon class="size-10"
                /></span>
                <span class="text-base font-semibold tracking-wide">{{
                    name
                }}</span>
            </Link>

            <div class="relative z-10 my-auto max-w-xl py-16">
                <span
                    class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-medium tracking-wide text-[#f2cfaa]"
                >
                    <span class="size-1.5 rounded-full bg-[#e9a45f]" /> GESTÃO
                    SIMPLES PARA O DIA A DIA
                </span>
                <h2
                    class="max-w-lg text-4xl leading-[1.12] font-semibold tracking-tight xl:text-5xl"
                >
                    Sua lanchonete organizada, pedido por pedido.
                </h2>
                <p class="mt-5 max-w-md text-base leading-7 text-white/70">
                    Acesse sua área de trabalho e mantenha a operação da casa em
                    ordem.
                </p>

                <div
                    class="mt-12 max-w-md rounded-3xl border border-white/10 bg-white/[0.07] p-5 shadow-2xl shadow-black/10 backdrop-blur-sm"
                >
                    <div class="mb-5 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold">
                                Um dia mais organizado
                            </p>
                            <p class="mt-1 text-xs text-white/55">
                                Acompanhe sua rotina de perto
                            </p>
                        </div>
                        <span
                            class="flex size-10 items-center justify-center rounded-xl bg-[#e9a45f]/15 text-[#f2cfaa]"
                            ><ClipboardList class="size-5"
                        /></span>
                    </div>
                    <div class="space-y-3">
                        <div
                            class="flex items-center gap-3 rounded-2xl bg-black/15 px-4 py-3"
                        >
                            <span
                                class="flex size-8 items-center justify-center rounded-lg bg-white/10"
                                ><Clock3 class="size-4 text-[#f2cfaa]" /></span
                            ><span class="flex-1 text-sm text-white/85"
                                >Saiba o que precisa de atenção</span
                            ><ArrowUpRight class="size-4 text-white/45" />
                        </div>
                        <div
                            class="flex items-center gap-3 rounded-2xl bg-black/15 px-4 py-3"
                        >
                            <span
                                class="flex size-8 items-center justify-center rounded-lg bg-emerald-300/15"
                                ><Check class="size-4 text-emerald-200" /></span
                            ><span class="flex-1 text-sm text-white/85"
                                >Trabalhe com mais clareza</span
                            ><span
                                class="rounded-full bg-emerald-300/10 px-2 py-1 text-[10px] font-medium text-emerald-100"
                                >NO SEU RITMO</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <p class="relative z-10 text-xs text-white/45">
                Uma ferramenta feita para simplificar sua rotina.
            </p>
        </section>

        <section
            class="flex min-h-svh flex-col justify-center px-5 py-10 sm:px-8 lg:px-12 xl:px-20"
        >
            <div class="mx-auto w-full max-w-md">
                <Link
                    :href="home()"
                    class="mb-9 inline-flex items-center gap-2.5 font-semibold lg:hidden"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-xl border bg-white text-[#17372d] shadow-sm"
                        ><AppLogoIcon class="size-8"
                    /></span>
                    {{ name }}
                </Link>
                <div class="mb-8">
                    <h1
                        v-if="title"
                        class="text-3xl font-semibold tracking-tight"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="mt-2 text-sm leading-6 text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>
                <slot />
                <p class="mt-8 text-center text-xs text-muted-foreground">
                    Acesso protegido à sua área de trabalho.
                </p>
            </div>
        </section>
    </main>
</template>
