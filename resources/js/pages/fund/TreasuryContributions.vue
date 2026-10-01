<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import { index as contributionsIndex } from '@/routes/fund/treasury/contributions';
import { show as transactionShow } from '@/routes/fund/transactions';
import FundPagination from '@/components/FundPagination.vue';
import FundStatus from '@/components/FundStatus.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { fundMonth, usd, type FundPagination as Pagination } from '@/lib/fund';

type Participant = {
  id: number;
  name: string;
  email: string;
  expected_cents: number;
  approved_cents: number;
  pending_cents: number;
  status: string;
  transaction_id: number | null;
};
const props = defineProps<{
  participants: Pagination<Participant> | null;
  filters: { month: string; status: string };
  amountCents: number | null;
}>();
const page = usePage();
const month = ref(props.filters.month);
const status = ref(props.filters.status);
function filter(): void {
  router.get(
    contributionsIndex().url,
    { month: month.value, ...(status.value ? { status: status.value } : {}) },
    { replace: true },
  );
}
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Tesorería', href: treasuryIndex() },
      { title: 'Aportes', href: contributionsIndex() },
    ],
  },
});
</script>

<template>
  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
    <Head title="Aportes de tesorería" />
    <header>
      <p class="text-sm text-muted-foreground">Tesorería</p>
      <h1 class="text-3xl font-semibold tracking-tight">Aportes</h1>
      <p class="mt-1 text-muted-foreground">
        Seguimiento mensual de todos los participantes del fondo.
      </p>
    </header>
    <form
      class="flex flex-wrap items-end gap-3"
      @submit.prevent="filter"
    >
      <div class="flex flex-col gap-2">
        <Label for="contribution-month">Mes</Label
        ><Input
          id="contribution-month"
          v-model="month"
          type="month"
          required
          @change="filter"
        />
      </div>
      <div class="flex flex-col gap-2">
        <Label for="contribution-status">Estado</Label>
        <select
          id="contribution-status"
          v-model="status"
          class="h-9 rounded-md border bg-background px-3 text-sm"
          @change="filter"
        >
          <option value="">Todos</option>
          <option value="paid">Pagado</option>
          <option value="pending">En revisión</option>
          <option value="unpaid">Pendiente</option>
          <option value="not_applicable">No aplica</option>
        </select>
      </div>
      <Button
        type="submit"
        variant="outline"
        >Consultar</Button
      >
      <p
        v-if="amountCents !== null"
        class="py-2 text-sm text-muted-foreground"
      >
        Cuota de {{ fundMonth(filters.month) }}: {{ usd(amountCents) }}
      </p>
    </form>
    <p
      v-if="!participants"
      class="rounded-lg border p-8 text-center text-muted-foreground"
    >
      No hay una cuota configurada para este mes.
    </p>
    <template v-else>
      <div class="overflow-x-auto rounded-lg border">
        <table class="w-full min-w-[760px] text-left text-sm">
          <caption class="sr-only">
            Aportes de
            {{
              fundMonth(filters.month)
            }}
          </caption>
          <thead class="bg-muted/70 text-muted-foreground">
            <tr>
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
                Esperado
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Aprobado
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Pendiente
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Estado
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Comprobante
              </th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr
              v-for="person in participants.data"
              :key="person.id"
              class="transition-colors hover:bg-muted/30"
            >
              <th
                scope="row"
                class="px-4 py-3 font-medium"
              >
                {{ person.name
                }}<span
                  class="block text-xs font-normal text-muted-foreground"
                  >{{ person.email }}</span
                >
              </th>
              <td class="px-4 py-3 text-right tabular-nums">
                {{ usd(person.expected_cents) }}
              </td>
              <td class="px-4 py-3 text-right tabular-nums">
                {{ usd(person.approved_cents) }}
              </td>
              <td class="px-4 py-3 text-right font-medium tabular-nums">
                {{ usd(person.pending_cents) }}
              </td>
              <td class="px-4 py-3">
                <FundStatus
                  :status="person.status"
                  subtle
                />
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  v-if="person.transaction_id"
                  :href="
                    transactionShow(person.transaction_id, {
                      query: { return_to: page.url },
                    })
                  "
                  class="font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring"
                  :aria-label="`Ver comprobante de ${person.name}`"
                  >Abrir</Link
                ><span
                  v-else
                  class="text-muted-foreground"
                  >—</span
                >
              </td>
            </tr>
            <tr v-if="!participants.data.length">
              <td
                colspan="6"
                class="p-8 text-center text-muted-foreground"
              >
                No hay participantes con este estado.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted-foreground">
          {{ participants.total }} participantes · Página
          {{ participants.current_page }} de {{ participants.last_page }}
        </p>
        <FundPagination
          :links="participants.links"
          :last-page="participants.last_page"
          label="Páginas de aportes"
        />
      </div>
      <p class="text-xs text-muted-foreground">
        Quienes se registraron desde el día 6 figuran como “No aplica”. Un
        comprobante en revisión todavía no cuenta como pagado.
      </p>
    </template>
  </main>
</template>
