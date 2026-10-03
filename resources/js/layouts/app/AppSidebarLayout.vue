<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
  breadcrumbs?: BreadcrumbItem[];
  showHeader?: boolean;
  showMobileHeader?: boolean;
};

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
  showHeader: true,
  showMobileHeader: true,
});
</script>

<template>
  <AppShell variant="sidebar">
    <AppSidebar />
    <AppContent
      variant="sidebar"
      class="min-w-0 overflow-x-clip pb-16 md:pb-0"
    >
      <AppSidebarHeader
        v-if="showHeader || showMobileHeader"
        :breadcrumbs="breadcrumbs"
        :show-desktop="showHeader"
        :show-mobile="showMobileHeader"
      />
      <slot />
    </AppContent>
    <Toaster />
  </AppShell>
</template>
