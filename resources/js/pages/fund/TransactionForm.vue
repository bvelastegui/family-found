<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Check, FileText, Upload, X } from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as transactionsIndex } from '@/routes/fund/transactions';
import { store } from '@/routes/fund/transactions';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fundDate, fundMonth, operationKey, usd } from '@/lib/fund';
import { cn } from '@/lib/utils';

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
  sharedEvidence?: { name: string; mime: string; contents: string } | null;
}>();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Mis transacciones', href: transactionsIndex() },
    ],
  },
});

const step = ref(1);
const evidence = ref<File | null>(null);
const receiptInput = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const sharedEvidenceApplied = ref(false);
const stepHeading = ref<HTMLElement | null>(null);
const steps = [
  {
    title: 'Comprobante',
    description: 'Adjunta el archivo y los datos de tu transferencia.',
  },
  {
    title: 'Asignación',
    description: 'Elige los aportes o cuotas que cubre este depósito.',
  },
  {
    title: 'Confirmación',
    description: 'Comprueba que todo esté correcto antes de enviarlo.',
  },
];
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
const selectedMonths = computed(() =>
  props.periods.filter((period) => form.period_ids.includes(period.id)),
);
const selectedInstallments = computed(() =>
  props.loans.flatMap((loan) =>
    loan.installments
      .filter((item) => form.installment_ids.includes(item.id))
      .map((item) => ({ ...item, loanId: loan.id })),
  ),
);
const remaining = computed(() => entered.value - allocated.value);
const evidenceSize = computed(() =>
  evidence.value
    ? evidence.value.size < 1024 * 1024
      ? `${Math.max(1, Math.round(evidence.value.size / 1024))} KB`
      : `${(evidence.value.size / 1024 / 1024).toFixed(1)} MB`
    : '',
);

watch(evidence, (file) => {
  if (preview.value) URL.revokeObjectURL(preview.value);
  preview.value = file?.type.startsWith('image/')
    ? URL.createObjectURL(file)
    : null;
});
onMounted(async () => {
  if (!props.sharedEvidence || sharedEvidenceApplied.value) return;

  try {
    const contents = props.sharedEvidence.contents;
    const binary = atob(contents);
    const bytes = Uint8Array.from(binary, (character) =>
      character.charCodeAt(0),
    );
    const file = new File([bytes], props.sharedEvidence.name, {
      type: props.sharedEvidence.mime,
    });
    const transfer = new DataTransfer();
    transfer.items.add(file);
    if (receiptInput.value) receiptInput.value.files = transfer.files;
    chooseEvidence({ target: { files: transfer.files } } as unknown as Event);
    sharedEvidenceApplied.value = true;
    await goToStep(1);
  } catch {
    form.setError(
      'evidence',
      'No se pudo cargar el archivo compartido. Adjunta el comprobante nuevamente.',
    );
  }
});
onUnmounted(() => {
  if (preview.value) URL.revokeObjectURL(preview.value);
});

function chooseEvidence(event: Event): void {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  form.clearErrors('evidence');
  if (
    !/\.(jpe?g|png|pdf)$/i.test(file.name) ||
    (file.type !== '' &&
      !['image/jpeg', 'image/png', 'application/pdf'].includes(file.type))
  ) {
    evidence.value = null;
    input.value = '';
    form.setError('evidence', 'Elige una imagen JPG, PNG o un archivo PDF.');
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    evidence.value = null;
    input.value = '';
    form.setError('evidence', 'El comprobante debe pesar como máximo 10 MB.');
    return;
  }
  evidence.value = file;
}

function removeEvidence(): void {
  evidence.value = null;
  form.clearErrors('evidence');
  if (receiptInput.value) receiptInput.value.value = '';
}

async function goToStep(value: number): Promise<void> {
  step.value = value;
  await nextTick();
  stepHeading.value?.focus({ preventScroll: true });
  stepHeading.value?.scrollIntoView({ block: 'start' });
}

