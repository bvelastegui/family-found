<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { dashboard } from '@/routes';
import {
  create as newTransaction,
  show as transactionShow,
} from '@/routes/fund/transactions';
import { index as contributionsIndex } from '@/routes/fund/contributions';
import { index as loansIndex, show as loanShow } from '@/routes/fund/loans';
import { index as reconciliationIndex } from '@/routes/fund/treasury/reconciliation';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import FundStatCard from '@/components/FundStatCard.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
import { fundDate, fundMonth, usd } from '@/lib/fund';

defineOptions({
  layout: { breadcrumbs: [{ title: 'Inicio', href: dashboard() }] },
});
defineProps<{
  isTreasurer: boolean;
  isAdministrator: boolean;
  contributedCents: number;
  nextContribution: { month: string; amount_cents: number } | null;
  pendingTransactions: {
    id: number;
    amount_cents: number;
    created_at: string;
  }[];
  upcomingInstallments: {
    id: number;
    loan_id: number;
    due_on: string;
    amount_cents: number;
  }[];
  hasLoans: boolean;
  pendingReviewCount: number;
  balances: {
    cash: number;
    available: number;
    interest: number;
    reserved: number;
    principal: number;
  } | null;
}>();
</script>

<template>
  <main
    class="mx-auto flex w-full max-w-6xl flex-col gap-4 p-3 pb-24 sm:gap-6 sm:p-4 sm:pb-24 md:gap-8 md:p-8"
  >
    <Head title="Inicio" />
    <AppPageHeader title="Tu inicio">
      <template #actions>
        <Button
          class="hidden sm:inline-flex"
          as-child
        >
          <Link :href="newTransaction()">Registrar transferencia</Link>
        </Button>
      </template>
    </AppPageHeader>
    <div
      class="-mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-2 md:mx-0 md:grid md:snap-none md:grid-cols-2 md:gap-4 md:overflow-visible md:px-0 md:pb-0"
      :class="{ 'lg:grid-cols-3': isTreasurer }"
    >
      <FundStatCard
        class="min-w-[84vw] snap-start md:min-w-0"
        label="Próximo aporte"
        :value="
          nextContribution ? usd(nextContribution.amount_cents) : 'Sin cuota'
        "
        :badge="nextContribution ? 'Por pagar' : 'Sin pendientes'"
        :headline="
          nextContribution
            ? fundMonth(nextContribution.month)
            : 'No hay cuotas pendientes configuradas'
        "
        detail="El mes cuenta como pagado cuando se aprueba el comprobante."
      >
        <template #footer
          ><Link
            class="text-sm font-medium underline underline-offset-4"
            :href="contributionsIndex()"
            >Ver calendario</Link
          ></template
        >
      </FundStatCard>
      <FundStatCard
        class="min-w-[84vw] snap-start md:min-w-0"
        label="Mis aportes"
        :value="usd(contributedCents)"
        badge="Aprobados"
        headline="Tu participación en el fondo"
        detail="No incluye transferencias en revisión."
      />
      <FundStatCard
        v-if="isTreasurer && balances"
        class="min-w-[84vw] snap-start md:min-w-0"
        label="Por conciliar"
        :value="String(pendingReviewCount)"
        badge="Pendientes"
        headline="Comprobantes que requieren revisión"
        detail="Las transferencias pendientes no alteran el efectivo."
      >
        <template #footer
          ><Link
            class="text-sm font-medium underline underline-offset-4"
            :href="reconciliationIndex()"
            >Abrir conciliación</Link
          ></template
        >
      </FundStatCard>
    </div>
    <div
      class="grid gap-3 sm:gap-4"
      :class="{ 'lg:grid-cols-2': upcomingInstallments.length > 0 }"
    >
      <Card
        ><CardHeader class="px-4 pt-4 pb-2 sm:p-6"
          ><CardTitle>Movimientos pendientes de aprobación</CardTitle
          ><CardDescription
            >Los movimientos se contabilizan cuando el tesorero los
            aprueba</CardDescription
          ></CardHeader
        ><CardContent class="px-4 pt-0 pb-4 sm:p-6"
          ><p
            v-if="!pendingTransactions.length"
            class="text-sm text-muted-foreground"
          >
            No tienes comprobantes esperando revisión.
          </p>
          <ul
            v-else
            class="divide-y"
          >
            <li
              v-for="item in pendingTransactions"
              :key="item.id"
              class="flex items-center justify-between gap-3 py-3 text-sm"
            >
              <span
                >Comprobante #{{ item.id }} · {{ usd(item.amount_cents) }}</span
              ><Link
                class="font-medium underline underline-offset-4"
                :href="transactionShow(item.id)"
                >Revisar estado</Link
              >
            </li>
          </ul></CardContent
        ></Card
      ><Card v-if="upcomingInstallments.length > 0"
        ><CardHeader class="px-4 pt-4 pb-2 sm:p-6"
          ><CardTitle>Próximas cuotas de préstamo</CardTitle
          ><CardDescription
            >Cuotas pendientes ordenadas por vencimiento</CardDescription
          ></CardHeader
        ><CardContent class="px-4 pt-0 pb-4 sm:p-6"
          ><ul class="divide-y">
            <li
              v-for="item in upcomingInstallments"
              :key="item.id"
              class="flex items-center justify-between gap-3 py-3 text-sm"
            >
              <span
                >{{ fundDate(item.due_on) }} ·
                {{ usd(item.amount_cents) }}</span
              ><Link
                class="font-medium underline underline-offset-4"
                :href="loanShow(item.loan_id)"
                >Préstamo #{{ item.loan_id }}</Link
              >
            </li>
          </ul>
          <Link
            class="mt-3 inline-block text-sm font-medium underline underline-offset-4"
            :href="loansIndex()"
            >Ver mis préstamos</Link
          ></CardContent
        ></Card
      >
    </div>
    <p
      v-if="hasLoans && upcomingInstallments.length === 0"
      class="text-sm text-muted-foreground"
    >
      No tienes cuotas pendientes.
      <Link
        class="font-medium underline underline-offset-4"
        :href="loansIndex()"
        >Consultar mis préstamos</Link
      >
    </p>
    <Button
      class="fixed right-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 size-14 rounded-full shadow-lg sm:hidden"
      size="icon"
      as-child
    >
      <Link
        :href="newTransaction()"
        aria-label="Registrar transferencia"
      >
        <Plus class="size-6" />
      </Link>
    </Button>
  </main>
</template>
