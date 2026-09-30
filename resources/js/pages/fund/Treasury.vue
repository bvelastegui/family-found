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
import { fundDate, usd, type FundPagination as Pagination } from '@/lib/fund';

type Pending = {
  id: number;
  amount_cents: number;
  transaction_date: string;
  bank_name: string;
  reference: string;
  user: { name: string };
};
type Reserved = { id: number; principal_cents: number; user: { name: string } };
const props = defineProps<{
  pending: Pagination<Pending>;
  reservedLoans: Reserved[];
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
    <header class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-sm text-muted-foreground">Operaciones del fondo</p>
        <h1 class="text-3xl font-semibold tracking-tight">Tesorería</h1>
        <p class="mt-1 text-muted-foreground">
          Revisa los comprobantes antes de que afecten los saldos.
        </p>
      </div>
      <Button as-child
        ><Link :href="createLoan()">Reservar préstamo</Link></Button
      >
    </header>
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
    <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
      <FundStatCard
        v-for="stat in stats"
        :key="stat.label"
        v-bind="stat"
      />
    </div>
    <Card
      ><CardHeader
        ><CardTitle>Préstamos reservados</CardTitle
        ><CardDescription
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
