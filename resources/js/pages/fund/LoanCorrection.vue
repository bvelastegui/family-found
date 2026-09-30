<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import {
  index as loansIndex,
  show as showLoan,
  correct as correctLoan,
} from '@/routes/fund/loans';
import { show as evidenceShow } from '@/routes/fund/evidences';
import AutoResizeTextarea from '@/components/AutoResizeTextarea.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fundDate, operationKey, usd } from '@/lib/fund';

type Loan = {
  id: number;
  user: { name: string };
  principal_cents: number;
  monthly_rate: string;
  term_months: number;
  bank_id: number | null;
  bank_name: string | null;
  reference: string | null;
  disbursed_on: string | null;
  evidence_id: number | null;
};
const props = defineProps<{
  loan: Loan;
  banks: { id: number; name: string }[];
  hasDependentPayments: boolean;
}>();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Tesorería', href: treasuryIndex() },
      { title: 'Préstamos', href: loansIndex() },
    ],
  },
});

const step = ref(1);
const stepError = ref('');
const form = useForm({
  idempotency_key: operationKey(),
  reason: '',
  amount: (props.loan.principal_cents / 100).toFixed(2),
  monthly_rate: props.loan.monthly_rate,
  term_months: props.loan.term_months,
  bank_id: props.banks.find((bank) => bank.id === props.loan.bank_id)?.id ?? '',
  reference: props.loan.reference ?? '',
  transaction_date: props.loan.disbursed_on?.slice(0, 10) ?? '',
  evidence: null as File | null,
});
const amountCents = computed(() => {
  if (!/^\d+(?:\.\d{1,2})?$/.test(form.amount)) return 0;
  const [whole, cents = ''] = form.amount.split('.');
  return Number(whole) * 100 + Number(cents.padEnd(2, '0'));
});
const bankName = computed(
  () =>
    props.banks.find((bank) => bank.id === Number(form.bank_id))?.name ??
    'Selecciona un banco',
);
const errorLabels: Record<string, string> = {
  loan: 'Condiciones del préstamo',
  correction: 'Corrección',
  reason: 'Motivo',
  bank_id: 'Banco',
  reference: 'Comprobante',
  transaction_date: 'Fecha',
  amount: 'Monto',
  monthly_rate: 'Tasa mensual',
  term_months: 'Plazo',
  evidence: 'Nueva evidencia',
};

function next(): void {
  form.clearErrors();
  stepError.value = '';
  if (step.value === 1 && !form.reason.trim()) {
    form.setError('reason', 'Explica el motivo antes de continuar.');
    return;
  }
  if (
    step.value === 2 &&
    (!form.bank_id ||
      !form.reference.trim() ||
      !form.transaction_date ||
      amountCents.value <= 0 ||
      form.monthly_rate === '' ||
      Number(form.term_months) < 1 ||
      !form.evidence)
  ) {
    stepError.value =
      'Completa los datos corregidos y adjunta una nueva evidencia.';
    return;
  }
  step.value = Math.min(step.value + 1, 3);
}

function submit(): void {
  if (step.value !== 3 || props.hasDependentPayments || !form.evidence) return;
  form.post(correctLoan(props.loan.id).url, {
    forceFormData: true,
    onError: (errors) => {
      step.value = errors.reason ? 1 : 2;
    },
  });
}
</script>

