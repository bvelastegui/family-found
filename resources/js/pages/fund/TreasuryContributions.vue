<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import { index as contributionsIndex } from '@/routes/fund/treasury/contributions';
import { show as transactionShow } from '@/routes/fund/transactions';
import FundPagination from '@/components/FundPagination.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
import FundStatus from '@/components/FundStatus.vue';
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
    <AppPageHeader title="Aportes" />
    <form
      class="flex flex-col gap-3"
      @submit.prevent="filter"
    >
      <div class="flex min-w-0 flex-col gap-2">
        <Label for="contribution-month">Mes</Label
        ><Input
          id="contribution-month"
          v-model="month"
          type="month"
          class="h-11 min-w-0"
          required
          @change="filter"
        />
      </div>
      <div
        class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        role="group"
        aria-label="Filtrar aportes por estado"
      >
        <button
          v-for="option in [
            { label: 'Todos', value: '' },
            { label: 'Pagado', value: 'paid' },
            { label: 'Por conciliar', value: 'pending' },
            { label: 'Sin pago', value: 'unpaid' },
            { label: 'No aplica', value: 'not_applicable' },
          ]"
          :key="option.value"
          type="button"
          class="min-h-11 shrink-0 rounded-full border px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring"
          :class="
            status === option.value
              ? 'border-primary bg-primary text-primary-foreground'
              : 'bg-background text-muted-foreground hover:bg-accent'
          "
          :aria-pressed="status === option.value"
          @click="
            status = option.value;
            filter();
          "
        >
          {{ option.label }}
        </button>
      </div>
      <p
        v-if="amountCents !== null"
        class="text-sm text-muted-foreground"
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
      <p
        v-if="!participants.data.length"
        class="py-6 text-center text-sm text-muted-foreground md:hidden"
      >
        No hay participantes con este estado.
      </p>
      <ul
        v-else
        class="flex flex-col gap-3 md:hidden"
      >
        <li
          v-for="person in participants.data"
          :key="person.id"
        >
          <component
            :is="person.transaction_id ? Link : 'div'"
            v-bind="
              person.transaction_id
                ? {
                    href: transactionShow(person.transaction_id, {
                      query: { return_to: page.url },
                    }),
                  }
                : {}
            "
            class="flex flex-col gap-3 rounded-xl border px-3 py-3"
            :class="
              person.transaction_id
                ? 'transition-colors hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring'
                : ''
            "
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-semibold break-words">
                  {{ person.name }}
                </p>
                <p class="truncate text-xs text-muted-foreground">
                  {{ person.email }}
                </p>
              </div>
              <FundStatus
                :status="person.status"
                :label="
                  person.status === 'pending'
                    ? 'Por conciliar'
                    : person.status === 'unpaid'
                      ? 'Sin pago'
                      : undefined
                "
                subtle
              />
            </div>
            <p class="flex items-center justify-between gap-3 text-sm">
              <span class="text-muted-foreground">Cuota del mes</span>
              <span class="font-medium tabular-nums">{{
                usd(person.expected_cents)
              }}</span>
            </p>
            <p
              v-if="person.transaction_id"
              class="text-xs text-muted-foreground"
            >
              Comprobante #{{ person.transaction_id }}
            </p>
          </component>
        </li>
      </ul>
      <div class="hidden overflow-x-auto rounded-lg border md:block">
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
                Cuota del mes
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
              <td class="px-4 py-3">
                <FundStatus
                  :status="person.status"
                  :label="
                    person.status === 'pending'
                      ? 'Por conciliar'
                      : person.status === 'unpaid'
                        ? 'Sin pago'
                        : undefined
                  "
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
                colspan="4"
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
