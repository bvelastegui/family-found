<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as transactionsIndex } from '@/routes/fund/transactions';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { store } from '@/routes/fund/transactions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fundDate, operationKey, usd } from '@/lib/fund';

type Period = {
  id: number;
  month: string;
  amount_cents: number;
  paid: boolean;
};
type Installment = {
  id: number;
  number: number;
  amount_cents: number;
  paid: boolean;
};
const props = defineProps<{
  banks: { id: number; name: string }[];
  periods: Period[];
  loans: { id: number; installments: Installment[] }[];
  hasPendingContribution: boolean;
}>();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Transacciones', href: transactionsIndex() },
    ],
  },
});

const step = ref(1);
const evidence = ref<File | null>(null);
const form = useForm({
  idempotency_key: operationKey(),
  bank_id: '',
  reference: '',
  transaction_date: '',
  amount: '',
  period_ids: [] as number[],
  installment_ids: [] as number[],
  evidence: null as File | null,
});
const months = computed(() => {
  const available: Period[] = [];
  let expectedMonth: number | null = null;

  for (const period of props.periods) {
    const [year, month] = period.month.split('-').map(Number);
    const currentMonth = year * 12 + month;
    if (expectedMonth !== null && currentMonth !== expectedMonth) break;
    expectedMonth = currentMonth + 1;
    if (period.paid) {
      if (available.length > 0) break;
      continue;
    }
    available.push(period);
  }

  return available;
});
const installmentGroups = computed(() =>
  props.loans.map((loan) => ({
    id: loan.id,
    installments: loan.installments.filter((item) => !item.paid),
  })),
);
const allocated = computed(
  () =>
    props.periods
      .filter((period) => form.period_ids.includes(period.id))
      .reduce((total, period) => total + period.amount_cents, 0) +
    props.loans
      .flatMap((loan) => loan.installments)
      .filter((item) => form.installment_ids.includes(item.id))
      .reduce((total, item) => total + item.amount_cents, 0),
);
const entered = computed(() => {
  if (!/^\d+(\.\d{1,2})?$/.test(form.amount)) return 0;
  const [dollars, cents = ''] = form.amount.split('.');
  return Number(dollars) * 100 + Number(cents.padEnd(2, '0'));
});
const nextMonth = computed(
  () => months.value.find((month) => !form.period_ids.includes(month.id))?.id,
);

function selectMonth(id: number): void {
  const index = form.period_ids.indexOf(id);
  if (index >= 0) {
    form.period_ids = form.period_ids.slice(0, index);
    return;
  }
  const period = months.value.find((item) => item.id === id);
  if (
    nextMonth.value === id &&
    !props.hasPendingContribution &&
    period &&
    allocated.value + period.amount_cents <= entered.value
  )
    form.period_ids.push(id);
}
function selectInstallment(loanId: number, id: number): void {
  const items =
    installmentGroups.value.find((loan) => loan.id === loanId)?.installments ??
    [];
  const index = items.findIndex((item) => item.id === id);
  if (form.installment_ids.includes(id)) {
    form.installment_ids = form.installment_ids.filter(
      (value) => !items.slice(index).some((item) => item.id === value),
    );
    return;
  }
  if (
    (index === 0 || form.installment_ids.includes(items[index - 1].id)) &&
    allocated.value + items[index].amount_cents <= entered.value
  )
    form.installment_ids.push(id);
}
function submit(): void {
  if (
    step.value !== 3 ||
    allocated.value !== entered.value ||
    allocated.value <= 0
  )
    return;
  form.evidence = evidence.value;
  form.post(store().url, { forceFormData: true });
}

function next(): void {
  form.clearErrors();
  if (step.value === 1) {
    if (
      !form.bank_id ||
      !form.reference.trim() ||
      !form.transaction_date ||
      entered.value <= 0 ||
      !evidence.value
    ) {
      form.setError(
        'amount',
        'Completa banco, número, fecha, monto y evidencia para continuar.',
      );
      return;
    }
  }
  if (step.value === 2 && allocated.value <= 0) {
    form.setError('amount', 'Selecciona al menos un mes o una cuota completa.');
    return;
  }
  step.value = Math.min(step.value + 1, 3);
}
</script>