<template>
  <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
    <Head :title="`Corregir desembolso #${loan.id}`" />
    <header>
      <p class="text-sm text-muted-foreground">Tesorería · Préstamos</p>
      <h1 class="text-3xl font-semibold tracking-tight">
        Corregir desembolso #{{ loan.id }}
      </h1>
      <p class="mt-1 text-muted-foreground">
        Corrige un error de registro conservando el préstamo original y su
        trazabilidad.
      </p>
    </header>

    <Card>
      <CardHeader
        ><CardTitle>Desembolso registrado</CardTitle
        ><CardDescription
          >Este es el registro que se conservará en el historial después de la
          corrección.</CardDescription
        ></CardHeader
      >
      <CardContent class="grid gap-3 text-sm sm:grid-cols-2">
        <p>
          <span class="text-muted-foreground">Prestatario:</span>
          {{ loan.user.name }}
        </p>
        <p>
          <span class="text-muted-foreground">Capital:</span>
          {{ usd(loan.principal_cents) }}
        </p>
        <p>
          <span class="text-muted-foreground">Tasa:</span>
          {{ loan.monthly_rate }} % mensual
        </p>
        <p>
          <span class="text-muted-foreground">Plazo:</span>
          {{ loan.term_months }} meses
        </p>
        <p>
          <span class="text-muted-foreground">Banco:</span>
          {{ loan.bank_name ?? 'Sin banco' }}
        </p>
        <p>
          <span class="text-muted-foreground">Comprobante:</span>
          {{ loan.reference ?? 'Sin referencia' }}
        </p>
        <p>
          <span class="text-muted-foreground">Fecha:</span>
          {{ loan.disbursed_on ? fundDate(loan.disbursed_on) : 'Sin fecha' }}
        </p>
        <a
          v-if="loan.evidence_id"
          class="underline underline-offset-4"
          :href="evidenceShow(loan.evidence_id).url"
          >Ver evidencia original</a
        >
      </CardContent>
    </Card>

    <Alert v-if="hasDependentPayments">
      <AlertTitle>No se puede corregir este desembolso</AlertTitle>
      <AlertDescription
        >Ya existen pagos pendientes o aprobados sobre su tabla de amortización.
        Estos pagos deben resolverse antes de poder reemplazar las condiciones
        del préstamo.
        <Link
          class="font-medium underline underline-offset-4"
          :href="showLoan(loan.id)"
          >Volver al préstamo</Link
        >.</AlertDescription
      >
    </Alert>

    <template v-else>
      <nav aria-label="Pasos de la corrección">
        <ol class="grid grid-cols-3 gap-2 text-center text-xs sm:text-sm">
          <li
            v-for="(label, index) in [
              'Motivo',
              'Datos correctos',
              'Confirmación',
            ]"
            :key="label"
            class="rounded-md border px-2 py-3"
            :class="
              step === index + 1
                ? 'bg-primary text-primary-foreground'
                : 'bg-card text-muted-foreground'
            "
            :aria-current="step === index + 1 ? 'step' : undefined"
          >
            {{ index + 1 }}. {{ label }}
          </li>
        </ol>
      </nav>

      <form
        class="flex flex-col gap-5"
        @submit.prevent="submit"
      >
        <Card v-if="step === 1">
          <CardHeader
            ><CardTitle>¿Qué dato fue registrado incorrectamente?</CardTitle
            ><CardDescription
              >Explica el error. Tu explicación formará parte del historial de
              correcciones.</CardDescription
            ></CardHeader
          >
          <CardContent class="flex flex-col gap-2">
            <Label for="disbursement-reason">Motivo de la corrección</Label>
            <AutoResizeTextarea
              id="disbursement-reason"
              v-model="form.reason"
              required
              maxlength="5000"
              :aria-invalid="!!form.errors.reason"
            />
            <p
              v-if="form.errors.reason"
              role="alert"
              class="text-sm text-destructive"
            >
              {{ form.errors.reason }}
            </p>
          </CardContent>
        </Card>

        <Card v-if="step === 2">
          <CardHeader
            ><CardTitle>Datos correctos del desembolso</CardTitle
            ><CardDescription
              >Los valores originales están precargados. Adjunta el comprobante
              que respalda el reemplazo.</CardDescription
            ></CardHeader
          >
          <CardContent class="grid gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
              <Label for="correct-bank">Banco de destino</Label
              ><select
                id="correct-bank"
                v-model="form.bank_id"
                required
                class="h-9 rounded-md border bg-background px-3"
                :aria-invalid="!!form.errors.bank_id"
              >
                <option value="">Selecciona un banco</option>
                <option
                  v-for="bank in banks"
                  :key="bank.id"
                  :value="bank.id"
                >
                  {{ bank.name }}
                </option>
              </select>
            </div>
            <div class="flex flex-col gap-2">
              <Label for="correct-reference">Número de comprobante</Label
              ><Input
                id="correct-reference"
                v-model="form.reference"
                required
                :aria-invalid="!!form.errors.reference"
              />
            </div>
            <div class="flex flex-col gap-2">
              <Label for="correct-date">Fecha del desembolso</Label
              ><Input
                id="correct-date"
                v-model="form.transaction_date"
                type="date"
                required
                :aria-invalid="!!form.errors.transaction_date"
              />
            </div>
            <div class="flex flex-col gap-2">
              <Label for="correct-amount">Capital transferido en USD</Label
              ><Input
                id="correct-amount"
                v-model="form.amount"
                inputmode="decimal"
                required
                :aria-invalid="!!form.errors.amount"
              />
            </div>
            <div class="flex flex-col gap-2">
              <Label for="correct-rate">Tasa mensual (%)</Label
              ><Input
                id="correct-rate"
                v-model="form.monthly_rate"
                inputmode="decimal"
                required
                :aria-invalid="!!form.errors.monthly_rate"
              />
            </div>
            <div class="flex flex-col gap-2">
              <Label for="correct-term">Plazo en meses</Label
              ><Input
                id="correct-term"
                v-model="form.term_months"
                type="number"
                min="1"
                required
                :aria-invalid="!!form.errors.term_months"
              />
            </div>
            <div class="flex flex-col gap-2 sm:col-span-2">
              <Label for="correct-evidence"
                >Nueva evidencia (JPG, PNG o PDF, máximo 10 MB)</Label
              ><Input
                id="correct-evidence"
                type="file"
                accept="image/jpeg,image/png,application/pdf"
                required
                :aria-invalid="!!form.errors.evidence"
                @change="
                  form.evidence =
                    ($event.target as HTMLInputElement).files?.[0] ?? null
                "
              />
            </div>
            <p
              v-if="stepError"
              role="alert"
              class="text-sm text-destructive sm:col-span-2"
            >
              {{ stepError }}
            </p>
            <p
              v-for="(message, field) in form.errors"
              :key="field"
              role="alert"
              class="text-sm text-destructive sm:col-span-2"
            >
              {{ errorLabels[field] ?? field }}: {{ message }}
            </p>
          </CardContent>
        </Card>

        <Card v-if="step === 3">
          <CardHeader
            ><CardTitle>Confirma el reemplazo</CardTitle
            ><CardDescription
              >No se realiza una nueva transferencia bancaria. Se compensará el
              asiento original y se registrará el nuevo préstamo con su tabla de
              amortización en una sola operación.</CardDescription
            ></CardHeader
          >
          <CardContent class="flex flex-col gap-3 text-sm">
            <p>
              <span class="text-muted-foreground">Prestatario:</span>
              {{ loan.user.name }}
            </p>
            <p>
              <span class="text-muted-foreground">Capital anterior:</span>
              {{ usd(loan.principal_cents) }} →
              <strong>{{ usd(amountCents) }}</strong>
            </p>
            <p>
              <span class="text-muted-foreground">Tasa anterior:</span>
              {{ loan.monthly_rate }} % →
              <strong>{{ form.monthly_rate }} % mensual</strong>
            </p>
            <p>
              <span class="text-muted-foreground">Plazo anterior:</span>
              {{ loan.term_months }} →
              <strong>{{ form.term_months }} meses</strong>
            </p>
            <p>
              <span class="text-muted-foreground">Banco y referencia:</span>
              {{ bankName }} · {{ form.reference }}
            </p>
            <p>
              <span class="text-muted-foreground">Fecha:</span>
              {{ fundDate(form.transaction_date) }} ·
              <span class="text-muted-foreground">Evidencia:</span>
              {{ form.evidence?.name }}
            </p>
            <p class="break-words whitespace-pre-wrap">
              <span class="text-muted-foreground">Motivo:</span>
              {{ form.reason }}
            </p>
          </CardContent>
        </Card>

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
            :disabled="form.processing || !form.evidence"
            >Confirmar corrección</Button
          >
          <Link
            :href="showLoan(loan.id)"
            class="text-sm underline underline-offset-4"
            >Cancelar</Link
          >
        </div>
      </form>
    </template>
  </main>
</template>
