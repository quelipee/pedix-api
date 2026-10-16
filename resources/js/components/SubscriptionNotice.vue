<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, CreditCard, TriangleAlert } from '@lucide/vue';

import type { SubscriptionNoticeData } from '@/types/billing';

defineProps<{ notice: SubscriptionNoticeData }>();
</script>

<template>
    <aside
        aria-label="Situação da assinatura"
        class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border p-4 sm:px-5"
        :class="
            notice.tone === 'warning'
                ? 'border-amber-300/60 bg-amber-50/70 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100'
                : 'border-border bg-card text-foreground'
        "
    >
        <div class="flex min-w-0 flex-1 items-start gap-3">
            <component
                :is="notice.tone === 'warning' ? TriangleAlert : CreditCard"
                class="mt-0.5 size-5 shrink-0"
            />
            <div>
                <h2 class="text-sm font-semibold">{{ notice.title }}</h2>
                <p class="mt-1 text-sm leading-6 opacity-80">
                    {{ notice.message }}
                </p>
            </div>
        </div>
        <Link
            :href="notice.url"
            class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-current/15 px-3 py-2 text-xs font-semibold transition-colors hover:bg-current/5"
        >
            Gerenciar assinatura <ArrowUpRight class="size-4" />
        </Link>
    </aside>
</template>
