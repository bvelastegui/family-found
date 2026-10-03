<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, usePage, setLayoutProps } from '@inertiajs/vue3';
import { ArrowDownLeft, ArrowUpRight } from '@lucide/vue';
import { index as reconciliationIndex } from '@/routes/fund/treasury/reconciliation';
import { index as treasuryIndex } from '@/routes/fund/treasury';
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
  reconciliation: boolean;
}>();
const page = usePage();
const listingRoute = props.reconciliation
  ? reconciliationIndex
  : transactionsIndex;
const title = props.reconciliation ? 'Conciliación' : 'Mis transacciones';
setLayoutProps({
  breadcrumbs: [
    { title: 'Inicio', href: dashboard() },
    ...(props.reconciliation
      ? [{ title: 'Tesorería', href: treasuryIndex() }]
      : []),
    { title, href: listingRoute() },
  ],
});

const status = ref(props.filters.status);
const type = ref(props.filters.type);
const search = ref(props.filters.search);
let searchTimeout: ReturnType<typeof setTimeout> | undefined;
const hasFilters =
  props.filters.status !== '' ||
  props.filters.type !== '' ||
  props.filters.search !== '';

function filter(): void {
  router.get(
    listingRoute().url,
    {
      status: status.value,
      ...(type.value ? { type: type.value } : {}),
      ...(search.value.trim() ? { search: search.value.trim() } : {}),
    },
    { replace: true },
  );
}

watch(search, (value) => {
  if (!props.reconciliation) {
    return;
  }

  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(
      listingRoute().url,
      {
        status: status.value,
        ...(type.value ? { type: type.value } : {}),
        ...(value.trim() ? { search: value.trim() } : {}),
      },
      { replace: true, preserveState: true, preserveScroll: true },
    );
  }, 350);
});

onBeforeUnmount(() => clearTimeout(searchTimeout));
</script>

