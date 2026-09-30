<script setup lang="ts">
import { useTextareaAutosize } from '@vueuse/core';

const value = defineModel<string>({ default: '' });
const { textarea } = useTextareaAutosize({ input: value });
</script>

<template>
    <textarea
        ref="textarea"
        v-model="value"
        rows="3"
        class="min-h-24 w-full min-w-0 resize-none overflow-y-auto rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none file:text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30"
    />
</template>
