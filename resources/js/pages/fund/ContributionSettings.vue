<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import {
  index as periodsIndex,
  store as savePeriod,
  range as saveRange,
} from '@/routes/fund/contribution-periods';
import FundPagination from '@/components/FundPagination.vue';
import FundStatus from '@/components/FundStatus.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
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
  editable: boolean;
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

const rangeForm = useForm({
  idempotency_key: operationKey(),
  first_month: '',
  last_month: '',
  amount: '',
});
const selectedPeriod = ref<Period | null>(null);
const editForm = useForm({
  idempotency_key: operationKey(),
  month: '',
  amount: '',
});

function edit(period: Period): void {
  if (!period.editable) return;
  selectedPeriod.value = period;
  editForm.idempotency_key = operationKey();
  editForm.month = period.month;
  editForm.amount = (period.amount_cents / 100).toFixed(2);
  editForm.clearErrors();
}

function cancelEdit(): void {
  selectedPeriod.value = null;
  editForm.reset('month', 'amount');
  editForm.clearErrors();
}
</script>

<template>
  <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
    <Head title="Cuotas del fondo" />
    <AppPageHeader title="Cuotas mensuales" />

    <Card>
      <CardHeader
        ><CardTitle>Configurar un rango de meses</CardTitle
        ><CardDescription
          >El mismo importe se aplica a todos los meses entre el inicio y el
          fin, inclusive. Si un mes ya fue utilizado o no admite cambios, el
          sistema no guardará ninguno.</CardDescription
        ></CardHeader
      >
      <CardContent>
        <form
          class="grid gap-4 sm:grid-cols-2"
          @submit.prevent="
            rangeForm.post(saveRange().url, {
              onSuccess: () => {
                rangeForm.reset('first_month', 'last_month', 'amount');
                rangeForm.idempotency_key = operationKey();
              },
            })
          "
        >
          <div class="flex flex-col gap-2">
            <Label for="range-start">Desde el mes</Label
            ><Input
              id="range-start"
              v-model="rangeForm.first_month"
              type="month"
              required
              :aria-invalid="!!rangeForm.errors.first_month"
            />
            <p
              v-if="rangeForm.errors.first_month"
              role="alert"
              class="text-sm text-destructive"
            >
              {{ rangeForm.errors.first_month }}
            </p>
          </div>
          <div class="flex flex-col gap-2">
            <Label for="range-end">Hasta el mes</Label
            ><Input
              id="range-end"
              v-model="rangeForm.last_month"
              type="month"
              required
              :aria-invalid="!!rangeForm.errors.last_month"
            />
            <p
              v-if="rangeForm.errors.last_month"
              role="alert"
              class="text-sm text-destructive"
            >
              {{ rangeForm.errors.last_month }}
            </p>
          </div>
          <div class="flex flex-col gap-2 sm:col-span-2">
            <Label for="range-amount">Aporte mensual en USD</Label
            ><Input
              id="range-amount"
              v-model="rangeForm.amount"
              inputmode="decimal"
              required
              placeholder="25.00"
              :aria-invalid="!!rangeForm.errors.amount"
            />
            <p
              v-if="rangeForm.errors.amount"
              role="alert"
              class="text-sm text-destructive"
            >
              {{ rangeForm.errors.amount }}
            </p>
          </div>
          <p class="text-xs text-muted-foreground sm:col-span-2">
            Puedes configurar hasta 120 meses consecutivos en un rango.
          </p>
          <Button :disabled="rangeForm.processing">Guardar rango</Button>
        </form>
      </CardContent>
    </Card>

    <Card v-if="selectedPeriod">
      <CardHeader
        ><CardTitle>Editar {{ fundMonth(selectedPeriod.month) }}</CardTitle
        ><CardDescription
          >Solo pueden cambiarse cuotas futuras que no aparezcan en una
          transacción registrada.</CardDescription
        ></CardHeader
      >
      <CardContent
        ><form
          class="flex flex-wrap items-end gap-3"
          @submit.prevent="
            editForm.post(savePeriod().url, { onSuccess: () => cancelEdit() })
          "
        >
          <div class="flex min-w-44 flex-1 flex-col gap-2">
            <Label for="edit-amount">Nuevo importe mensual en USD</Label
            ><Input
              id="edit-amount"
              v-model="editForm.amount"
              inputmode="decimal"
              required
              :aria-invalid="
                !!editForm.errors.amount || !!editForm.errors.month
              "
            />
            <p
              v-if="editForm.errors.amount || editForm.errors.month"
              role="alert"
              class="text-sm text-destructive"
            >
              {{ editForm.errors.amount ?? editForm.errors.month }}
            </p>
          </div>
          <Button :disabled="editForm.processing">Guardar cambio</Button
          ><Button
            type="button"
            variant="outline"
            @click="cancelEdit"
            >Cancelar</Button
          >
        </form></CardContent
      >
    </Card>

    <section
      class="flex flex-col gap-4"
      aria-label="Períodos configurados"
    >
      <h2 class="text-xl font-semibold">Períodos configurados</h2>
      <p
        v-if="!periods.data.length"
        class="rounded-lg border p-6 text-muted-foreground"
      >
        Aún no hay cuotas. Configura el primer rango para que los usuarios
        puedan aportar.
      </p>
      <div
        v-else
        class="overflow-x-auto rounded-lg border"
      >
        <table class="w-full min-w-[540px] text-left text-sm">
          <caption class="sr-only">
            Aportes mensuales definidos por Tesorería
          </caption>
          <thead class="bg-muted/50 text-muted-foreground">
            <tr>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Mes
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Aporte
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
                Acción
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="period in periods.data"
              :key="period.id"
              class="border-t"
            >
              <th
                scope="row"
                class="px-4 py-3 font-medium capitalize"
              >
                {{ fundMonth(period.month) }}
              </th>
              <td class="px-4 py-3 text-right tabular-nums">
                {{ usd(period.amount_cents) }}
              </td>
              <td class="px-4 py-3">
                <FundStatus
                  :status="
                    period.locked_at
                      ? 'locked'
                      : period.editable
                        ? 'upcoming'
                        : 'unavailable'
                  "
                />
              </td>
              <td class="px-4 py-3 text-right">
                <Button
                  v-if="period.editable"
                  size="sm"
                  variant="outline"
                  @click="edit(period)"
                  >Editar cuota</Button
                ><span
                  v-else
                  class="text-muted-foreground"
                  >—</span
                >
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <FundPagination
      :links="periods.links"
      :last-page="periods.last_page"
      label="Páginas de cuotas configuradas"
    />
  </main>
</template>
