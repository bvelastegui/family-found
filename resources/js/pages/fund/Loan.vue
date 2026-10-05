<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, EllipsisVertical, Download, Pencil } from '@lucide/vue';
import { computed } from 'vue';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { dashboard } from '@/routes';
import { index as loansIndex } from '@/routes/fund/loans';
import {
  cancel,
  disburse,
  correction as correctLoan,
} from '@/routes/fund/loans';
import { show as evidenceShow } from '@/routes/fund/evidences';
import { fundDate, operationKey, usd } from '@/lib/fund';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AutoResizeTextarea from '@/components/AutoResizeTextarea.vue';
import FundStatus from '@/components/FundStatus.vue';

type Installment = {
  id: number;
  number: number;
  due_on: string;
  capital_cents: number;
  interest_cents: number;
  balance_cents: number;
};
type Loan = {
  id: number;
  user: { name: string };
  principal_cents: number;
  monthly_rate: string;
  term_months: number;
  status: string;
  evidence_id: number | null;
  installments: Installment[];
};
const props = defineProps<{
  loan: Loan;
  paidInstallmentIds: number[];
  isTreasurer: boolean;
  banks: { id: number; name: string }[];
  outstandingCents: number;
  canCorrect: boolean;
}>();
const totalWithInterest = computed(() =>
  props.loan.installments.reduce(
    (total, item) => total + item.capital_cents + item.interest_cents,
    0,
  ),
);
defineOptions({
  layout: {
    showHeader: false,
    showMobileHeader: false,
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Préstamos', href: loansIndex() },
    ],
  },
});
const discharge = useForm({
  idempotency_key: operationKey(),
  amount: (props.loan.principal_cents / 100).toFixed(2),
  bank_id: '',
  reference: '',
  transaction_date: '',
  evidence: null as File | null,
});
const cancellation = useForm({ idempotency_key: operationKey(), reason: '' });
</script>

