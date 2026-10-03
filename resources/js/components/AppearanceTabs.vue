<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
  { value: 'light', Icon: Sun, label: 'Claro' },
  { value: 'dark', Icon: Moon, label: 'Oscuro' },
  { value: 'system', Icon: Monitor, label: 'Sistema' },
] as const;
</script>

<template>
  <div
    class="grid grid-cols-3 gap-1 rounded-xl bg-muted p-1"
    role="group"
    aria-label="Tema de la aplicación"
  >
    <button
      v-for="{ value, Icon, label } in tabs"
      :key="value"
      type="button"
      :aria-pressed="appearance === value"
      @click="updateAppearance(value)"
      :class="[
        'flex min-h-12 items-center justify-center rounded-lg px-3 py-2 transition-colors focus-visible:ring-2 focus-visible:ring-ring',
        appearance === value
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
      ]"
    >
      <component
        :is="Icon"
        class="-ml-1 h-4 w-4"
      />
      <span class="ml-1.5 text-sm">{{ label }}</span>
    </button>
  </div>
</template>
