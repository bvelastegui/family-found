<script setup lang="ts">
import AppPageNotificationsButton from '@/components/AppPageNotificationsButton.vue';

withDefaults(
  defineProps<{
    title: string;
    description?: string;
    level?: 1 | 2;
    inverse?: boolean;
    showNotifications?: boolean;
  }>(),
  { level: 1, inverse: false, showNotifications: true },
);
</script>

<template>
  <header class="flex items-center justify-between gap-3">
    <div class="min-w-0 flex-1">
      <component
        :is="level === 1 ? 'h1' : 'h2'"
        class="text-xl font-semibold tracking-tight sm:text-3xl"
        :class="inverse ? 'text-primary-foreground' : 'text-foreground'"
      >
        {{ title }}
      </component>
      <p
        v-if="description"
        class="mt-1 text-sm sm:text-base"
        :class="
          inverse ? 'text-primary-foreground/75' : 'text-muted-foreground'
        "
      >
        {{ description }}
      </p>
    </div>
    <div class="flex shrink-0 items-center gap-2">
      <slot name="actions" />
      <AppPageNotificationsButton
        v-if="showNotifications"
        :inverse="inverse"
      />
    </div>
  </header>
</template>
