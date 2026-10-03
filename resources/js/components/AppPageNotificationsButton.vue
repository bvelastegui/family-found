<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { index as notificationsIndex } from '@/routes/fund/notifications';

const page = usePage();
const props = withDefaults(defineProps<{ inverse?: boolean }>(), {
  inverse: false,
});
const unreadCount = computed(() =>
  Number(page.props.unreadNotificationsCount ?? 0),
);
</script>

<template>
  <Button
    :variant="props.inverse ? 'ghost' : 'outline'"
    size="icon"
    class="relative size-11 shrink-0 rounded-xl md:hidden"
    :class="
      props.inverse
        ? 'text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground'
        : ''
    "
    as-child
  >
    <Link
      :href="notificationsIndex()"
      :aria-label="`Notificaciones: ${unreadCount} sin leer`"
    >
      <Bell class="size-5" />
      <span
        v-if="unreadCount"
        class="absolute -top-1 -right-1 flex min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs text-primary-foreground"
        aria-hidden="true"
        >{{ unreadCount > 9 ? '9+' : unreadCount }}</span
      >
    </Link>
  </Button>
</template>
