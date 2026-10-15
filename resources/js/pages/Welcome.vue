<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    Check,
    ChefHat,
    CircleDollarSign,
    ClipboardCheck,
    Clock3,
    PackageCheck,
    ShoppingBag,
    Sparkles,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth.user));
const primaryUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const features = [
    {
        icon: ClipboardCheck,
        title: 'Pedidos organizados',
        description:
            'Acompanhe cada pedido do atendimento até a finalização, sem perder nenhuma etapa.',
    },
    {
        icon: ChefHat,
        title: 'Cozinha em movimento',
        description:
            'Visualize pedidos novos, em preparo e prontos em um painel simples para a equipe.',
    },
    {
        icon: ShoppingBag,
        title: 'Cardápio sob controle',
        description:
            'Cadastre produtos, adicionais, categorias, preços e disponibilidade em poucos passos.',
    },
    {
        icon: Users,
        title: 'Clientes mais próximos',
        description:
            'Guarde contatos e endereços e consulte o histórico de compras de cada cliente.',
    },
    {
        icon: CircleDollarSign,
        title: 'Caixa sem surpresa',
        description:
            'Registre abertura, suprimentos, sangrias e fechamento com as vendas por pagamento.',
    },
    {
        icon: BarChart3,
        title: 'Decisões com dados',
        description:
            'Consulte faturamento, ticket médio, modalidades e os produtos que mais vendem.',
    },
];

const steps = [
    'Registre o pedido e escolha a modalidade',
    'A cozinha acompanha e atualiza o preparo',
    'Finalize a venda e acompanhe os resultados',
];

const previewOrders = [
    { id: 48, item: 'X-Bacon + Coca-Cola', status: 'Em preparo' },
    { id: 49, item: 'X-Salada', status: 'Novo' },
    { id: 50, item: 'Batata + Sprite', status: 'Pronto' },
];
</script>

