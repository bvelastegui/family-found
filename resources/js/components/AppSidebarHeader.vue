<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { index as notificationsIndex } from '@/routes/fund/notifications';
import type { BreadcrumbItem } from '@/types';

withDefaults(
  defineProps<{
    breadcrumbs?: BreadcrumbItem[];
  }>(),
  {
    breadcrumbs: () => [],
  },
);
const page = usePage();
const unreadCount = computed(() =>
  Number(page.props.unreadNotificationsCount ?? 0),
);
</script>

<template>
  <header
    class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
  >
    <div class="flex items-center gap-2">
      <SidebarTrigger class="-ml-1" />
      <template v-if="breadcrumbs && breadcrumbs.length > 0">
        <Breadcrumbs :breadcrumbs="breadcrumbs" />
      </template>
    </div>
    <Button
      variant="ghost"
      size="icon"
      class="relative ml-auto"
      as-child
    >
      <Link
        :href="notificationsIndex()"
        :aria-label="`Notificaciones: ${unreadCount} sin leer`"
      >
        <Bell />
        <span
          v-if="unreadCount"
          class="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs text-primary-foreground"
          aria-hidden="true"
          >{{ unreadCount > 9 ? '9+' : unreadCount }}</span
        >
      </Link>
    </Button>
  </header>
</template>
