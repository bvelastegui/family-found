<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as loansIndex, show as showLoan } from '@/routes/fund/loans';
import { create as createLoan } from '@/routes/fund/treasury/loans';
import FundPagination from '@/components/FundPagination.vue';
import FundStatus from '@/components/FundStatus.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
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
    <AppPageHeader title="Préstamos">
      <template #actions>
        <Button
          v-if="isTreasurer"
          class="hidden sm:inline-flex"
          as-child
        >
          <Link :href="createLoan()">Reservar nuevo préstamo</Link>
        </Button>
      </template>
    </AppPageHeader>
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
      <p
        v-if="!reservedLoans.data.length"
        class="text-sm text-muted-foreground md:hidden"
      >
        No hay reservas pendientes.
      </p>
      <ul
        v-else
        class="flex flex-col gap-3 md:hidden"
      >
        <li
          v-for="loan in reservedLoans.data"
          :key="loan.id"
        >
          <Link
            :href="showLoan(loan.id)"
            class="flex min-h-16 items-center justify-between gap-3 rounded-xl border p-3 hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring"
          >
            <div class="min-w-0">
              <p class="text-sm font-semibold break-words">
                {{ loan.user.name }}
              </p>
              <p class="text-xs text-muted-foreground">
                Reserva #{{ loan.id }}
              </p>
            </div>
            <span class="shrink-0 text-sm font-semibold tabular-nums">{{
              usd(loan.principal_cents)
            }}</span>
          </Link>
        </li>
      </ul>
      <div class="hidden overflow-x-auto rounded-lg border md:block">
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
        class="min-w-0"
      >
        <ul class="flex flex-col gap-3 md:hidden">
          <li
            v-for="loan in loans.data"
            :key="loan.id"
          >
            <Link
              :href="showLoan(loan.id)"
              class="flex flex-col gap-3 rounded-xl border p-3 hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring"
              :aria-label="`Ver préstamo ${loan.id}`"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-sm font-semibold">Préstamo #{{ loan.id }}</p>
                  <p
                    v-if="isTreasurer"
                    class="text-xs break-words text-muted-foreground"
                  >
                    {{ loan.user.name }}
                  </p>
                </div>
                <FundStatus
                  :status="loan.status"
                  subtle
                />
              </div>
              <dl class="grid grid-cols-2 gap-3 text-xs">
                <div>
                  <dt class="text-muted-foreground">Monto del préstamo</dt>
                  <dd class="mt-1 text-sm font-medium tabular-nums">
                    {{ usd(loan.principal_cents) }}
                  </dd>
                </div>
                <div>
                  <dt class="text-muted-foreground">Saldo pendiente</dt>
                  <dd
                    class="mt-1 text-sm font-semibold text-primary tabular-nums"
                  >
                    {{ usd(loan.outstanding_cents) }}
                  </dd>
                </div>
              </dl>
              <p class="text-xs text-muted-foreground">
                {{ loan.term_months }} meses · {{ loan.monthly_rate }} % mensual
              </p>
            </Link>
          </li>
        </ul>
        <div class="hidden overflow-x-auto rounded-lg border md:block">
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
      </div>
    </section>
    <FundPagination
      :links="loans.links"
      :last-page="loans.last_page"
      label="Páginas de préstamos"
    />
    <Button
      v-if="isTreasurer"
      class="fixed right-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 size-14 rounded-full shadow-lg sm:hidden"
      size="icon"
      as-child
    >
      <Link
        :href="createLoan()"
        aria-label="Reservar nuevo préstamo"
        ><Plus class="size-6"
      /></Link>
    </Button>
  </main>
</template>
