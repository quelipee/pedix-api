<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 115 115"
        :class="className"
        role="img"
        aria-label="Logo da aplicação"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            d="M20 44C20 31.85 29.85 22 42 22h31c12.15 0 22 9.85 22 22H20Z"
        />
        <path
            fill="currentColor"
            d="M20 48h75c0 12.15-9.85 22-22 22H43v3c0 12.15-9.85 22-22 22h-1V48Z"
        />
    </svg>
</template>