<template>
  <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
    <Head :title="`Préstamo #${loan.id}`" />
    <header
      class="sticky top-0 z-20 -mx-4 -mt-4 flex items-center gap-2 border-b bg-background px-3 py-2 md:-mx-6 md:-mt-6"
    >
      <Link
        :href="loansIndex()"
        aria-label="Volver a préstamos"
        class="inline-flex size-11 shrink-0 items-center justify-center rounded-md hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
        ><ArrowLeft class="size-5"
      /></Link>
      <h1 class="min-w-0 flex-1 text-base font-semibold">
        Préstamo #{{ loan.id }}
      </h1>
      <FundStatus
        :status="loan.status"
        subtle
      />
      <DropdownMenu v-if="loan.evidence_id || canCorrect">
        <DropdownMenuTrigger as-child>
          <Button
            variant="ghost"
            size="icon"
            class="size-11"
            aria-label="Más acciones"
            ><EllipsisVertical class="size-5"
          /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem
            v-if="loan.evidence_id"
            as-child
          >
            <a :href="evidenceShow(loan.evidence_id).url"
              ><Download class="size-4" />Descargar evidencia</a
            >
          </DropdownMenuItem>
          <DropdownMenuItem
            v-if="canCorrect"
            as-child
          >
            <Link :href="correctLoan(loan.id)"
              ><Pencil class="size-4" />Preparar corrección</Link
            >
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </header>
    <section
      class="space-y-3"
      aria-label="Detalles del préstamo"
    >
      <p class="text-sm font-medium break-words">{{ loan.user.name }}</p>
      <dl class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <dt class="text-muted-foreground">Monto del préstamo</dt>
          <dd class="mt-1 font-semibold tabular-nums">
            {{ usd(loan.principal_cents) }}
          </dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Saldo pendiente</dt>
          <dd class="mt-1 font-semibold text-primary tabular-nums">
            {{ usd(outstandingCents) }}
          </dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Tasa mensual</dt>
          <dd class="mt-1 font-medium">
            {{ Number(loan.monthly_rate).toFixed(2) }} %
          </dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Plazo</dt>
          <dd class="mt-1 font-medium">{{ loan.term_months }} meses</dd>
        </div>
        <div v-if="loan.installments.length">
          <dt class="text-muted-foreground">Total con intereses</dt>
          <dd class="mt-1 font-semibold tabular-nums">
            {{ usd(totalWithInterest) }}
          </dd>
        </div>
      </dl>
    </section>
    <section
      v-if="isTreasurer && loan.status === 'reserved'"
      class="grid gap-4 border-t pt-4"
    >
      <h2 class="text-lg font-medium">Confirmar desembolso</h2>
      <p class="text-sm text-muted-foreground">
        La reserva todavía no crea deuda ni movimientos contables.
      </p>
      <form
        class="grid gap-3 sm:grid-cols-2"
        @submit.prevent="
          discharge.post(disburse(loan.id).url, {
            forceFormData: true,
          })
        "
      >
        <label class="grid gap-1"
          >Banco<select
            v-model="discharge.bank_id"
            required
            class="h-12 rounded-md border bg-background px-3 text-base"
          >
            <option value="">Selecciona banco</option>
            <option
              v-for="bank in banks"
              :key="bank.id"
              :value="bank.id"
            >
              {{ bank.name }}
            </option>
          </select></label
        ><label class="grid gap-1"
          >Comprobante<Input
            v-model="discharge.reference"
            class="h-12 text-base!"
            required /></label
        ><label class="grid gap-1"
          >Fecha del desembolso<Input
            v-model="discharge.transaction_date"
            type="date"
            class="h-12 min-w-0 text-base!"
            required /></label
        ><label class="grid gap-1"
          >Monto en USD<Input
            v-model="discharge.amount"
            inputmode="decimal"
            class="h-12 text-base!"
            required /></label
        ><label class="grid gap-1 sm:col-span-2"
          >Evidencia (PDF, JPG o PNG)<Input
            type="file"
            accept="application/pdf,image/jpeg,image/png"
            required
            @change="
              discharge.evidence =
                ($event.target as HTMLInputElement).files?.[0] ?? null
            "
        /></label>
        <p
          v-for="(message, field) in discharge.errors"
          :key="field"
          class="text-sm text-destructive"
        >
          {{ message }}
        </p>
        <Button
          class="min-h-12"
          :disabled="discharge.processing || cancellation.processing"
          >Aprobar desembolso</Button
        >
      </form>
      <form
        class="flex flex-col gap-3"
        @submit.prevent="cancellation.post(cancel(loan.id).url)"
      >
        <Label for="loan-cancellation-reason">Motivo de cancelación</Label>
        <AutoResizeTextarea
          id="loan-cancellation-reason"
          v-model="cancellation.reason"
          placeholder="Motivo de cancelación"
          required
          maxlength="5000"
          :aria-invalid="!!cancellation.errors.reason"
        />
        <p
          v-if="cancellation.errors.reason"
          role="alert"
          class="text-sm text-destructive"
        >
          {{ cancellation.errors.reason }}
        </p>
        <Button
          variant="outline"
          class="min-h-12"
          :disabled="cancellation.processing || discharge.processing"
          >Cancelar reserva</Button
        >
      </form>
    </section>
    <section
      v-if="loan.status === 'disbursed'"
      class="space-y-3"
    >
      <h2 class="text-lg font-medium">Tabla de amortización</h2>
      <ol class="divide-y md:hidden">
        <li
          v-for="item in loan.installments"
          :key="item.id"
          class="space-y-3 py-3"
        >
          <div class="flex items-start justify-between gap-3 text-sm">
            <div>
              <p class="font-medium">Cuota {{ item.number }}</p>
              <p class="text-xs text-muted-foreground">
                Vence {{ fundDate(item.due_on) }}
              </p>
            </div>
            <div class="text-right">
              <p class="font-semibold tabular-nums">
                {{ usd(item.capital_cents + item.interest_cents) }}
              </p>
              <p
                class="text-xs"
                :class="
                  paidInstallmentIds.includes(item.id)
                    ? 'text-primary'
                    : 'text-muted-foreground'
                "
              >
                {{
                  paidInstallmentIds.includes(item.id) ? 'Pagada' : 'Pendiente'
                }}
              </p>
            </div>
          </div>
          <dl class="grid grid-cols-3 gap-2 text-xs">
            <div>
              <dt class="text-muted-foreground">Capital</dt>
              <dd class="tabular-nums">{{ usd(item.capital_cents) }}</dd>
            </div>
            <div>
              <dt class="text-muted-foreground">Interés</dt>
              <dd class="tabular-nums">{{ usd(item.interest_cents) }}</dd>
            </div>
            <div>
              <dt class="text-muted-foreground">Saldo</dt>
              <dd class="tabular-nums">{{ usd(item.balance_cents) }}</dd>
            </div>
          </dl>
        </li>
      </ol>
      <div class="hidden overflow-x-auto rounded-xl border md:block">
        <table class="w-full text-left text-sm">
          <thead class="bg-muted/50">
            <tr>
              <th class="p-3">Cuota</th>
              <th class="p-3">Vence</th>
              <th class="p-3">Capital</th>
              <th class="p-3">Interés</th>
              <th class="p-3">Monto</th>
              <th class="p-3">Saldo</th>
              <th class="p-3">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in loan.installments"
              :key="item.id"
              class="border-t"
            >
              <td class="p-3">{{ item.number }}</td>
              <td class="p-3">{{ fundDate(item.due_on) }}</td>
              <td class="p-3">{{ usd(item.capital_cents) }}</td>
              <td class="p-3">{{ usd(item.interest_cents) }}</td>
              <td class="p-3">
                {{ usd(item.capital_cents + item.interest_cents) }}
              </td>
              <td class="p-3">{{ usd(item.balance_cents) }}</td>
              <td class="p-3">
                {{
                  paidInstallmentIds.includes(item.id) ? 'Pagada' : 'Pendiente'
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</template>