function selectMonth(id: number): void {
  form.clearErrors('period_ids');
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
  form.clearErrors('installment_ids', 'period_ids');
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
    form.processing ||
    !evidence.value ||
    step.value !== 3 ||
    allocated.value !== entered.value ||
    allocated.value <= 0
  )
    return;
  form.evidence = evidence.value;
  form.post(store().url, {
    forceFormData: true,
    onError: (errors) => {
      const fields = [
        'bank_id',
        'reference',
        'transaction_date',
        'amount',
        'evidence',
      ];
      void goToStep(
        Object.keys(errors).some((field) => fields.includes(field)) ? 1 : 2,
      );
    },
  });
}

function next(): void {
  form.clearErrors();
  if (step.value === 1) {
    form.amount = form.amount.trim().replace(',', '.');
    if (!form.bank_id)
      form.setError('bank_id', 'Selecciona el banco de origen.');
    if (!form.reference.trim())
      form.setError('reference', 'Escribe el número de comprobante.');
    if (!form.transaction_date)
      form.setError('transaction_date', 'Indica la fecha de transferencia.');
    if (entered.value <= 0)
      form.setError('amount', 'Ingresa un monto válido, por ejemplo 25.00.');
    if (!evidence.value)
      form.setError('evidence', 'Adjunta una foto o PDF del comprobante.');
    if (form.hasErrors) {
      void nextTick(() =>
        document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(),
      );
      return;
    }
  }
  if (
    step.value === 2 &&
    (allocated.value <= 0 || allocated.value !== entered.value)
  ) {
    form.setError(
      'period_ids',
      'Selecciona aportes o cuotas que sumen exactamente el monto de la transferencia.',
    );
    return;
  }
  void goToStep(Math.min(step.value + 1, 3));
}
</script>

