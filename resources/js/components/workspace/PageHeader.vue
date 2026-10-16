<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <header class="workspace-page-header">
        <div class="min-w-0 space-y-2">
            <p v-if="eyebrow" class="workspace-eyebrow">{{ eyebrow }}</p>
            <h1 class="text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="max-w-2xl text-sm leading-6 text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </header>
</template>
