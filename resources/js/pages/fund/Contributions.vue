<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  CircleCheck,
  Clock3,
  Plus,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as contributionsIndex } from '@/routes/fund/contributions';
import {
  create as newTransaction,
  show as transactionShow,
} from '@/routes/fund/transactions';
import { Button } from '@/components/ui/button';
import FundStatus from '@/components/FundStatus.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
import { fundMonth, usd } from '@/lib/fund';

type Period = {
  id: number | null;
  month: string;
  amount_cents: number | null;
  status:
    | 'paid'
    | 'pending'
    | 'unpaid'
    | 'upcoming'
    | 'unconfigured'
    | 'before_start';
  transaction_id: number | null;
};
const props = defineProps<{
  periods: Period[];
  year: number;
  years: number[];
  yearTotalCents: number;
  hasConfiguredPeriods: boolean;
  totalCents: number;
}>();
const paidCount = computed(
  () => props.periods.filter((period) => period.status === 'paid').length,
);
const configuredCount = computed(
  () => props.periods.filter((period) => period.id !== null).length,
);
const displayedPeriods = computed(() =>
  props.periods.filter((period) => period.id !== null),
);

function changeYear(event: Event): void {
  const selectedYear = Number((event.target as HTMLSelectElement).value);
  router.get(contributionsIndex().url, { year: selectedYear });
}

defineOptions({
  layout: {
    showHeader: true,
    showMobileHeader: false,
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Aportes', href: contributionsIndex() },
    ],
  },
});
</script>

<template>
  <main class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
    <Head title="Mis aportes" />
    <section
      class="space-y-4"
      aria-label="Resumen de aportes"
    >
      <AppPageHeader title="Mis aportes">
        <template #actions>
          <Button
            class="hidden h-11 shrink-0 px-3 sm:inline-flex sm:px-4"
            size="sm"
            as-child
          >
            <Link :href="newTransaction()">Registrar aporte</Link>
          </Button>
        </template>
      </AppPageHeader>
      <div class="grid grid-cols-2 gap-3 rounded-lg border p-3 sm:gap-4 sm:p-4">
        <div class="min-w-0">
          <p class="text-xs text-muted-foreground sm:text-sm">Total aportado</p>
          <p
            class="mt-1 truncate text-xl font-semibold tabular-nums sm:text-2xl"
          >
            {{ usd(totalCents) }}
          </p>
          <p class="text-xs text-muted-foreground">En todos los años</p>
        </div>
        <div class="min-w-0 border-s ps-3 sm:ps-4">
          <p class="text-xs text-muted-foreground sm:text-sm">Cuotas pagadas</p>
          <p class="mt-1 text-xl font-semibold tabular-nums sm:text-2xl">
            {{ paidCount }}/{{ configuredCount }}
          </p>
          <p class="text-xs text-muted-foreground">En {{ year }}</p>
        </div>
      </div>
    </section>

    <section
      aria-labelledby="calendar-heading"
      class="flex flex-col gap-3 sm:gap-4"
    >
      <div
        class="sticky top-12 z-10 -mx-3 flex items-center justify-between gap-3 border-b bg-background/95 px-3 py-2 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none"
      >
        <div class="flex min-w-0 items-center gap-2">
          <h2
            id="calendar-heading"
            class="truncate text-base font-semibold sm:text-xl"
          >
            {{ year }}
          </h2>
          <span class="shrink-0 text-xs text-muted-foreground sm:hidden">
            {{ paidCount }}/{{ configuredCount }} pagadas
          </span>
        </div>
        <nav
          class="flex shrink-0 items-center gap-1"
          aria-label="Elegir año de aportes"
        >
          <Button
            v-if="years.includes(year - 1)"
            variant="ghost"
            size="icon"
            class="size-9"
            as-child
          >
            <Link
              :href="contributionsIndex({ query: { year: year - 1 } })"
              :aria-label="`Ver aportes de ${year - 1}`"
              ><ChevronLeft aria-hidden="true"
            /></Link>
          </Button>
          <label
            for="contribution-year"
            class="sr-only"
            >Año de aportes</label
          >
          <select
            id="contribution-year"
            :value="year"
            class="h-9 rounded-md border bg-background px-2 text-sm"
            @change="changeYear"
          >
            <option
              v-for="availableYear in years"
              :key="availableYear"
              :value="availableYear"
            >
              {{ availableYear }}
            </option>
          </select>
          <Button
            v-if="years.includes(year + 1)"
            variant="ghost"
            size="icon"
            class="size-9"
            as-child
          >
            <Link
              :href="contributionsIndex({ query: { year: year + 1 } })"
              :aria-label="`Ver aportes de ${year + 1}`"
              ><ChevronRight aria-hidden="true"
            /></Link>
          </Button>
        </nav>
      </div>
      <div
        class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
      >
        <div class="min-w-0">
          <h3 class="text-lg font-semibold sm:text-xl">
            Aportes de {{ year }}
          </h3>
          <p class="hidden text-sm text-muted-foreground sm:block">
            {{ paidCount }} de {{ configuredCount }} cuotas configuradas pagadas
            · {{ usd(yearTotalCents) }} aprobados este año.
          </p>
        </div>
      </div>
      <p
        v-if="!hasConfiguredPeriods"
        class="text-muted-foreground"
      >
        Todavía no hay cuotas configuradas. El tesorero define el primer período
        de aportes.
      </p>
      <p
        v-else-if="displayedPeriods.length === 0"
        class="text-muted-foreground"
      >
        No hay cuotas configuradas para {{ year }}. Puedes consultar otro año.
      </p>
      <ol
        v-else
        class="flex flex-col gap-3"
      >
        <li
          v-for="period in displayedPeriods"
          :key="period.month"
        >
          <component
            :is="period.transaction_id ? Link : 'div'"
            v-bind="
              period.transaction_id
                ? { href: transactionShow(period.transaction_id) }
                : {}
            "
            class="flex items-center gap-3 rounded-xl border bg-background px-3 py-3"
            :class="
              period.transaction_id
                ? 'transition-colors hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset'
                : ''
            "
          >
            <span
              class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
              aria-hidden="true"
            >
              <component
                :is="
                  period.status === 'paid'
                    ? CircleCheck
                    : period.status === 'pending'
                      ? Clock3
                      : CalendarDays
                "
                class="size-4"
              />
            </span>
            <div class="min-w-0 flex-1">
              <h3 class="text-sm font-semibold capitalize">
                {{ fundMonth(period.month) }}
              </h3>
              <p
                v-if="period.transaction_id"
                class="text-xs text-muted-foreground"
              >
                Comprobante #{{ period.transaction_id }}
              </p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1">
              <p class="text-sm font-semibold text-primary tabular-nums">
                {{
                  period.amount_cents !== null ? usd(period.amount_cents) : ''
                }}
              </p>
              <FundStatus
                :status="period.status"
                subtle
              />
            </div>
          </component>
        </li>
      </ol>
    </section>
    <Button
      class="fixed right-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 size-14 rounded-full shadow-lg sm:hidden"
      size="icon"
      as-child
    >
      <Link
        :href="newTransaction()"
        aria-label="Registrar aporte"
      >
        <Plus class="size-6" />
      </Link>
    </Button>
  </main>
</template>