<template>
  <main
    class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 sm:gap-8 sm:p-6"
  >
    <Head title="Registrar transferencia" />
    <div>
      <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
        Registrar transferencia
      </h1>
      <p class="mt-2 text-sm text-muted-foreground">
        Registra tu depósito en tres pasos. Se acreditará cuando el tesorero lo
        apruebe.
      </p>
    </div>
    <nav aria-label="Pasos del registro">
      <ol class="flex items-start">
        <li
          v-for="(item, number) in steps"
          :key="item.title"
          class="relative flex flex-1 flex-col items-center gap-2 text-center"
          :aria-current="step === number + 1 ? 'step' : undefined"
        >
          <span
            v-if="number < steps.length - 1"
            :class="
              cn(
                'absolute top-4 left-1/2 h-px w-full',
                step > number + 1 ? 'bg-primary' : 'bg-border',
              )
            "
            aria-hidden="true"
          />
          <span
            :class="
              cn(
                'relative flex size-8 items-center justify-center rounded-full border text-sm font-medium',
                step >= number + 1
                  ? 'border-primary bg-primary text-primary-foreground'
                  : 'border-border bg-background text-muted-foreground',
              )
            "
            aria-hidden="true"
            ><Check
              v-if="step > number + 1"
              class="size-4"
            /><template v-else>{{ number + 1 }}</template></span
          >
          <span
            :class="
              cn(
                'text-xs sm:text-sm',
                step === number + 1
                  ? 'font-medium text-foreground'
                  : 'text-muted-foreground',
              )
            "
            >{{ item.title }}</span
          >
          <span
            v-if="step > number + 1"
            class="sr-only"
            >Completado</span
          >
        </li>
      </ol>
    </nav>
    <form
      class="flex flex-col gap-6"
      novalidate
      @submit.prevent="step < 3 ? next() : submit()"
    >
      <div>
        <p class="text-xs font-medium text-muted-foreground">
          Paso {{ step }} de 3
        </p>
        <h2
          ref="stepHeading"
          tabindex="-1"
          class="mt-1 scroll-mt-6 text-xl font-semibold outline-none"
        >
          {{ steps[step - 1].title }}
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ steps[step - 1].description }}
        </p>
      </div>
      <div
        v-if="step === 1"
        class="grid gap-5 sm:grid-cols-2"
      >
        <div class="flex flex-col gap-2 sm:col-span-2">
          <span class="text-sm font-medium">Archivo del comprobante</span>
          <label
            for="receipt"
            :class="
              cn(
                'relative flex min-h-36 cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed bg-muted/20 p-5 text-center transition-colors focus-within:border-ring focus-within:ring-2 focus-within:ring-ring/50 hover:bg-muted/40',
                form.errors.evidence && 'border-destructive',
              )
            "
          >
            <input
              id="receipt"
              ref="receiptInput"
              type="file"
              class="sr-only"
              accept="image/jpeg,image/png,application/pdf"
              :aria-invalid="!!form.errors.evidence"
              :aria-describedby="
                form.errors.evidence
                  ? 'receipt-help receipt-error'
                  : 'receipt-help'
              "
              @change="chooseEvidence"
            />
            <img
              v-if="preview"
              :src="preview"
              alt="Vista previa del comprobante seleccionado"
              class="max-h-44 max-w-full rounded-md object-contain"
            />
            <FileText
              v-else-if="evidence"
              class="size-8 text-muted-foreground"
              aria-hidden="true"
            />
            <Upload
              v-else
              class="size-8 text-muted-foreground"
              aria-hidden="true"
            />
            <span class="flex max-w-full min-w-0 flex-col gap-1">
              <span class="font-medium">{{
                evidence ? 'Cambiar comprobante' : 'Adjuntar comprobante'
              }}</span>
              <span
                v-if="evidence"
                class="truncate text-sm"
                >{{ evidence.name }}</span
              >
              <span class="text-sm text-muted-foreground">{{
                evidence ? evidenceSize : 'Toca para elegir una foto o un PDF'
              }}</span>
            </span>
          </label>
          <div class="flex items-center justify-between gap-3">
            <p
              id="receipt-help"
              class="text-xs text-muted-foreground"
            >
              JPG, PNG o PDF · Máximo 10 MB
            </p>
            <Button
              v-if="evidence"
              type="button"
              variant="ghost"
              class="min-h-11"
              @click="removeEvidence"
              ><X data-icon="inline-start" />Quitar archivo</Button
            >
          </div>
          <p
            v-if="form.errors.evidence"
            id="receipt-error"
            role="alert"
            class="text-sm text-destructive"
          >
            {{ form.errors.evidence }}
          </p>
        </div>
        <div class="grid gap-2">
          <Label for="bank">Banco de origen</Label
          ><select
            id="bank"
            v-model="form.bank_id"
            required
            class="h-12 min-w-0 rounded-md border border-input bg-background px-3 text-base outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :aria-invalid="!!form.errors.bank_id"
            :aria-describedby="form.errors.bank_id ? 'bank-error' : undefined"
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
          <p
            v-if="form.errors.bank_id"
            id="bank-error"
            role="alert"
            class="text-sm text-destructive"
          >
            {{ form.errors.bank_id }}
          </p>
        </div>
        <div class="grid gap-2">
          <Label for="reference">Número de comprobante</Label
          ><Input
            id="reference"
            v-model="form.reference"
            required
            class="h-12"
            maxlength="191"
            autocomplete="off"
            autocapitalize="off"
            :spellcheck="false"
            placeholder="Número o referencia bancaria"
            :aria-invalid="!!form.errors.reference"
            :aria-describedby="
              form.errors.reference ? 'reference-error' : undefined
            "
          />
          <p
            v-if="form.errors.reference"
            id="reference-error"
            role="alert"
            class="text-sm text-destructive"
          >
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
            class="h-12"
            :aria-invalid="!!form.errors.transaction_date"
            :aria-describedby="
              form.errors.transaction_date ? 'date-error' : undefined
            "
          />
          <p
            v-if="form.errors.transaction_date"
            id="date-error"
            role="alert"
            class="text-sm text-destructive"
          >
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
            class="h-12"
            :aria-invalid="!!form.errors.amount"
            :aria-describedby="form.errors.amount ? 'amount-error' : undefined"
          />
          <p
            v-if="form.errors.amount"
            id="amount-error"
            role="alert"
            class="text-sm text-destructive"
          >
            {{ form.errors.amount }}
          </p>
        </div>
      </div>
      <section
        v-if="step === 2"
        class="flex flex-col gap-3"
      >
        <h3 class="font-medium">Meses que vas a aportar</h3>
        <p class="text-sm text-muted-foreground">
          Selecciona desde el primer mes pendiente, en orden.
        </p>
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
        <div class="grid gap-2 sm:grid-cols-2">
          <button
            v-for="month in months"
            :key="month.id"
            type="button"
            :class="
              cn(
                'flex min-h-14 items-center justify-between gap-3 rounded-lg border px-4 py-3 text-left text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50',
                form.period_ids.includes(month.id)
                  ? 'border-primary bg-primary/5'
                  : 'border-border bg-background hover:bg-muted/40',
              )
            "
            :aria-pressed="form.period_ids.includes(month.id)"
            :disabled="
              hasPendingContribution ||
              (!form.period_ids.includes(month.id) &&
                (nextMonth !== month.id ||
                  allocated + month.amount_cents > entered))
            "
            @click="selectMonth(month.id)"
          >
            <span class="flex items-center gap-3"
              ><span
                :class="
                  cn(
                    'flex size-5 shrink-0 items-center justify-center rounded border',
                    form.period_ids.includes(month.id)
                      ? 'border-primary bg-primary text-primary-foreground'
                      : 'border-input',
                  )
                "
                aria-hidden="true"
                ><Check
                  v-if="form.period_ids.includes(month.id)"
                  class="size-3.5" /></span
              ><span>{{ fundMonth(month.month) }}</span></span
            >
            <span class="shrink-0 font-medium tabular-nums">{{
              usd(month.amount_cents)
            }}</span>
          </button>
        </div>
      </section>
      <section
        v-if="step === 2 && installmentGroups.length"
        class="flex flex-col gap-3"
      >
        <h3 class="font-medium">Cuotas de préstamos</h3>
        <div
          v-for="loan in installmentGroups"
          :key="loan.id"
          class="flex flex-col gap-3"
        >
          <h4 class="text-sm text-muted-foreground">Préstamo #{{ loan.id }}</h4>
          <p
            v-if="!loan.installments.length"
            class="text-sm text-muted-foreground"
          >
            No tiene cuotas pendientes.
          </p>
          <div class="grid gap-2 sm:grid-cols-2">
            <button
              v-for="(item, i) in loan.installments"
              :key="item.id"
              type="button"
              :class="
                cn(
                  'flex min-h-14 items-center justify-between gap-3 rounded-lg border px-4 py-3 text-left text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50',
                  form.installment_ids.includes(item.id)
                    ? 'border-primary bg-primary/5'
                    : 'border-border bg-background hover:bg-muted/40',
                )
              "
              :aria-pressed="form.installment_ids.includes(item.id)"
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
              <span class="flex items-center gap-3"
                ><span
                  :class="
                    cn(
                      'flex size-5 shrink-0 items-center justify-center rounded border',
                      form.installment_ids.includes(item.id)
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-input',
                    )
                  "
                  aria-hidden="true"
                  ><Check
                    v-if="form.installment_ids.includes(item.id)"
                    class="size-3.5" /></span
                ><span>Cuota {{ item.number }}</span></span
              >
              <span class="shrink-0 font-medium tabular-nums">{{
                usd(item.amount_cents)
              }}</span>
            </button>
          </div>
        </div>
      </section>
      <div
        v-if="step >= 2"
        class="flex flex-col gap-2 rounded-lg bg-muted/40 p-4 text-sm"
        aria-live="polite"
      >
        <p class="flex items-center justify-between gap-3">
          Monto transferido
          <strong class="tabular-nums">{{ usd(entered) }}</strong>
        </p>
        <p class="flex items-center justify-between gap-3">
          Total seleccionado
          <strong class="tabular-nums">{{ usd(allocated) }}</strong>
        </p>
        <p
          v-if="remaining !== 0"
          class="flex items-center justify-between gap-3 font-medium"
        >
          {{ remaining > 0 ? 'Falta asignar' : 'Exceso seleccionado' }}
          <span class="tabular-nums">{{ usd(Math.abs(remaining)) }}</span>
        </p>
        <p
          v-else
          class="flex items-center gap-2 text-muted-foreground"
        >
          <Check
            class="size-4"
            aria-hidden="true"
          />El monto coincide con las cuotas seleccionadas.
        </p>
        <p
          v-if="form.errors.amount"
          role="alert"
          class="text-destructive"
        >
          {{ form.errors.amount }}
        </p>
      </div>
      <section
        v-if="step === 3"
        class="flex flex-col gap-5"
        aria-label="Resumen de la transferencia"
      >
        <dl class="divide-y text-sm">
          <div class="flex justify-between gap-4 py-3">
            <dt class="text-muted-foreground">Banco</dt>
            <dd class="text-right font-medium">
              {{ banks.find((bank) => bank.id === Number(form.bank_id))?.name }}
            </dd>
          </div>
          <div class="flex justify-between gap-4 py-3">
            <dt class="text-muted-foreground">Referencia</dt>
            <dd class="min-w-0 text-right font-medium break-all">
              {{ form.reference }}
            </dd>
          </div>
          <div class="flex justify-between gap-4 py-3">
            <dt class="text-muted-foreground">Fecha</dt>
            <dd class="text-right font-medium">
              {{ fundDate(form.transaction_date) }}
            </dd>
          </div>
          <div class="flex justify-between gap-4 py-3">
            <dt class="text-muted-foreground">Archivo</dt>
            <dd class="min-w-0 text-right font-medium break-all">
              {{ evidence?.name }}
            </dd>
          </div>
        </dl>
        <div class="flex flex-col gap-3">
          <h3 class="font-medium">Destino del depósito</h3>
          <ul class="divide-y text-sm">
            <li
              v-for="month in selectedMonths"
              :key="month.id"
              class="flex justify-between gap-3 py-3"
            >
              <span>Aporte de {{ fundMonth(month.month) }}</span
              ><strong class="shrink-0 tabular-nums">{{
                usd(month.amount_cents)
              }}</strong>
            </li>
            <li
              v-for="item in selectedInstallments"
              :key="item.id"
              class="flex justify-between gap-3 py-3"
            >
              <span>Préstamo #{{ item.loanId }} · Cuota {{ item.number }}</span
              ><strong class="shrink-0 tabular-nums">{{
                usd(item.amount_cents)
              }}</strong>
            </li>
          </ul>
        </div>
        <p class="text-sm text-muted-foreground">
          El depósito quedará en revisión hasta que el tesorero compruebe el
          comprobante.
        </p>
      </section>
      <div
        v-if="form.hasErrors && step === 2"
        role="alert"
        class="text-sm text-destructive"
      >
        <p
          v-for="(message, field) in form.errors"
          :key="field"
        >
          {{ message }}
        </p>
      </div>
      <div
        v-if="form.processing"
        class="flex flex-col gap-2"
        role="status"
      >
        <p class="text-sm text-muted-foreground">
          {{
            form.progress
              ? `Subiendo comprobante: ${form.progress.percentage}%`
              : 'Enviando transferencia…'
          }}
        </p>
        <progress
          v-if="form.progress"
          :value="form.progress.percentage"
          max="100"
          aria-label="Progreso de carga del comprobante"
          class="h-2 w-full accent-primary"
        />
      </div>
      <div
        class="sticky bottom-0 -mx-4 flex flex-col gap-2 border-t bg-background/95 px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))] backdrop-blur-sm sm:static sm:mx-0 sm:border-0 sm:bg-background sm:p-0"
      >
        <div class="flex items-center gap-3">
          <Button
            v-if="step > 1"
            type="button"
            variant="outline"
            size="lg"
            class="min-h-12"
            :disabled="form.processing"
            @click="goToStep(step - 1)"
            ><ArrowLeft data-icon="inline-start" />Atrás</Button
          >
          <Button
            v-if="step < 3"
            type="button"
            size="lg"
            class="min-h-12 flex-1"
            @click="next"
            ><span v-if="step === 1">Continuar</span
            ><template v-else
              ><span class="sm:hidden">Revisar</span
              ><span class="hidden sm:inline"
                >Revisar transferencia</span
              ></template
            ><ArrowRight data-icon="inline-end"
          /></Button>
          <Button
            v-if="step === 3"
            type="submit"
            size="lg"
            class="min-h-12 flex-1"
            :disabled="
              form.processing ||
              allocated === 0 ||
              allocated !== entered ||
              !evidence
            "
            >{{ form.processing ? 'Enviando…' : 'Enviar a revisión' }}</Button
          >
        </div>
        <Link
          v-if="!form.processing"
          :href="transactionsIndex()"
          class="flex min-h-11 items-center justify-center text-sm text-muted-foreground underline underline-offset-4"
          >Cancelar</Link
        >
      </div>
    </form>
  </main>
</template>
