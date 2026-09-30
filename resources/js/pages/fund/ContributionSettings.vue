<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import {
  index as periodsIndex,
  store as savePeriod,
} from '@/routes/fund/contribution-periods';
import FundPagination from '@/components/FundPagination.vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  fundMonth,
  usd,
  operationKey,
  type FundPagination as Pagination,
} from '@/lib/fund';

type Period = {
  id: number;
  month: string;
  amount_cents: number;
  locked_at: string | null;
};
defineProps<{ periods: Pagination<Period> }>();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Tesorería', href: treasuryIndex() },
      { title: 'Cuotas', href: periodsIndex() },
    ],
  },
});
const form = useForm({
  idempotency_key: operationKey(),
  month: '',
  amount: '',
});
</script>

<template>
  <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
    <Head title="Cuotas del fondo" />
    <header>
      <p class="text-sm text-muted-foreground">Tesorería</p>
      <h1 class="text-3xl font-semibold tracking-tight">Cuotas mensuales</h1>
      <p class="mt-1 text-muted-foreground">
        El período más antiguo que configures será el primer mes exigible a
        todos los participantes.
      </p>
    </header>
    <Card
      ><CardHeader
        ><CardTitle>Definir cuota</CardTitle
        ><CardDescription
          >La cuota es igual para todos. Un mes incluido en una transferencia no
          puede cambiarse.</CardDescription
        ></CardHeader
      ><CardContent
        ><form
          class="grid gap-4 sm:grid-cols-2"
          @submit.prevent="
            form.post(savePeriod().url, {
              onSuccess: () => {
                form.reset('month', 'amount');
                form.idempotency_key = operationKey();
              },
            })
          "
        >
          <div class="flex flex-col gap-2">
            <Label for="period-month">Mes (AAAA-MM)</Label
            ><Input
              id="period-month"
              v-model="form.month"
              required
              placeholder="2026-10"
              :aria-invalid="!!form.errors.month"
            />
            <p
              v-if="form.errors.month"
              class="text-sm text-destructive"
            >
              {{ form.errors.month }}
            </p>
          </div>
          <div class="flex flex-col gap-2">
            <Label for="period-amount">Monto en USD</Label
            ><Input
              id="period-amount"
              v-model="form.amount"
              inputmode="decimal"
              required
              placeholder="25.00"
              :aria-invalid="!!form.errors.amount"
            />
            <p
              v-if="form.errors.amount"
              class="text-sm text-destructive"
            >
              {{ form.errors.amount }}
            </p>
          </div>
          <Button :disabled="form.processing">Guardar cuota</Button>
        </form></CardContent
      ></Card
    >
    <Card
      ><CardHeader><CardTitle>Períodos configurados</CardTitle></CardHeader
      ><CardContent
        ><p
          v-if="!periods.data.length"
          class="text-sm text-muted-foreground"
        >
          Aún no hay cuotas. Configura el mes inicial para que los usuarios
          puedan registrar aportes.
        </p>
        <ul
          v-else
          class="divide-y"
        >
          <li
            v-for="item in periods.data"
            :key="item.id"
            class="flex flex-wrap items-center justify-between gap-2 py-3"
          >
            <div>
              <p class="font-medium">
                {{ fundMonth(item.month) }}
              </p>
              <p class="text-xs text-muted-foreground">
                {{
                  item.locked_at
                    ? 'Cuota fijada por una transferencia'
                    : 'Todavía puede configurarse si es futura'
                }}
              </p>
            </div>
            <strong>{{ usd(item.amount_cents) }}</strong>
          </li>
        </ul></CardContent
      ></Card
    ><FundPagination
      :links="periods.links"
      :last-page="periods.last_page"
      label="Páginas de cuotas configuradas"
    /><Link
      :href="treasuryIndex()"
      class="text-sm underline underline-offset-4"
      >Volver a Tesorería</Link
    >
  </main>
</template>
