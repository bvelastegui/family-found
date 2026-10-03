<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, ChevronLeft } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { index as notificationsIndex } from '@/routes/fund/notifications';
import type { BreadcrumbItem } from '@/types';

withDefaults(
  defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    showDesktop?: boolean;
    showMobile?: boolean;
  }>(),
  {
    breadcrumbs: () => [],
    showDesktop: true,
    showMobile: true,
  },
);
const page = usePage();
const unreadCount = computed(() =>
  Number(page.props.unreadNotificationsCount ?? 0),
);
</script>

<template>
  <header
    class="sticky top-0 z-20 hidden h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:flex md:px-4"
  >
    <div class="flex min-w-0 flex-1 items-center gap-2">
      <SidebarTrigger class="-ml-1 hidden md:inline-flex" />
      <template v-if="breadcrumbs && breadcrumbs.length > 0">
        <Breadcrumbs
          v-if="showDesktop"
          :breadcrumbs="breadcrumbs"
          class="hidden md:flex"
        />
        <div
          v-else-if="showMobile && breadcrumbs.length"
          class="flex min-w-0 flex-col md:hidden"
        >
          <Link
            v-if="breadcrumbs.length > 2"
            :href="breadcrumbs[breadcrumbs.length - 2].href"
            class="-ml-1 flex min-h-8 max-w-full items-center gap-1 rounded-md px-1 text-xs text-muted-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
          >
            <ChevronLeft class="size-4 shrink-0" />
            <span class="truncate">{{
              breadcrumbs[breadcrumbs.length - 2].title
            }}</span>
          </Link>
          <p
            v-if="breadcrumbs.length > 1"
            class="truncate text-sm font-semibold text-foreground"
          >
            {{ breadcrumbs[breadcrumbs.length - 1].title }}
          </p>
        </div>
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
