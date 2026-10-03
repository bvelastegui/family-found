<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import { index as reconciliationIndex } from '@/routes/fund/treasury/reconciliation';
import { Button } from '@/components/ui/button';
import AppPageHeader from '@/components/AppPageHeader.vue';
import { fundMonth, usd } from '@/lib/fund';

const props = defineProps<{
  pendingCount: number;
  contributionChart: {
    month: string;
    expected_cents: number;
    received_cents: number;
  }[];
  balances: {
    contributions: number;
    interest: number;
    principal: number;
    cash: number;
    reserved: number;
    available: number;
  };
}>();
const balances = computed(() => [
  {
    label: 'Efectivo aprobado',
    value: props.balances.cash,
    detail: 'No incluye comprobantes en revisión.',
  },
  {
    label: 'Disponible para préstamos',
    value: props.balances.available,
    detail: 'Efectivo después de las reservas activas.',
  },
  {
    label: 'Capital reservado',
    value: props.balances.reserved,
    detail: 'Comprometido para desembolsos.',
  },
  {
    label: 'Capital prestado',
    value: props.balances.principal,
    detail: 'Principal pendiente de recuperar.',
  },
  {
    label: 'Intereses cobrados',
    value: props.balances.interest,
    detail: 'Solo pagos aprobados.',
  },
  {
    label: 'Aportes recibidos',
    value: props.balances.contributions,
    detail: 'Aportes aprobados del fondo.',
  },
]);
const chartMax = computed(() =>
  Math.max(
    1,
    ...props.contributionChart.flatMap((month) => [
      month.expected_cents,
      month.received_cents,
    ]),
  ),
);
function barHeight(amount: number): string {
  return `${amount === 0 ? 0 : Math.max(3, (amount / chartMax.value) * 100)}%`;
}
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Tesorería', href: treasuryIndex() },
    ],
  },
});
</script>

<template>
  <main
    class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:gap-8 md:p-8"
  >
    <Head title="Resumen de tesorería" />
    <AppPageHeader title="Resumen del fondo">
      <template #actions>
        <Button
          class="hidden sm:inline-flex"
          as-child
          variant="outline"
        >
          <Link :href="reconciliationIndex()"
            >Conciliación · {{ pendingCount }} pendientes</Link
          >
        </Button>
      </template>
    </AppPageHeader>
    <Link
      :href="reconciliationIndex()"
      class="flex min-h-12 items-center justify-between gap-3 rounded-xl border px-3 py-3 text-sm font-medium sm:hidden"
    >
      <span>Conciliación</span>
      <span class="rounded-full bg-primary/10 px-3 py-1 text-primary"
        >{{ pendingCount }} pendientes</span
      >
    </Link>
    <section
      aria-labelledby="balances-title"
      class="flex flex-col gap-4"
    >
      <h2
        id="balances-title"
        class="text-lg font-semibold"
      >
        Estado del fondo
      </h2>
      <dl class="flex flex-col gap-3 md:hidden">
        <div
          v-for="balance in balances"
          :key="balance.label"
          class="flex items-start justify-between gap-3 rounded-xl border px-3 py-3"
        >
          <div class="min-w-0">
            <dt class="text-sm font-medium">{{ balance.label }}</dt>
            <p class="mt-1 text-xs text-muted-foreground">
              {{ balance.detail }}
            </p>
          </div>
          <dd class="shrink-0 text-sm font-semibold text-primary tabular-nums">
            {{ usd(balance.value) }}
          </dd>
        </div>
      </dl>
      <div class="hidden overflow-x-auto rounded-lg border md:block">
        <table class="w-full min-w-lg text-left text-sm">
          <caption class="sr-only">
            Saldos del fondo en dólares
          </caption>
          <thead class="bg-muted/70 text-muted-foreground">
            <tr>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Concepto
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Detalle
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Importe
              </th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr
              v-for="balance in balances"
              :key="balance.label"
              class="transition-colors hover:bg-muted/30"
            >
              <th
                scope="row"
                class="px-4 py-3 font-medium"
              >
                {{ balance.label }}
              </th>
              <td class="px-4 py-3 text-muted-foreground">
                {{ balance.detail }}
              </td>
              <td class="px-4 py-3 text-right font-medium tabular-nums">
                {{ usd(balance.value) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <section
      aria-labelledby="chart-title"
      class="flex flex-col gap-4"
    >
      <div>
        <h2
          id="chart-title"
          class="text-lg font-semibold"
        >
          Aportes de los últimos meses
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
          Cuotas esperadas al día 5 frente a aportes aprobados. Los comprobantes
          en revisión no se incluyen.
        </p>
      </div>
      <p
        v-if="!contributionChart.length"
        class="py-8 text-center text-sm text-muted-foreground"
      >
        No hay períodos configurados para mostrar.
      </p>
      <figure v-else>
        <ul
          class="flex flex-col gap-4 md:hidden"
          aria-label="Aportes esperados y aprobados por mes"
        >
          <li
            v-for="month in contributionChart"
            :key="month.month"
            class="space-y-2"
          >
            <div class="flex items-center justify-between gap-3 text-sm">
              <p class="font-medium capitalize">{{ fundMonth(month.month) }}</p>
              <p class="text-right text-xs text-muted-foreground tabular-nums">
                {{ usd(month.received_cents) }} de
                {{ usd(month.expected_cents) }}
              </p>
            </div>
            <div
              class="flex flex-col gap-1"
              aria-hidden="true"
            >
              <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full bg-muted-foreground/50"
                  :style="{ width: barHeight(month.expected_cents) }"
                />
              </div>
              <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                  class="h-full rounded-full bg-primary"
                  :style="{ width: barHeight(month.received_cents) }"
                />
              </div>
            </div>
          </li>
        </ul>
        <div class="mb-4 flex flex-wrap gap-x-5 gap-y-2 text-sm">
          <span class="flex items-center gap-2"
            ><span
              class="size-3 rounded-sm bg-muted-foreground/50"
              aria-hidden="true"
            />Esperado</span
          >
          <span class="flex items-center gap-2"
            ><span
              class="size-3 rounded-sm bg-primary"
              aria-hidden="true"
            />Aprobado</span
          >
        </div>
        <div class="hidden overflow-x-auto md:block">
          <div
            class="flex min-w-[36rem] items-end gap-4 border-b pb-3"
            aria-hidden="true"
          >
            <div
              v-for="month in contributionChart"
              :key="month.month"
              class="flex h-44 min-w-0 flex-1 items-end justify-center gap-1"
            >
              <div
                class="w-6 rounded-t-sm bg-muted-foreground/50"
                :style="{ height: barHeight(month.expected_cents) }"
              />
              <div
                class="w-6 rounded-t-sm bg-primary"
                :style="{ height: barHeight(month.received_cents) }"
              />
            </div>
          </div>
          <div class="flex min-w-[36rem] gap-4 pt-3 text-center text-xs">
            <div
              v-for="month in contributionChart"
              :key="month.month"
              class="min-w-0 flex-1"
            >
              <p class="font-medium">{{ fundMonth(month.month) }}</p>
              <p class="text-muted-foreground">
                {{ usd(month.received_cents) }} de
                {{ usd(month.expected_cents) }}
              </p>
            </div>
          </div>
        </div>
        <figcaption class="mt-3 text-xs text-muted-foreground">
          El esperado se calcula con las personas registradas antes del día 6 de
          cada mes.
        </figcaption>
      </figure>
    </section>
  </main>
</template>
