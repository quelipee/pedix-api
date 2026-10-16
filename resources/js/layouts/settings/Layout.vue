<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '@/components/workspace/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as billingIndex } from '@/routes/billing';
import { index as teams } from '@/routes/teams';
import type { NavItem } from '@/types';

const page = usePage();
const sidebarNavItems = computed<NavItem[]>(() => [
    {
        title: 'Meu perfil',
        href: editProfile(),
    },
    {
        title: 'Segurança',
        href: editSecurity(),
    },
    {
        title: 'Equipes',
        href: teams(),
    },
    {
        title: 'Aparência',
        href: editAppearance(),
    },
    ...(page.props.permissions?.canManageBilling && page.props.currentTeam
        ? [
              {
                  title: 'Assinatura',
                  href: billingIndex(page.props.currentTeam.slug),
              },
          ]
        : []),
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="workspace-page">
        <PageHeader
            title="Configurações"
            eyebrow="Do seu jeito"
            description="Cuide do seu perfil, da segurança e das pessoas que fazem parte da operação."
        />

        <div class="space-y-5">
            <div class="workspace-panel p-2">
                <nav
                    class="flex gap-1 overflow-x-auto"
                    aria-label="Configurações"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'shrink-0 rounded-xl',
                            {
                                'bg-secondary text-primary':
                                    isCurrentOrParentUrl(item.href),
                            },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </div>

            <div class="workspace-panel p-5 sm:p-8">
                <section class="max-w-3xl space-y-10">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
