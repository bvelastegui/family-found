<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import {
  index as notificationsIndex,
  read,
  readAll,
} from '@/routes/fund/notifications';
import { Button } from '@/components/ui/button';
import FundPagination from '@/components/FundPagination.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
import { fundDateTime, type FundPagination as Pagination } from '@/lib/fund';
type Notice = {
  id: string;
  title: string;
  body: string;
  url: string;
  created_at: string;
  read_at: string | null;
};
defineProps<{ notifications: Pagination<Notice> }>();
const page = usePage();
const readForm = useForm({});
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Notificaciones', href: notificationsIndex() },
    ],
  },
});
</script>

<template>
  <main
    class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4 md:gap-6 md:p-8"
  >
    <Head title="Notificaciones" />
    <AppPageHeader
      title="Notificaciones"
      :show-notifications="false"
    />
    <div class="flex items-center justify-between gap-3">
      <p class="text-sm text-muted-foreground">
        {{ Number(page.props.unreadNotificationsCount ?? 0) }} sin leer
      </p>
      <Button
        v-if="Number(page.props.unreadNotificationsCount ?? 0) > 0"
        variant="outline"
        class="min-h-11 rounded-xl px-3 text-sm"
        :disabled="readForm.processing"
        @click="readForm.patch(readAll().url)"
        >Marcar todas leídas</Button
      >
    </div>
    <p
      v-if="!notifications.data.length"
      class="py-8 text-center text-sm text-muted-foreground"
    >
      Todavía no tienes notificaciones.
    </p>
    <ol
      v-else
      class="divide-y"
    >
      <li
        v-for="notice in notifications.data"
        :key="notice.id"
      >
        <Link
          :href="read(notice.id, { query: { open: true } })"
          method="patch"
          as="button"
          class="block w-full space-y-2 px-1 py-4 text-left transition-colors hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring"
        >
          <div class="flex items-start justify-between gap-3">
            <p
              class="text-sm break-words"
              :class="!notice.read_at ? 'font-semibold' : 'font-medium'"
            >
              {{ notice.title }}
            </p>
            <span
              v-if="!notice.read_at"
              class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
              aria-label="Sin leer"
            />
          </div>
          <p class="text-sm break-words text-muted-foreground">
            {{ notice.body }}
          </p>
          <time class="block text-xs text-muted-foreground">{{
            fundDateTime(notice.created_at)
          }}</time>
        </Link>
      </li>
    </ol>
    <FundPagination
      :links="notifications.links"
      :last-page="notifications.last_page"
      label="Páginas de notificaciones"
    />
  </main>
</template>
