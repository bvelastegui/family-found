<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import { Galaxy } from '@lucide/vue';

defineProps<{
  title?: string;
  description?: string;
}>();
</script>

<template>
  <div
    class="flex min-h-svh flex-col items-center justify-start gap-6 bg-background px-5 pt-8 pb-[max(2rem,env(safe-area-inset-bottom))] md:justify-center md:bg-muted md:p-10"
  >
    <div class="flex w-full max-w-md flex-col gap-6">
      <Link
        :href="home()"
        class="flex items-center gap-2 self-center font-medium"
      >
        <div class="flex h-9 w-9 items-center justify-center">
          <Galaxy class="size-9" />
        </div>
      </Link>

      <div class="flex flex-col gap-6">
        <Card class="gap-6 rounded-none border-0 bg-transparent py-0 shadow-none md:gap-6 md:rounded-xl md:border md:bg-card md:py-6 md:shadow-sm">
          <CardHeader class="px-0 pt-2 pb-0 text-left md:px-10 md:pt-8 md:text-center">
            <CardTitle class="text-2xl md:text-xl">{{ title }}</CardTitle>
            <CardDescription>
              {{ description }}
            </CardDescription>
          </CardHeader>
          <CardContent class="px-0 py-0 md:px-10 md:py-8">
            <slot />
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
