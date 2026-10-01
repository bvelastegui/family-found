<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import {
  index as transactionsIndex,
  show as transactionShow,
  create as newTransaction,
} from '@/routes/fund/transactions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FundPagination from '@/components/FundPagination.vue';
import FundStatus from '@/components/FundStatus.vue';
import {
  fundDate,
  fundDateTime,
  usd,
  type FundPagination as Pagination,
} from '@/lib/fund';

type Transaction = {
  id: number;
  amount_cents: number;
  transaction_date: string;
  status: string;
  bank_name: string;
  reference: string;
  destination_label: string;
  approved_by: string | null;
  approved_at: string | null;
  user: { name: string };
};
const props = defineProps<{
  transactions: Pagination<Transaction>;
  filters: { status: string; type: string; search: string };
  isTreasurer: boolean;
}>();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Transacciones', href: transactionsIndex() },
    ],
  },
});

const status = ref(props.filters.status);
const type = ref(props.filters.type);
const search = ref(props.filters.search);
const hasFilters =
  props.filters.status !== '' ||
  props.filters.type !== '' ||
  props.filters.search !== '';

function filter(): void {
  router.get(
    transactionsIndex().url,
    {
      ...(status.value ? { status: status.value } : {}),
      ...(type.value ? { type: type.value } : {}),
      ...(search.value.trim() ? { search: search.value.trim() } : {}),
    },
    { replace: true },
  );
}
</script>

<template>
  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
    <Head title="Transacciones" />
    <header class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-sm text-muted-foreground">Comprobantes y movimientos</p>
        <h1 class="text-3xl font-semibold tracking-tight">Transacciones</h1>
        <p class="mt-1 text-muted-foreground">
          Aportes y cuotas de préstamo, desde el registro hasta su conciliación.
        </p>
      </div>
      <Button as-child
        ><Link :href="newTransaction()">Registrar transferencia</Link></Button
      >
    </header>

    <form
      class="flex flex-wrap items-end gap-3 rounded-lg border bg-muted/20 p-4"
      role="search"
      @submit.prevent="filter"
    >
      <div class="flex min-w-48 flex-1 flex-col gap-2">
        <Label for="transaction-search"
          >Buscar comprobante o banco<span v-if="isTreasurer"
            >, o participante</span
          ></Label
        ><Input
          id="transaction-search"
          v-model="search"
          maxlength="100"
          type="search"
          placeholder="Número de comprobante o banco"
        />
      </div>
      <div class="flex flex-col gap-2">
        <Label for="status-filter">Estado</Label
        ><select
          id="status-filter"
          v-model="status"
          class="h-9 rounded-md border bg-background px-3 text-sm"
          @change="filter"
        >
          <option value="">Todos</option>
          <option value="pending">En revisión</option>
          <option value="approved">Aprobados</option>
          <option value="rejected">Rechazados</option>
        </select>
      </div>
      <div class="flex flex-col gap-2">
        <Label for="type-filter">Destino</Label
        ><select
          id="type-filter"
          v-model="type"
          class="h-9 rounded-md border bg-background px-3 text-sm"
          @change="filter"
        >
          <option value="">Todos</option>
          <option value="contribution">Aportes</option>
          <option value="loan">Préstamos</option>
        </select>
      </div>
      <Button type="submit">Buscar</Button>
      <Button
        v-if="hasFilters"
        variant="outline"
        as-child
        ><Link :href="transactionsIndex()">Limpiar</Link></Button
      >
    </form>

    <section
      aria-label="Historial de transacciones"
      class="flex flex-col gap-4"
    >
      <p class="text-sm text-muted-foreground">
        {{ transactions.total }}
        {{ transactions.total === 1 ? 'transacción' : 'transacciones' }}
        {{ hasFilters ? 'con estos filtros' : 'registradas' }}. Los registros en
        revisión aún no afectan el fondo.
      </p>
      <p
        v-if="transactions.data.length === 0"
        class="rounded-lg border p-8 text-center text-muted-foreground"
      >
        No hay transacciones que coincidan con tu búsqueda.
      </p>
      <div
        v-else
        class="overflow-x-auto rounded-lg border"
      >
        <table class="w-full min-w-[900px] text-left text-sm">
          <caption class="sr-only">
            Transacciones y estado de conciliación
          </caption>
          <thead class="bg-muted/50 text-muted-foreground">
            <tr>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Fecha
              </th>
              <th
                v-if="isTreasurer"
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Participante
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Comprobante
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Destino
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Monto
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Estado
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Autorización
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Detalle
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in transactions.data"
              :key="item.id"
              class="border-t transition-colors hover:bg-muted/30"
            >
              <td class="px-4 py-3 whitespace-nowrap">
                {{ fundDate(item.transaction_date) }}
              </td>
              <td
                v-if="isTreasurer"
                class="px-4 py-3"
              >
                {{ item.user.name }}
              </td>
              <td class="px-4 py-3">
                <span class="font-medium">{{ item.reference }}</span
                ><span class="block text-xs text-muted-foreground">{{
                  item.bank_name
                }}</span>
              </td>
              <td class="px-4 py-3">{{ item.destination_label }}</td>
              <td class="px-4 py-3 text-right font-medium tabular-nums">
                {{ usd(item.amount_cents) }}
              </td>
              <td class="px-4 py-3"><FundStatus :status="item.status" /></td>
              <td class="px-4 py-3">
                <template v-if="item.approved_by && item.approved_at"
                  ><span class="font-medium">{{ item.approved_by }}</span
                  ><span class="block text-xs text-muted-foreground">{{
                    fundDateTime(item.approved_at)
                  }}</span></template
                ><span
                  v-else
                  class="text-muted-foreground"
                  >—</span
                >
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  :href="transactionShow(item.id)"
                  :aria-label="`Ver comprobante ${item.reference}`"
                  class="font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring"
                  >Abrir</Link
                >
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <FundPagination
      :links="transactions.links"
      :last-page="transactions.last_page"
      label="Páginas de transacciones"
    />
  </main>
</template>
