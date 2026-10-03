<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const {
  breadcrumbs = [],
  showHeader = true,
  showMobileHeader = true,
} = defineProps<{
  breadcrumbs?: BreadcrumbItem[];
  showHeader?: boolean;
  showMobileHeader?: boolean;
}>();
</script>

<template>
  <AppLayout
    :breadcrumbs="breadcrumbs"
    :show-header="showHeader"
    :show-mobile-header="showMobileHeader"
  >
    <slot />
  </AppLayout>
</template>