<template>
  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
    <Head :title="title" />
    <header
      v-if="!reconciliation"
      class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4"
    >
      <div>
        <p class="text-sm text-muted-foreground">
          {{ reconciliation ? 'Tesorería' : 'Mi cuenta' }}
        </p>
        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">{{ title }}</h1>
        <p class="mt-1 text-sm text-muted-foreground sm:text-base">
          {{
            reconciliation
              ? 'Revisa comprobantes y consulta las decisiones de todo el fondo.'
              : 'Tus aportes y cuotas de préstamo, desde el registro hasta su aprobación.'
          }}
        </p>
      </div>
      <Button
        v-if="!reconciliation"
        as-child
        ><Link :href="newTransaction()">Registrar transferencia</Link></Button
      >
    </header>

    <section
      v-if="reconciliation"
      class="-mx-4 -mt-4 bg-gradient-to-b from-primary/90 via-primary to-primary/75 px-4 pb-7 pt-5 text-primary-foreground md:-mx-8 md:-mt-8 md:px-8"
      aria-labelledby="reconciliation-title"
    >
      <h1 id="reconciliation-title" class="text-center text-lg font-semibold">
        Conciliación
      </h1>
      <p class="mt-5 text-center text-sm text-white/75">
        Transferencias del fondo
      </p>
      <p class="mt-1 text-center text-3xl font-semibold tracking-tight">
        {{ transactions.total }}
        <span class="text-lg font-medium">{{ transactions.total === 1 ? 'registro' : 'registros' }}</span>
      </p>
    </section>

    <form
      v-if="!reconciliation"
      class="grid grid-cols-1 gap-3 sm:flex sm:flex-wrap sm:items-end"
      role="search"
      @submit.prevent="filter"
    >
      <div class="flex min-w-0 flex-col gap-2 sm:min-w-48 sm:flex-1">
        <Label for="transaction-search"
          >Buscar comprobante o banco<span v-if="reconciliation"
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
      <div class="flex flex-col gap-2 sm:min-w-32">
        <Label for="status-filter">Estado</Label
        ><select
          id="status-filter"
          v-model="status"
          class="h-11 w-full rounded-md border bg-background px-3 text-sm sm:h-9 sm:w-auto"
          @change="filter"
        >
          <option value="">Todos</option>
          <option value="pending">Pendiente</option>
          <option value="approved">Aprobados</option>
          <option value="rejected">Rechazados</option>
        </select>
      </div>
      <div class="flex flex-col gap-2 sm:min-w-32">
        <Label for="type-filter">Destino</Label
        ><select
          id="type-filter"
          v-model="type"
          class="h-11 w-full rounded-md border bg-background px-3 text-sm sm:h-9 sm:w-auto"
          @change="filter"
        >
          <option value="">Todos</option>
          <option value="contribution">Aportes</option>
          <option value="loan">Préstamos</option>
        </select>
      </div>
      <Button
        type="submit"
        class="h-11 w-full sm:w-auto"
        >Buscar</Button
      >
      <Button
        v-if="hasFilters"
        variant="outline"
        class="h-11 w-full sm:w-auto"
        as-child
        ><Link :href="listingRoute()">Limpiar</Link></Button
      >
    </form>

    <form
      v-if="reconciliation"
      class="relative z-10 -mt-11 flex items-center gap-2 rounded-full border bg-background px-3 py-1.5 shadow-md"
      role="search"
      @submit.prevent="filter"
    >
      <Input
        id="transaction-search"
        v-model="search"
        maxlength="100"
        type="search"
        class="h-9 min-w-0 flex-1 border-0 bg-background! px-1 shadow-none focus-visible:bg-background! focus-visible:ring-0 focus-visible:ring-offset-0"
        placeholder="Buscar..."
        aria-label="Buscar comprobante, banco o participante"
      />
      <select
        id="type-filter"
        v-model="type"
        class="h-9 max-w-32 shrink-0 rounded-full border-0 bg-transparent px-2 text-xs text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label="Filtrar por destino"
        @change="filter"
      >
        <option value="">Destino</option>
        <option value="contribution">Aportes</option>
        <option value="loan">Préstamos</option>
      </select>
    </form>

    <nav
      v-if="reconciliation"
      class="-mt-2 flex gap-2 overflow-x-auto pb-1"
      aria-label="Filtrar por estado"
    >
      <Link
        v-for="option in [
          { label: 'Todos', value: '' },
          { label: 'Pendiente', value: 'pending' },
          { label: 'Aprobados', value: 'approved' },
          { label: 'Rechazados', value: 'rejected' },
        ]"
        :key="option.value || 'all'"
        :href="listingRoute({ query: { status: option.value, type, search } })"
        class="shrink-0 rounded-full border px-3 py-1.5 text-sm"
        :class="status === option.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-background text-muted-foreground'"
      >
        {{ option.label }}
      </Link>
    </nav>

    <section
      aria-label="Historial de transacciones"
      class="flex flex-col gap-4"
    >
      <p v-if="!reconciliation" class="text-sm text-muted-foreground">
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
        class="rounded-lg border"
      >
        <table
          v-if="!reconciliation"
          class="w-full min-w-[900px] text-left text-sm"
        >
          <caption class="sr-only">
            Historial de transacciones
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
              <td class="px-4 py-3">
                <FundStatus
                  :status="item.status"
                  subtle
                />
              </td>
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
        <div
          v-if="reconciliation"
          class="divide-y"
        >
          <Link
            v-for="item in transactions.data"
            :key="item.id"
            :href="transactionShow(item.id, { query: { return_to: page.url } })"
            class="flex flex-col gap-2.5 px-3 py-3 transition-colors hover:bg-muted/30 focus-visible:bg-muted/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
            :aria-label="`Ver transacción ${item.reference} de ${item.user.name}, ${usd(item.amount_cents)}, ${item.status}`"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex min-w-0 items-center gap-3">
                <span
                  class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                  aria-hidden="true"
                >
                  <ArrowDownLeft v-if="item.status === 'pending'" class="size-4" />
                  <ArrowUpRight v-else class="size-4" />
                </span>
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold">{{ item.user.name }}</p>
                  <p class="truncate text-xs text-muted-foreground">
                    {{ item.bank_name }} · {{ item.reference }}
                  </p>
                </div>
              </div>
              <p
                class="shrink-0 text-right text-sm font-semibold tabular-nums"
                :class="item.status === 'rejected' ? 'text-destructive' : 'text-primary'"
              >
                {{ usd(item.amount_cents) }}
              </p>
            </div>
            <div class="flex items-center justify-between gap-2 pl-12">
              <p class="min-w-0 truncate text-xs text-muted-foreground">
                {{ fundDate(item.transaction_date) }}
                <template v-if="item.approved_by && item.approved_at">
                  · {{ fundDateTime(item.approved_at) }}
                </template>
              </p>
              <FundStatus :status="item.status" subtle />
            </div>
          </Link>
        </div>
        <div
          v-if="reconciliation"
          class="hidden overflow-x-auto"
        >
          <table class="w-full min-w-[900px] text-left text-sm">
            <caption class="sr-only">
              Transacciones y estado de conciliación
            </caption>
            <thead class="bg-muted/70 text-muted-foreground">
              <tr>
                <th scope="col" class="px-4 py-3 font-medium">
                  Fecha
                </th>
                <th scope="col" class="px-4 py-3 font-medium">
                  Participante
                </th>
                <th scope="col" class="px-4 py-3 font-medium">
                  Comprobante
                </th>
                <th scope="col" class="px-4 py-3 font-medium">
                  Destino
                </th>
                <th scope="col" class="px-4 py-3 text-right font-medium">
                  Monto
                </th>
                <th scope="col" class="px-4 py-3 font-medium">
                  Estado
                </th>
                <th scope="col" class="px-4 py-3 font-medium">
                  Autorización
                </th>
                <th scope="col" class="px-4 py-3 text-right font-medium">
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
                <td class="px-4 py-3">{{ item.user.name }}</td>
                <td class="px-4 py-3">
                  <span class="font-medium">{{ item.reference }}</span>
                  <span class="block text-xs text-muted-foreground">
                    {{ item.bank_name }}
                  </span>
                </td>
                <td class="px-4 py-3">{{ item.destination_label }}</td>
                <td class="px-4 py-3 text-right font-medium tabular-nums">
                  {{ usd(item.amount_cents) }}
                </td>
                <td class="px-4 py-3">
                  <FundStatus
                    :status="item.status"
                    subtle
                  />
                </td>
                <td class="px-4 py-3">
                  <template v-if="item.approved_by && item.approved_at">
                    <span class="font-medium">{{ item.approved_by }}</span>
                    <span class="block text-xs text-muted-foreground">
                      {{ fundDateTime(item.approved_at) }}
                    </span>
                  </template>
                  <span
                    v-else
                    class="text-muted-foreground"
                    >—</span
                  >
                </td>
                <td class="px-4 py-3 text-right">
                  <Link
                    :href="transactionShow(item.id, { query: { return_to: page.url } })"
                    :aria-label="`Ver comprobante ${item.reference}`"
                    class="font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring"
                    >Abrir</Link
                  >
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
    <FundPagination
      :links="transactions.links"
      :last-page="transactions.last_page"
      label="Páginas de transacciones"
    />
  </main>
</template>
