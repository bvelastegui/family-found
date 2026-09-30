<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
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
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';

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
}>();
defineOptions({
  layout: {
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
    <div>
      <h1 class="text-2xl font-semibold">Préstamo #{{ loan.id }}</h1>
      <p class="text-muted-foreground">
        {{ loan.user.name }} · {{ usd(loan.principal_cents) }} de capital
        original · {{ usd(outstandingCents) }} pendiente ·
        {{ loan.monthly_rate }} % mensual · {{ loan.term_months }} meses
      </p>
      <FundStatus
        :status="loan.status"
        class="mt-2"
      />
    </div>
    <a
      v-if="loan.evidence_id"
      class="underline"
      :href="evidenceShow(loan.evidence_id).url"
      >Descargar evidencia del desembolso</a
    >
    <section
      v-if="isTreasurer && loan.status === 'reserved'"
      class="grid gap-4 rounded-xl border p-5"
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
          </select></label
        ><label class="grid gap-1"
          >Comprobante<Input
            v-model="discharge.reference"
            required /></label
        ><label class="grid gap-1"
          >Fecha del desembolso<Input
            v-model="discharge.transaction_date"
            type="date"
            required /></label
        ><label class="grid gap-1"
          >Monto en USD<Input
            v-model="discharge.amount"
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
        <Button :disabled="discharge.processing">Aprobar desembolso</Button>
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
          :disabled="cancellation.processing"
          >Cancelar reserva</Button
        >
      </form>
    </section>
    <section
      v-if="loan.status === 'disbursed'"
      class="space-y-3"
    >
      <h2 class="text-lg font-medium">Tabla de amortización</h2>
      <div class="overflow-x-auto rounded-xl border">
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
    <Card v-if="isTreasurer && loan.status === 'disbursed'">
      <CardHeader>
        <CardTitle>¿Necesitas corregir el desembolso?</CardTitle>
        <CardDescription
          >El registro original y sus asientos permanecen en el historial. La
          pantalla siguiente explica el reemplazo y verifica si ya hay pagos
          asociados.</CardDescription
        >
      </CardHeader>
      <CardContent>
        <Button
          as-child
          variant="outline"
          ><Link :href="correctLoan(loan.id)">Preparar corrección</Link></Button
        >
      </CardContent>
    </Card>
  </main>
</template>
