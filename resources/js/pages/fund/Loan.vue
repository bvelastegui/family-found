<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { index as fundIndex } from '@/routes/fund';
import { cancel, disburse, correct } from '@/routes/fund/loans';
import { show as evidenceShow } from '@/routes/fund/evidences';
import { operationKey, usd } from '@/lib/fund';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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
    layout: { breadcrumbs: [{ title: 'Fondo familiar', href: fundIndex() }] },
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
const correction = useForm({
    idempotency_key: operationKey(),
    amount: (props.loan.principal_cents / 100).toFixed(2),
    monthly_rate: props.loan.monthly_rate,
    term_months: props.loan.term_months,
    bank_id: '',
    reference: '',
    transaction_date: '',
    evidence: null as File | null,
    reason: '',
});
</script>

<template>
    <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <Head :title="`Préstamo #${loan.id}`" />
        <div>
            <h1 class="text-2xl font-semibold">Préstamo #{{ loan.id }}</h1>
            <p class="text-muted-foreground">
                {{ loan.user.name }} · {{ loan.status }} ·
                {{ usd(loan.principal_cents) }} desembolsado ·
                {{ usd(outstandingCents) }} pendiente ·
                {{ loan.monthly_rate }} % mensual · {{ loan.term_months }} meses
            </p>
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
                                ($event.target as HTMLInputElement)
                                    .files?.[0] ?? null
                        "
                /></label>
                <p
                    v-for="(message, field) in discharge.errors"
                    :key="field"
                    class="text-sm text-destructive"
                >
                    {{ message }}
                </p>
                <Button :disabled="discharge.processing"
                    >Aprobar desembolso</Button
                >
            </form>
            <form
                class="flex gap-3"
                @submit.prevent="cancellation.post(cancel(loan.id).url)"
            >
                <Input
                    v-model="cancellation.reason"
                    placeholder="Motivo de cancelación"
                    required
                /><Button variant="outline" :disabled="cancellation.processing"
                    >Cancelar reserva</Button
                >
            </form>
        </section>
        <section v-if="loan.status === 'disbursed'" class="space-y-3">
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
                            <td class="p-3">{{ item.due_on }}</td>
                            <td class="p-3">{{ usd(item.capital_cents) }}</td>
                            <td class="p-3">{{ usd(item.interest_cents) }}</td>
                            <td class="p-3">
                                {{
                                    usd(
                                        item.capital_cents +
                                            item.interest_cents,
                                    )
                                }}
                            </td>
                            <td class="p-3">{{ usd(item.balance_cents) }}</td>
                            <td class="p-3">
                                {{
                                    paidInstallmentIds.includes(item.id)
                                        ? 'Pagada'
                                        : 'Pendiente'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <section
            v-if="isTreasurer && loan.status === 'disbursed'"
            class="rounded-xl border p-5"
        >
            <h2 class="text-lg font-medium">Corregir desembolso</h2>
            <p class="text-sm text-muted-foreground">
                Solo errores de registro. No se permite cambiar una tabla que ya
                tenga pagos.
            </p>
            <form
                class="mt-3 grid gap-3 sm:grid-cols-2"
                @submit.prevent="
                    correction.post(correct(loan.id).url, {
                        forceFormData: true,
                    })
                "
            >
                <Input
                    v-model="correction.reason"
                    placeholder="Motivo"
                    required
                /><Input
                    v-model="correction.amount"
                    placeholder="Monto"
                    required
                /><Input
                    v-model="correction.monthly_rate"
                    placeholder="Tasa mensual"
                    required
                /><Input
                    v-model="correction.term_months"
                    type="number"
                    min="1"
                    required
                /><label class="grid gap-1"
                    >Banco<select
                        v-model="correction.bank_id"
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
                ><Input
                    v-model="correction.reference"
                    placeholder="Comprobante"
                    required
                /><Input
                    v-model="correction.transaction_date"
                    type="date"
                    required
                /><Input
                    type="file"
                    accept="application/pdf,image/jpeg,image/png"
                    required
                    @change="
                        correction.evidence =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                />
                <p
                    v-for="(message, field) in correction.errors"
                    :key="field"
                    class="text-sm text-destructive"
                >
                    {{ message }}
                </p>
                <Button variant="outline" :disabled="correction.processing"
                    >Corregir y reemplazar</Button
                >
            </form>
        </section>
    </main>
</template>
