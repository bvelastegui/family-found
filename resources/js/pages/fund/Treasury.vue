<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import { show as transactionShow } from '@/routes/fund/transactions';
import { show as loanShow } from '@/routes/fund/loans';
import { create as createLoan } from '@/routes/fund/treasury/loans';
import { computed } from 'vue';
import FundStatCard from '@/components/FundStatCard.vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import FundPagination from '@/components/FundPagination.vue';
import {
  fundDate,
  fundMonth,
  usd,
  type FundPagination as Pagination,
} from '@/lib/fund';

type Pending = {
  id: number;
  amount_cents: number;
  transaction_date: string;
  bank_name: string;
  reference: string;
  user: { name: string };
};
type Reserved = { id: number; principal_cents: number; user: { name: string } };
type Unpaid = {
  id: number;
  name: string;
  email: string;
  pending_transaction_id: number | null;
};
type ChartMonth = {
  month: string;
  expected_cents: number;
  received_cents: number;
};
const props = defineProps<{
  pending: Pagination<Pending>;
  reservedLoans: Reserved[];
  unpaid: Pagination<Unpaid> | null;
  contributionMonth: string | null;
  contributionChart: ChartMonth[];
  balances: {
    contributions: number;
    interest: number;
    principal: number;
    cash: number;
    reserved: number;
    available: number;
  };
}>();
const stats = computed(() => [
  {
    label: 'Efectivo aprobado',
    value: usd(props.balances.cash),
    badge: 'Conciliado',
    headline: 'Dinero reconocido en el fondo',
    detail: 'No incluye transferencias pendientes.',
  },
  {
    label: 'Disponible para préstamos',
    value: usd(props.balances.available),
    badge: 'Disponible',
    headline: 'Capital listo para prestar',
    detail: 'Efectivo después de las reservas activas.',
  },
  {
    label: 'Capital reservado',
    value: usd(props.balances.reserved),
    badge: 'Reservado',
    headline: 'Comprometido para desembolsos',
    detail: 'Estas reservas todavía no son préstamos.',
  },
  {
    label: 'Capital prestado',
    value: usd(props.balances.principal),
    badge: 'Por cobrar',
    headline: 'Principal pendiente de recuperar',
    detail: 'No se contabiliza como ganancia.',
  },
  {
    label: 'Intereses cobrados',
    value: usd(props.balances.interest),
    badge: 'Ganancia',
    headline: 'Rendimiento del fondo',
    detail: 'Solo intereses de pagos aprobados.',
  },
  {
    label: 'Aportes recibidos',
    value: usd(props.balances.contributions),
    badge: 'Aprobados',
    headline: 'Aportes mensuales del fondo',
    detail: 'No son retirables por sus participantes.',
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
  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
    <Head title="Tesorería" />
    <header>
      <div>
        <p class="text-sm text-muted-foreground">Operaciones del fondo</p>
        <h1 class="text-3xl font-semibold tracking-tight">Tesorería</h1>
        <p class="mt-1 text-muted-foreground">
          Conciliación, aportes del mes y estado del fondo.
        </p>
      </div>
    </header>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <FundStatCard
        v-for="stat in stats.slice(0, 3)"
        :key="stat.label"
        v-bind="stat"
      />
    </div>
    <Card
      ><CardHeader
        ><CardTitle
          >Por conciliar
          <Badge variant="secondary"
            >{{ pending.data.length }} en esta página</Badge
          ></CardTitle
        ><CardDescription
          >Abre el comprobante para aprobarlo o rechazarlo con un
          motivo.</CardDescription
        ></CardHeader
      ><CardContent
        ><p
          v-if="!pending.data.length"
          class="py-6 text-center text-muted-foreground"
        >
          No hay transacciones esperando revisión.
        </p>
        <div
          v-else
          class="divide-y"
        >
          <Link
            v-for="item in pending.data"
            :key="item.id"
            :href="transactionShow(item.id)"
            class="flex flex-wrap items-center justify-between gap-3 py-4 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring"
            ><div>
              <p class="font-medium">
                {{ item.user.name }} ·
                {{ usd(item.amount_cents) }}
              </p>
              <p class="text-sm text-muted-foreground">
                {{ fundDate(item.transaction_date) }} · {{ item.bank_name }} ·
                {{ item.reference }}
              </p>
            </div>
            <span class="text-sm font-medium underline underline-offset-4"
              >Verificar</span
            ></Link
          >
        </div></CardContent
      ></Card
    ><FundPagination
      :links="pending.links"
      :last-page="pending.last_page"
      label="Páginas de transferencias por conciliar"
    />
    <Card>
      <CardHeader>
        <CardTitle>Aportes pendientes al día 5</CardTitle>
        <CardDescription>
          <template v-if="contributionMonth">
            {{ fundMonth(contributionMonth) }} · Personas con cuenta antes del
            día 6 que aún no tienen este aporte aprobado. Un comprobante en
            revisión no cuenta como pagado.
          </template>
          <template v-else
            >Este mes aún no tiene una cuota configurada.</template
          >
        </CardDescription>
      </CardHeader>
      <CardContent>
        <p
          v-if="!contributionMonth"
          class="text-sm text-muted-foreground"
        >
          Configura la cuota del mes para consultar los aportes.
        </p>
        <p
          v-else-if="!unpaid"
          class="text-sm text-muted-foreground"
        >
          La lista estará disponible a partir del día 6.
        </p>
        <p
          v-else-if="!unpaid.total"
          class="text-sm text-muted-foreground"
        >
          Todas las personas incluidas tienen su aporte aprobado.
        </p>
        <template v-else>
          <p class="mb-4 text-sm font-medium">
            {{ unpaid.total }}
            {{
              unpaid.total === 1 ? 'persona pendiente' : 'personas pendientes'
            }}
          </p>
          <div class="overflow-x-auto rounded-md border">
            <table class="w-full min-w-lg text-left text-sm">
              <thead class="bg-muted/50 text-muted-foreground">
                <tr>
                  <th
                    scope="col"
                    class="px-4 py-3 font-medium"
                  >
                    Persona
                  </th>
                  <th
                    scope="col"
                    class="px-4 py-3 font-medium"
                  >
                    Situación
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
                  v-for="person in unpaid.data"
                  :key="person.id"
                >
                  <td class="px-4 py-3">
                    <span class="font-medium">{{ person.name }}</span
                    ><span class="block text-muted-foreground">{{
                      person.email
                    }}</span>
                  </td>
                  <td class="px-4 py-3">
                    {{
                      person.pending_transaction_id
                        ? 'En revisión'
                        : 'Sin depósito registrado'
                    }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <Link
                      v-if="person.pending_transaction_id"
                      :href="transactionShow(person.pending_transaction_id)"
                      class="font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring"
                      >Revisar</Link
                    >
                    <span
                      v-else
                      class="text-muted-foreground"
                      >—</span
                    >
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <FundPagination
            :links="unpaid.links"
            :last-page="unpaid.last_page"
            label="Páginas de aportes pendientes"
          />
        </template>
      </CardContent>
    </Card>
    <Card>
      <CardHeader>
        <CardTitle>Aportes de los últimos meses</CardTitle>
        <CardDescription
          >Cuotas esperadas al día 5 frente a aportes aprobados de cada mes. Los
          comprobantes en revisión no se incluyen.</CardDescription
        >
      </CardHeader>
      <CardContent>
        <p
          v-if="!contributionChart.length"
          class="text-sm text-muted-foreground"
        >
          No hay períodos configurados para mostrar.
        </p>
        <figure v-else>
          <div class="mb-4 flex flex-wrap gap-x-5 gap-y-2 text-sm">
            <span class="flex items-center gap-2"
              ><span
                class="size-3 rounded-sm bg-muted-foreground/50"
                aria-hidden="true"
              />
              Esperado</span
            >
            <span class="flex items-center gap-2"
              ><span
                class="size-3 rounded-sm bg-primary"
                aria-hidden="true"
              />
              Aprobado</span
            >
          </div>
          <div class="overflow-x-auto">
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
            El esperado se calcula con las personas registradas antes del día 6
            de cada mes.
          </figcaption>
        </figure>
      </CardContent>
    </Card>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <FundStatCard
        v-for="stat in stats.slice(3)"
        :key="stat.label"
        v-bind="stat"
      />
    </div>
    <Card
      ><CardHeader
        ><div class="flex flex-wrap items-center justify-between gap-3">
          <CardTitle>Préstamos reservados</CardTitle>
          <Button
            as-child
            size="sm"
          >
            <Link :href="createLoan()">Reservar nuevo préstamo</Link>
          </Button>
        </div>
        <CardDescription
          >Reservas pendientes de desembolso, sin asientos
          contables.</CardDescription
        ></CardHeader
      ><CardContent
        ><p
          v-if="!reservedLoans.length"
          class="text-sm text-muted-foreground"
        >
          No hay reservas pendientes.
        </p>
        <ul
          v-else
          class="divide-y"
        >
          <li
            v-for="loan in reservedLoans"
            :key="loan.id"
            class="flex items-center justify-between gap-3 py-3"
          >
            <span>{{ loan.user.name }} · {{ usd(loan.principal_cents) }}</span
            ><Link
              class="text-sm font-medium underline underline-offset-4"
              :href="loanShow(loan.id)"
              >Abrir préstamo</Link
            >
          </li>
        </ul></CardContent
      ></Card
    >
  </main>
</template>
