<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
  links: { url: string | null; label: string; active: boolean }[];
  label: string;
  lastPage: number;
}>();
const text = (label: string): string =>
  label.replace(/&laquo;|&raquo;/g, '').trim();
</script>

<template>
  <nav
    v-if="lastPage > 1"
    class="flex flex-wrap gap-2"
    :aria-label="label"
  >
    <template
      v-for="(link, index) in links"
      :key="index"
    >
      <Link
        v-if="link.url"
        :href="link.url"
        class="rounded-md border px-3 py-1 text-sm hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
        :class="{ 'bg-primary text-primary-foreground': link.active }"
        :aria-current="link.active ? 'page' : undefined"
        >{{ text(link.label) }}</Link
      >
      <span
        v-else
        class="rounded-md border px-3 py-1 text-sm text-muted-foreground"
        >{{ text(link.label) }}</span
      >
    </template>
  </nav>
</template>
