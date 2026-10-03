<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as auditIndex, controls } from '@/routes/fund/audit';
import { show as eventShow } from '@/routes/fund/audit/events';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FundPagination from '@/components/FundPagination.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
import {
  fundDateTime,
  usd,
  type FundPagination as Pagination,
} from '@/lib/fund';
import type { AuditEvent } from '@/lib/audit';
const props = defineProps<{
  events: Pagination<
    AuditEvent & {
      participant_name: string | null;
      reference: string | null;
      amount_cents: number | null;
    }
  >;
  filters: { from: string; to: string; type: string; search: string };
}>();
const filters = ref({ ...props.filters });
const page = usePage();
function filter(): void {
  router.get(auditIndex().url, filters.value, { replace: true });
}
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Auditoría', href: auditIndex() },
    ],
  },
});
</script>
<template>
  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
    <Head title="Trazabilidad del fondo" />
    <header class="flex flex-wrap items-center justify-between gap-4">
      <AppPageHeader title="Trazabilidad">
        <template #actions>
          <Button
            class="hidden sm:inline-flex"
            as-child
            variant="outline"
          >
            <Link :href="controls()">Revisar controles</Link>
          </Button>
        </template>
      </AppPageHeader>
    </header>
    <form
      class="flex flex-wrap items-end gap-3"
      role="search"
      @submit.prevent="filter"
    >
      <div class="flex min-w-48 flex-1 flex-col gap-2">
        <Label for="audit-search">Persona o comprobante</Label
        ><Input
          id="audit-search"
          v-model="filters.search"
          type="search"
          maxlength="100"
          placeholder="Participante, responsable o referencia"
        />
      </div>
      <div class="flex flex-col gap-2">
        <Label for="audit-from">Desde</Label
        ><Input
          id="audit-from"
          v-model="filters.from"
          type="date"
        />
      </div>
      <div class="flex flex-col gap-2">
        <Label for="audit-to">Hasta</Label
        ><Input
          id="audit-to"
          v-model="filters.to"
          type="date"
          :min="filters.from || undefined"
        />
      </div>
      <div class="flex flex-col gap-2">
        <Label for="audit-type">Operación</Label
        ><select
          id="audit-type"
          v-model="filters.type"
          class="h-9 rounded-md border bg-background px-3 text-sm"
        >
          <option value="">Todas</option>
          <option value="transaction">Transferencias</option>
          <option value="loan">Préstamos</option>
          <option value="fund">Administración</option>
          <option value="period">Cuotas</option>
          <option value="bank">Bancos</option>
          <option value="user">Participantes</option>
          <option value="invitation">Invitaciones</option>
        </select>
      </div>
      <Button type="submit">Filtrar</Button
      ><Button
        as-child
        variant="ghost"
        ><Link :href="auditIndex()">Limpiar</Link></Button
      >
    </form>
    <p class="text-sm text-muted-foreground">
      {{ events.total }} eventos · Fechas en horario de Ecuador.
    </p>
    <div class="overflow-x-auto rounded-lg border">
      <table class="w-full min-w-[850px] text-left text-sm">
        <caption class="sr-only">
          Historial de operaciones del fondo
        </caption>
        <thead class="bg-muted/70 text-muted-foreground">
          <tr>
            <th
              scope="col"
              class="px-4 py-3 font-medium"
            >
              Fecha
            </th>
            <th
              scope="col"
              class="px-4 py-3 font-medium"
            >
              Operación
            </th>
            <th
              scope="col"
              class="px-4 py-3 font-medium"
            >
              Responsable
            </th>
            <th
              scope="col"
              class="px-4 py-3 font-medium"
            >
              Participante
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right font-medium"
            >
              Monto
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right font-medium"
            >
              Detalle
            </th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr
            v-for="event in events.data"
            :key="event.id"
            class="hover:bg-muted/30"
          >
            <td class="px-4 py-3">{{ fundDateTime(event.created_at) }}</td>
            <td class="px-4 py-3">
              <p class="font-medium">{{ event.title }}</p>
              <p class="text-xs text-muted-foreground">
                {{ event.reference ?? `Registro #${event.subject_id}` }}
              </p>
            </td>
            <td class="px-4 py-3">{{ event.actor_name }}</td>
            <td class="px-4 py-3">{{ event.participant_name ?? '—' }}</td>
            <td class="px-4 py-3 text-right tabular-nums">
              {{ event.amount_cents === null ? '—' : usd(event.amount_cents) }}
            </td>
            <td class="px-4 py-3 text-right">
              <Link
                :href="eventShow(event.id, { query: { return_to: page.url } })"
                class="font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring"
                >Examinar</Link
              >
            </td>
          </tr>
          <tr v-if="!events.data.length">
            <td
              colspan="6"
              class="p-8 text-center text-muted-foreground"
            >
              No hay eventos con estos filtros.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <FundPagination
      :links="events.links"
      :last-page="events.last_page"
      label="Páginas del historial de auditoría"
    />
  </main>
</template>