<template>
    <Head title="Gestão simples para sua lanchonete">
        <meta
            head-key="description"
            name="description"
            content="Organize pedidos, cozinha, cardápio, clientes e caixa da sua lanchonete em um só lugar."
        />
    </Head>

    <div class="min-h-screen overflow-hidden bg-[#f7f7f4] text-[#151713]">
        <header
            class="relative z-30 border-b border-black/8 bg-[#f7f7f4]/90 backdrop-blur-xl"
        >
            <div
                class="mx-auto flex h-18 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-10"
            >
                <a
                    href="#inicio"
                    class="flex items-center gap-3"
                    aria-label="Ir para o início"
                >
                    <span
                        class="grid size-10 place-items-center rounded-xl border border-black/8 bg-white text-[#163c2c] shadow-sm"
                    >
                        <AppLogoIcon class-name="size-9" />
                    </span>
                    <span class="text-base font-bold tracking-tight">{{
                        page.props.name
                    }}</span>
                </a>

                <nav
                    class="hidden items-center gap-7 text-sm font-medium text-[#5f655d] md:flex"
                    aria-label="Navegação da página"
                >
                    <a href="#recursos" class="transition hover:text-[#163c2c]"
                        >Recursos</a
                    >
                    <a
                        href="#como-funciona"
                        class="transition hover:text-[#163c2c]"
                        >Como funciona</a
                    >
                    <a href="#planos" class="transition hover:text-[#163c2c]"
                        >Planos</a
                    >
                    <a href="#comecar" class="transition hover:text-[#163c2c]"
                        >Começar</a
                    >
                </nav>

                <div class="flex items-center gap-2">
                    <template v-if="isAuthenticated">
                        <Link
                            :href="primaryUrl"
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#163c2c] px-4 text-sm font-semibold text-white transition hover:bg-[#0f2e21]"
                        >
                            Abrir painel <ArrowRight class="size-4" />
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="hidden h-10 items-center rounded-xl px-4 text-sm font-semibold text-[#343934] transition hover:bg-black/5 sm:inline-flex"
                            >Entrar</Link
                        >
                        <Link
                            :href="register()"
                            class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#163c2c] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#0f2e21]"
                        >
                            Criar conta <ArrowRight class="size-4" />
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <main id="inicio">
            <section class="relative">
                <div
                    class="absolute inset-x-0 top-0 h-[580px] bg-[radial-gradient(circle_at_75%_20%,rgba(72,154,112,0.18),transparent_35%),radial-gradient(circle_at_15%_40%,rgba(221,179,79,0.13),transparent_28%)]"
                />
                <div
                    class="relative mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-[0.88fr_1.12fr] lg:px-10 lg:py-28"
                >
                    <div class="max-w-2xl">
                        <div
                            class="mb-6 inline-flex items-center gap-2 rounded-full border border-[#163c2c]/15 bg-white/75 px-3 py-1.5 text-xs font-semibold text-[#245c43] shadow-sm"
                        >
                            <Sparkles class="size-3.5" /> Sua operação inteira
                            em um só lugar
                        </div>
                        <h1
                            class="text-4xl leading-[1.05] font-bold tracking-[-0.045em] sm:text-6xl lg:text-[4.5rem]"
                        >
                            Menos confusão.
                            <span class="block text-[#26704f]"
                                >Mais pedidos saindo.</span
                            >
                        </h1>
                        <p
                            class="mt-6 max-w-xl text-base leading-7 text-[#626860] sm:text-lg"
                        >
                            Organize atendimento, cozinha, entregas e caixa com
                            uma plataforma feita para a rotina da sua
                            lanchonete.
                        </p>
                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link
                                v-if="isAuthenticated"
                                :href="primaryUrl"
                                class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[#163c2c] px-6 text-sm font-semibold text-white shadow-[0_12px_30px_rgba(22,60,44,0.22)] transition hover:-translate-y-0.5 hover:bg-[#0f2e21]"
                            >
                                Ir para o painel <ArrowRight class="size-4" />
                            </Link>
                            <template v-else>
                                <Link
                                    :href="register()"
                                    class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[#163c2c] px-6 text-sm font-semibold text-white shadow-[0_12px_30px_rgba(22,60,44,0.22)] transition hover:-translate-y-0.5 hover:bg-[#0f2e21]"
                                >
                                    Começar agora <ArrowRight class="size-4" />
                                </Link>
                                <Link
                                    :href="login()"
                                    class="inline-flex h-12 items-center justify-center rounded-xl border border-black/10 bg-white px-6 text-sm font-semibold shadow-sm transition hover:bg-[#f0f1ed]"
                                    >Já tenho uma conta</Link
                                >
                            </template>
                        </div>
                        <div
                            class="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm text-[#626860]"
                        >
                            <span class="flex items-center gap-2"
                                ><Check class="size-4 text-[#26704f]" /> Simples
                                de usar</span
                            >
                            <span class="flex items-center gap-2"
                                ><Check class="size-4 text-[#26704f]" /> Feito
                                para equipes</span
                            >
                            <span class="flex items-center gap-2"
                                ><Check class="size-4 text-[#26704f]" /> Acesso
                                seguro</span
                            >
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-2xl">
                        <div
                            class="absolute -inset-8 -z-10 rounded-[3rem] bg-[#2d7454]/10 blur-3xl"
                        />
                        <div
                            class="overflow-hidden rounded-[1.75rem] border border-black/10 bg-white shadow-[0_30px_80px_rgba(30,43,34,0.16)]"
                        >
                            <div
                                class="flex items-center justify-between border-b border-black/7 px-5 py-4"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="grid size-8 place-items-center rounded-lg border border-black/8 bg-white text-[#163c2c]"
                                        ><AppLogoIcon class-name="size-7"
                                    /></span>
                                    <div>
                                        <p class="text-xs font-bold">Pedix</p>
                                        <p class="text-[10px] text-[#858a83]">
                                            Visão geral
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center gap-2 rounded-lg bg-[#f2f3ef] px-3 py-2 text-[10px] font-semibold"
                                >
                                    <span
                                        class="size-2 rounded-full bg-emerald-500"
                                    />
                                    Operação ativa
                                </div>
                            </div>

                            <div class="bg-[#fafaf8] p-4 sm:p-6">
                                <div
                                    class="mb-5 flex items-end justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-medium text-[#2d7454]"
                                        >
                                            Hoje na lanchonete
                                        </p>
                                        <p class="mt-1 text-xl font-bold">
                                            Tudo sob controle.
                                        </p>
                                    </div>
                                    <span
                                        class="hidden text-[10px] text-[#858a83] sm:block"
                                        >Atualizado agora</span
                                    >
                                </div>

                                <div class="grid grid-cols-3 gap-2 sm:gap-3">
                                    <div
                                        class="rounded-xl border border-black/7 bg-white p-3 sm:p-4"
                                    >
                                        <ClipboardCheck
                                            class="mb-4 size-4 text-[#26704f]"
                                        />
                                        <p
                                            class="text-xl font-bold sm:text-2xl"
                                        >
                                            24
                                        </p>
                                        <p
                                            class="text-[10px] text-[#777d75] sm:text-xs"
                                        >
                                            Pedidos hoje
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-xl border border-black/7 bg-white p-3 sm:p-4"
                                    >
                                        <ChefHat
                                            class="mb-4 size-4 text-amber-600"
                                        />
                                        <p
                                            class="text-xl font-bold sm:text-2xl"
                                        >
                                            5
                                        </p>
                                        <p
                                            class="text-[10px] text-[#777d75] sm:text-xs"
                                        >
                                            Em preparo
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-xl border border-black/7 bg-white p-3 sm:p-4"
                                    >
                                        <PackageCheck
                                            class="mb-4 size-4 text-sky-600"
                                        />
                                        <p
                                            class="text-xl font-bold sm:text-2xl"
                                        >
                                            3
                                        </p>
                                        <p
                                            class="text-[10px] text-[#777d75] sm:text-xs"
                                        >
                                            Prontos
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-3 grid gap-3 sm:grid-cols-[1.35fr_0.65fr]"
                                >
                                    <div
                                        class="rounded-xl border border-black/7 bg-white p-4"
                                    >
                                        <div
                                            class="mb-3 flex items-center justify-between"
                                        >
                                            <p class="text-xs font-bold">
                                                Pedidos recentes
                                            </p>
                                            <span
                                                class="text-[10px] text-[#26704f]"
                                                >Ver todos</span
                                            >
                                        </div>
                                        <div class="space-y-2">
                                            <div
                                                v-for="order in previewOrders"
                                                :key="order.id"
                                                class="flex items-center justify-between rounded-lg bg-[#f6f7f3] px-3 py-2.5"
                                            >
                                                <div>
                                                    <p
                                                        class="text-[11px] font-bold"
                                                    >
                                                        Pedido #{{ order.id }}
                                                    </p>
                                                    <p
                                                        class="text-[9px] text-[#858a83]"
                                                    >
                                                        {{ order.item }}
                                                    </p>
                                                </div>
                                                <span
                                                    class="rounded-full bg-white px-2 py-1 text-[9px] font-semibold"
                                                    >{{ order.status }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-xl bg-[#173c2d] p-4 text-white"
                                    >
                                        <CircleDollarSign
                                            class="size-5 text-emerald-300"
                                        />
                                        <p
                                            class="mt-8 text-[10px] text-white/60"
                                        >
                                            Vendas do dia
                                        </p>
                                        <p class="mt-1 text-2xl font-bold">
                                            R$ 1.248
                                        </p>
                                        <div
                                            class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/10"
                                        >
                                            <div
                                                class="h-full w-3/4 rounded-full bg-emerald-300"
                                            />
                                        </div>
                                        <p
                                            class="mt-2 text-[9px] text-white/55"
                                        >
                                            75% da meta diária
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                id="recursos"
                class="border-y border-black/7 bg-white py-20 sm:py-24"
            >
                <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
                    <div class="max-w-2xl">
                        <p class="text-sm font-bold text-[#26704f]">
                            Tudo conectado
                        </p>
                        <h2
                            class="mt-3 text-3xl font-bold tracking-[-0.035em] sm:text-5xl"
                        >
                            Da entrada do pedido ao fechamento do caixa.
                        </h2>
                        <p class="mt-4 text-base leading-7 text-[#666c64]">
                            Cada parte da operação conversa com a outra, para
                            sua equipe trabalhar com mais clareza e menos
                            retrabalho.
                        </p>
                    </div>
                    <div class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="feature in features"
                            :key="feature.title"
                            class="group rounded-2xl border border-black/8 bg-[#fbfbf9] p-6 transition duration-300 hover:-translate-y-1 hover:border-[#26704f]/30 hover:shadow-lg"
                        >
                            <span
                                class="grid size-11 place-items-center rounded-xl bg-[#e7f0ea] text-[#1f6244] transition group-hover:bg-[#163c2c] group-hover:text-white"
                                ><component :is="feature.icon" class="size-5"
                            /></span>
                            <h3 class="mt-5 text-lg font-bold">
                                {{ feature.title }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-[#6b7169]">
                                {{ feature.description }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="como-funciona" class="py-20 sm:py-28">
                <div
                    class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:items-center lg:px-10"
                >
                    <div>
                        <p class="text-sm font-bold text-[#26704f]">
                            Rotina mais leve
                        </p>
                        <h2
                            class="mt-3 text-3xl font-bold tracking-[-0.035em] sm:text-5xl"
                        >
                            Um fluxo que sua equipe entende de primeira.
                        </h2>
                        <p class="mt-5 max-w-xl leading-7 text-[#666c64]">
                            Sem telas complicadas. Cada pessoa vê o que precisa
                            e acompanha o pedido até a conclusão.
                        </p>
                    </div>
                    <ol class="space-y-3">
                        <li
                            v-for="(step, index) in steps"
                            :key="step"
                            class="flex items-center gap-4 rounded-2xl border border-black/8 bg-white p-5 shadow-sm"
                        >
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-xl bg-[#163c2c] text-sm font-bold text-white"
                                >0{{ index + 1 }}</span
                            >
                            <div class="flex-1">
                                <p class="font-bold">{{ step }}</p>
                                <p class="mt-1 text-sm text-[#777d75]">
                                    Informação atualizada para toda a equipe.
                                </p>
                            </div>
                            <Clock3
                                class="hidden size-5 text-[#a5aaa3] sm:block"
                            />
                        </li>
                    </ol>
                </div>
            </section>

            <section
                id="planos"
                class="border-y border-black/7 bg-white py-20 sm:py-28"
            >
                <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-10">
                    <div class="mx-auto max-w-2xl text-center">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-[#26704f]/20 bg-[#eaf3ed] px-3 py-1.5 text-xs font-bold text-[#1f6244]"
                        >
                            <Sparkles class="size-3.5" />
                            CONDIÇÃO DE LANÇAMENTO
                        </div>
                        <h2
                            class="mt-5 text-3xl font-bold tracking-[-0.035em] sm:text-5xl"
                        >
                            Comece com 15 dias grátis.
                        </h2>
                        <p class="mt-4 text-base leading-7 text-[#666c64]">
                            Os primeiros clientes da Pedix poderão testar todos
                            os recursos por 15 dias antes de escolher um plano.
                        </p>
                    </div>

                    <div
                        class="mx-auto mt-12 grid max-w-4xl gap-5 md:grid-cols-2"
                    >
                        <article
                            class="flex flex-col rounded-[1.75rem] border border-black/10 bg-[#fafaf8] p-6 shadow-sm sm:p-8"
                        >
                            <div>
                                <p class="text-sm font-bold text-[#26704f]">
                                    Plano mensal
                                </p>
                                <div class="mt-4 flex items-end gap-1">
                                    <span
                                        class="text-4xl font-bold tracking-[-0.04em]"
                                        >R$ 79,90</span
                                    >
                                    <span class="mb-1 text-sm text-[#747a72]"
                                        >/mês</span
                                    >
                                </div>
                                <p class="mt-2 text-sm text-[#747a72]">
                                    R$ 958,80 no período de 12 meses.
                                </p>
                            </div>
                            <ul class="mt-8 flex-1 space-y-3 text-sm">
                                <li
                                    class="flex items-center gap-3 text-[#4f554e]"
                                >
                                    <Check class="size-4 text-[#26704f]" />
                                    Todos os recursos da plataforma
                                </li>
                                <li
                                    class="flex items-center gap-3 text-[#4f554e]"
                                >
                                    <Check class="size-4 text-[#26704f]" />
                                    Pagamento mês a mês
                                </li>
                                <li
                                    class="flex items-center gap-3 text-[#4f554e]"
                                >
                                    <Check class="size-4 text-[#26704f]" />
                                    Liberdade para começar sem compromisso anual
                                </li>
                            </ul>
                            <Link
                                :href="
                                    isAuthenticated ? primaryUrl : register()
                                "
                                class="mt-8 inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-[#163c2c]/20 bg-white px-5 text-sm font-bold text-[#163c2c] transition hover:bg-[#edf3ef]"
                            >
                                Testar grátis por 15 dias
                                <ArrowRight class="size-4" />
                            </Link>
                        </article>

                        <article
                            class="relative flex flex-col overflow-hidden rounded-[1.75rem] bg-[#153b2b] p-6 text-white shadow-[0_24px_60px_rgba(21,59,43,0.2)] sm:p-8"
                        >
                            <div
                                class="absolute top-0 right-0 rounded-bl-2xl bg-emerald-300 px-4 py-2 text-xs font-bold text-[#153b2b]"
                            >
                                MELHOR VALOR
                            </div>
                            <div>
                                <p class="text-sm font-bold text-emerald-300">
                                    Plano anual
                                </p>
                                <div class="mt-4 flex items-end gap-1">
                                    <span
                                        class="text-4xl font-bold tracking-[-0.04em]"
                                        >R$ 799,00</span
                                    >
                                    <span class="mb-1 text-sm text-white/55"
                                        >/ano</span
                                    >
                                </div>
                                <p class="mt-2 text-sm text-white/60">
                                    Equivale a R$ 66,58 por mês.
                                </p>
                            </div>
                            <ul class="mt-8 flex-1 space-y-3 text-sm">
                                <li
                                    class="flex items-center gap-3 text-white/80"
                                >
                                    <Check class="size-4 text-emerald-300" />
                                    Todos os recursos da plataforma
                                </li>
                                <li
                                    class="flex items-center gap-3 text-white/80"
                                >
                                    <Check class="size-4 text-emerald-300" />
                                    Economia de R$ 159,80 em 12 meses
                                </li>
                                <li
                                    class="flex items-center gap-3 text-white/80"
                                >
                                    <Check class="size-4 text-emerald-300" />
                                    Um único pagamento por ano
                                </li>
                            </ul>
                            <Link
                                :href="
                                    isAuthenticated ? primaryUrl : register()
                                "
                                class="mt-8 inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-white px-5 text-sm font-bold text-[#153b2b] transition hover:bg-[#edf3ef]"
                            >
                                Testar grátis por 15 dias
                                <ArrowRight class="size-4" />
                            </Link>
                        </article>
                    </div>

                    <p class="mt-6 text-center text-xs text-[#858a83]">
                        Período gratuito disponível para os primeiros clientes
                        durante o lançamento da Pedix.
                    </p>
                </div>
            </section>

            <section id="comecar" class="px-5 pb-20 sm:px-8 sm:pb-28 lg:px-10">
                <div
                    class="relative mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-[#153b2b] px-6 py-14 text-white sm:px-12 sm:py-16 lg:px-16"
                >
                    <div
                        class="absolute -top-32 -right-24 size-96 rounded-full border-[70px] border-white/5"
                    />
                    <div
                        class="absolute -bottom-40 left-1/3 size-80 rounded-full bg-emerald-300/10 blur-3xl"
                    />
                    <div
                        class="relative flex flex-col items-start justify-between gap-8 lg:flex-row lg:items-end"
                    >
                        <div class="max-w-2xl">
                            <p class="text-sm font-bold text-emerald-300">
                                Pronto para organizar a casa?
                            </p>
                            <h2
                                class="mt-3 text-3xl font-bold tracking-[-0.035em] sm:text-5xl"
                            >
                                Sua lanchonete merece uma operação mais simples.
                            </h2>
                            <p class="mt-4 text-base leading-7 text-white/65">
                                Crie sua conta e comece a centralizar pedidos,
                                equipe e resultados.
                            </p>
                        </div>
                        <Link
                            :href="isAuthenticated ? primaryUrl : register()"
                            class="inline-flex h-12 shrink-0 items-center gap-2 rounded-xl bg-white px-6 text-sm font-bold text-[#153b2b] transition hover:-translate-y-0.5 hover:bg-[#edf3ef]"
                        >
                            {{
                                isAuthenticated
                                    ? 'Abrir painel'
                                    : 'Criar minha conta'
                            }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-black/8 bg-white">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-8 text-sm text-[#747a72] sm:flex-row sm:items-center sm:justify-between sm:px-8 lg:px-10"
            >
                <div
                    class="flex items-center gap-2 font-semibold text-[#343934]"
                >
                    <AppLogoIcon class-name="size-6 text-[#163c2c]" />
                    {{ page.props.name }}
                </div>
                <p>Gestão simples para quem coloca a mão na massa.</p>
            </div>
        </footer>
    </div>
</template>
