<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as loansIndex, show as showLoan } from '@/routes/fund/loans';
import { create as createLoan } from '@/routes/fund/treasury/loans';
import FundPagination from '@/components/FundPagination.vue';
import FundStatus from '@/components/FundStatus.vue';
import { Button } from '@/components/ui/button';
import { usd, type FundPagination as Pagination } from '@/lib/fund';

type Loan = {
  id: number;
  principal_cents: number;
  outstanding_cents: number;
  monthly_rate: string;
  term_months: number;
  status: string;
  user: { name: string };
};
defineProps<{
  loans: Pagination<Loan>;
  isTreasurer: boolean;
  reservedLoans: Pagination<Loan> | null;
}>();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Préstamos', href: loansIndex() },
    ],
  },
});
</script>

<template>
  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
    <Head title="Préstamos" />
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm text-muted-foreground">Capital del fondo</p>
        <h1 class="text-3xl font-semibold tracking-tight">Préstamos</h1>
        <p class="mt-1 text-muted-foreground">
          Consulta sus condiciones, saldo pendiente y tabla de amortización.
        </p>
      </div>
      <Button
        v-if="isTreasurer"
        as-child
      >
        <Link :href="createLoan()">Reservar nuevo préstamo</Link>
      </Button>
    </header>
    <section
      v-if="reservedLoans"
      aria-labelledby="reserved-title"
      class="flex flex-col gap-4"
    >
      <div>
        <h2
          id="reserved-title"
          class="text-lg font-semibold"
        >
          Reservas pendientes de desembolso
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ reservedLoans.total }} reservas. Todavía no generan movimientos
          contables.
        </p>
      </div>
      <div class="overflow-x-auto rounded-lg border">
        <table class="w-full min-w-lg text-left text-sm">
          <caption class="sr-only">
            Préstamos reservados
          </caption>
          <thead class="bg-muted/70 text-muted-foreground">
            <tr>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Préstamo
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
                Capital reservado
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
              v-for="loan in reservedLoans.data"
              :key="loan.id"
              class="hover:bg-muted/30"
            >
              <th
                scope="row"
                class="px-4 py-3 font-medium"
              >
                #{{ loan.id }}
              </th>
              <td class="px-4 py-3">{{ loan.user.name }}</td>
              <td class="px-4 py-3 text-right tabular-nums">
                {{ usd(loan.principal_cents) }}
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  :href="showLoan(loan.id)"
                  class="font-medium underline underline-offset-4"
                  >Abrir</Link
                >
              </td>
            </tr>
            <tr v-if="!reservedLoans.data.length">
              <td
                colspan="4"
                class="p-8 text-center text-muted-foreground"
              >
                No hay reservas pendientes.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <FundPagination
        :links="reservedLoans.links"
        :last-page="reservedLoans.last_page"
        label="Páginas de reservas"
      />
    </section>
    <section
      class="flex flex-col gap-4"
      aria-labelledby="loans-title"
    >
      <div>
        <h2
          id="loans-title"
          class="text-lg font-semibold"
        >
          Historial de préstamos
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
          Las reservas y los desembolsos mantienen su propio historial.
        </p>
      </div>
      <p
        v-if="!loans.data.length"
        class="py-8 text-center text-sm text-muted-foreground"
      >
        Todavía no hay préstamos registrados.
      </p>
      <div
        v-else
        class="overflow-x-auto rounded-md border"
      >
        <table class="w-full min-w-[760px] text-left text-sm">
          <caption class="sr-only">
            Historial de préstamos y sus condiciones
          </caption>
          <thead class="bg-muted/70 text-muted-foreground">
            <tr>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Préstamo
              </th>
              <th
                v-if="isTreasurer"
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Prestatario
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Principal
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Pendiente
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Tasa mensual
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Plazo
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
                Detalle
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="loan in loans.data"
              :key="loan.id"
              class="border-t transition-colors hover:bg-muted/30"
            >
              <th
                scope="row"
                class="px-4 py-3 font-medium"
              >
                #{{ loan.id }}
              </th>
              <td
                v-if="isTreasurer"
                class="px-4 py-3"
              >
                {{ loan.user.name }}
              </td>
              <td class="px-4 py-3 text-right tabular-nums">
                {{ usd(loan.principal_cents) }}
              </td>
              <td class="px-4 py-3 text-right font-medium tabular-nums">
                {{ usd(loan.outstanding_cents) }}
              </td>
              <td class="px-4 py-3 text-right tabular-nums">
                {{ loan.monthly_rate }} %
              </td>
              <td class="px-4 py-3 text-right tabular-nums">
                {{ loan.term_months }} meses
              </td>
              <td class="px-4 py-3">
                <FundStatus :status="loan.status" />
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  class="font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring"
                  :href="showLoan(loan.id)"
                  :aria-label="`Ver préstamo ${loan.id}`"
                  >Abrir</Link
                >
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <FundPagination
      :links="loans.links"
      :last-page="loans.last_page"
      label="Páginas de préstamos"
    />
  </main>
</template>