<template>
  <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
    <Head title="Registrar transferencia" />
    <div>
      <h1 class="text-3xl font-semibold tracking-tight">
        Registrar transferencia
      </h1>
      <p class="text-muted-foreground">
        Se registrará como pendiente. El tesorero comprobará el depósito antes
        de sumarlo al fondo.
      </p>
    </div>
    <nav aria-label="Pasos del registro">
      <ol class="grid grid-cols-3 gap-2 text-center text-xs sm:text-sm">
        <li
          v-for="(title, number) in [
            'Comprobante',
            'Asignación',
            'Confirmación',
          ]"
          :key="title"
          class="rounded-md border px-2 py-3"
          :class="
            step === number + 1
              ? 'bg-primary text-primary-foreground'
              : 'bg-card text-muted-foreground'
          "
          :aria-current="step === number + 1 ? 'step' : undefined"
        >
          {{ number + 1 }}. {{ title }}
        </li>
      </ol>
    </nav>
    <form
      class="flex flex-col gap-6"
      @submit.prevent="submit"
    >
      <div
        v-if="step === 1"
        class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2"
      >
        <div class="grid gap-2">
          <Label for="bank">Banco de origen</Label
          ><select
            id="bank"
            v-model="form.bank_id"
            required
            class="h-9 rounded-md border bg-background px-3"
          >
            <option value="">Selecciona banco</option>
            <option
              v-for="bank in banks"
              :key="bank.id"
              :value="bank.id"
            >
              {{ bank.name }}
            </option>
          </select>
          <p class="text-sm text-destructive">
            {{ form.errors.bank_id }}
          </p>
        </div>
        <div class="grid gap-2">
          <Label for="reference">Número de comprobante</Label
          ><Input
            id="reference"
            v-model="form.reference"
            required
          />
          <p class="text-sm text-destructive">
            {{ form.errors.reference }}
          </p>
        </div>
        <div class="grid gap-2">
          <Label for="date">Fecha de transferencia</Label
          ><Input
            id="date"
            v-model="form.transaction_date"
            type="date"
            required
          />
          <p class="text-sm text-destructive">
            {{ form.errors.transaction_date }}
          </p>
        </div>
        <div class="grid gap-2">
          <Label for="amount">Monto en USD</Label
          ><Input
            id="amount"
            v-model="form.amount"
            type="text"
            inputmode="decimal"
            required
            placeholder="25.00"
          />
          <p class="text-sm text-destructive">
            {{ form.errors.amount }}
          </p>
        </div>
        <div class="grid gap-2 sm:col-span-2">
          <Label for="receipt">Comprobante (JPG, PNG o PDF, máximo 10 MB)</Label
          ><Input
            id="receipt"
            type="file"
            accept="image/jpeg,image/png,application/pdf"
            required
            @change="
              evidence = ($event.target as HTMLInputElement).files?.[0] ?? null
            "
          />
          <p class="text-sm text-destructive">
            {{ form.errors.evidence }}
          </p>
        </div>
      </div>
      <section
        v-if="step === 2"
        class="flex flex-col gap-3"
      >
        <h2 class="text-lg font-medium">Meses que vas a aportar</h2>
        <p
          v-if="hasPendingContribution"
          class="text-sm text-muted-foreground"
        >
          Tienes un aporte en revisión. Podrás registrar otro cuando se apruebe
          o rechace.
        </p>
        <p
          v-else-if="months.length === 0"
          class="text-sm text-muted-foreground"
        >
          No hay meses disponibles. El tesorero debe configurar las cuotas.
        </p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="month in months"
            :key="month.id"
            type="button"
            class="rounded-lg border px-3 py-2 text-sm disabled:opacity-50"
            :class="
              form.period_ids.includes(month.id)
                ? 'bg-primary text-primary-foreground'
                : 'bg-card'
            "
            :disabled="
              hasPendingContribution ||
              (!form.period_ids.includes(month.id) &&
                (nextMonth !== month.id ||
                  allocated + month.amount_cents > entered))
            "
            @click="selectMonth(month.id)"
          >
            {{ month.month }} · {{ usd(month.amount_cents) }}
          </button>
        </div>
        <p class="text-sm text-destructive">
          {{ form.errors.period_ids }}
        </p>
      </section>
      <section
        v-if="step === 2 && installmentGroups.length"
        class="flex flex-col gap-3"
      >
        <h2 class="text-lg font-medium">Cuotas de préstamos</h2>
        <div
          v-for="loan in installmentGroups"
          :key="loan.id"
          class="rounded-xl border p-4"
        >
          <h3 class="font-medium">Préstamo #{{ loan.id }}</h3>
          <div class="flex flex-wrap gap-2 pt-3">
            <button
              v-for="(item, i) in loan.installments"
              :key="item.id"
              type="button"
              class="rounded-lg border px-3 py-2 text-sm disabled:opacity-50"
              :class="
                form.installment_ids.includes(item.id)
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-card'
              "
              :disabled="
                !form.installment_ids.includes(item.id) &&
                ((i > 0 &&
                  !form.installment_ids.includes(
                    loan.installments[i - 1].id,
                  )) ||
                  allocated + item.amount_cents > entered)
              "
              @click="selectInstallment(loan.id, item.id)"
            >
              Cuota {{ item.number }} ·
              {{ usd(item.amount_cents) }}
            </button>
          </div>
        </div>
        <p class="text-sm text-destructive">
          {{ form.errors.installment_ids }}
        </p>
      </section>
      <div
        v-if="step >= 2"
        class="rounded-xl border bg-muted/30 p-4 text-sm"
        aria-live="polite"
      >
        <p>
          Asignado: <strong>{{ usd(allocated) }}</strong>
        </p>
        <p>
          Transferencia: <strong>{{ usd(entered) }}</strong>
        </p>
        <p
          v-if="allocated !== entered"
          class="text-destructive"
        >
          La transferencia debe coincidir exactamente con las cuotas
          seleccionadas.
        </p>
      </div>
      <Card v-if="step === 3"
        ><CardHeader
          ><CardTitle>Revisa antes de enviar</CardTitle
          ><CardDescription
            >La transferencia quedará en revisión. No se incluirá en el ledger
            hasta la aprobación del tesorero.</CardDescription
          ></CardHeader
        ><CardContent class="flex flex-col gap-3 text-sm"
          ><p>
            Banco:
            <strong>{{
              banks.find((bank) => bank.id === Number(form.bank_id))?.name
            }}</strong>
          </p>
          <p>
            Comprobante: <strong>{{ form.reference }}</strong>
          </p>
          <p>
            Fecha:
            <strong>{{ fundDate(form.transaction_date) }}</strong>
          </p>
          <p>
            Archivo: <strong>{{ evidence?.name }}</strong>
          </p>
          <p>
            Meses: <strong>{{ form.period_ids.length }}</strong> · Cuotas de
            préstamo:
            <strong>{{ form.installment_ids.length }}</strong>
          </p>
          <p
            v-if="form.hasErrors"
            role="alert"
            class="text-destructive"
          >
            Corrige los datos indicados antes de reintentar.
          </p>
          <p
            v-for="(message, field) in form.errors"
            :key="field"
            class="text-destructive"
          >
            {{ field }}: {{ message }}
          </p></CardContent
        ></Card
      >
      <div class="flex flex-wrap items-center gap-3">
        <Button
          v-if="step > 1"
          type="button"
          variant="outline"
          @click="step--"
          >Anterior</Button
        >
        <Button
          v-if="step < 3"
          type="button"
          @click="next"
          >Continuar</Button
        >
        <Button
          v-if="step === 3"
          :disabled="
            form.processing ||
            allocated === 0 ||
            allocated !== entered ||
            !evidence
          "
          >Enviar a revisión</Button
        ><Link
          :href="transactionsIndex()"
          class="self-center underline"
          >Cancelar</Link
        >
      </div>
    </form>
  </main>
</template>
