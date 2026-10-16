<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        detail?: string;
        tone?: 'green' | 'amber' | 'sky' | 'orange';
    }>(),
    { tone: 'green' },
);

const tones = {
    green: 'bg-secondary text-secondary-foreground',
    amber: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    sky: 'bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
    orange: 'bg-orange-50 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300',
};
</script>

<template>
    <article class="workspace-stat">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
            <span
                v-if="$slots.icon"
                class="grid size-9 shrink-0 place-items-center rounded-xl"
                :class="tones[tone]"
            >
                <slot name="icon" />
            </span>
        </div>
        <p class="mt-4 text-3xl font-semibold tracking-tight tabular-nums">
            {{ value }}
        </p>
        <p v-if="detail" class="mt-2 text-xs text-muted-foreground">
            {{ detail }}
        </p>
    </article>
</template>
