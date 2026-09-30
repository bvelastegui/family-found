<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { approve, reject, correct } from '@/routes/fund/transactions';
import { show as evidenceShow } from '@/routes/fund/evidences';
import { index as fundIndex } from '@/routes/fund';
import { operationKey, usd } from '@/lib/fund';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Transaction = {
    id: number;
    status: string;
    amount_cents: number;
    transaction_date: string;
    bank_name: string;
    reference: string;
    evidence_id: number;
    corrected_from_id: number | null;
    superseded_by_id: number | null;
};
const props = defineProps<{
    transaction: Transaction;
    allocations: {
        id: number;
        amount_cents: number;
        contribution_period_id: number | null;
        loan_installment_id: number | null;
        month: string | null;
        installment_number: number | null;
        loan_id: number | null;
        capital_cents: number;
        interest_cents: number;
    }[];
    events: {
        id: number;
        event: string;
        created_at: string;
        data: Record<string, unknown> | string;
    }[];
    isTreasurer: boolean;
    banks: { id: number; name: string }[];
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Fondo familiar', href: fundIndex() }] },
});
const approval = useForm({ idempotency_key: operationKey() });
const rejection = useForm({ idempotency_key: operationKey(), reason: '' });
const correction = useForm({
    idempotency_key: operationKey(),
    reason: '',
    bank_id:
        props.banks.find((bank) => bank.name === props.transaction.bank_name)
            ?.id ?? '',
    reference: props.transaction.reference,
    transaction_date: props.transaction.transaction_date,
    amount: (props.transaction.amount_cents / 100).toFixed(2),
    period_ids: props.allocations.flatMap((item) =>
        item.contribution_period_id ? [item.contribution_period_id] : [],
    ),
    installment_ids: props.allocations.flatMap((item) =>
        item.loan_installment_id ? [item.loan_installment_id] : [],
    ),
    evidence: null as File | null,
});
</script>

<template>
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
        <Head :title="`Transferencia #${transaction.id}`" />
        <div>
            <h1 class="text-2xl font-semibold">
                Transferencia #{{ transaction.id }}
            </h1>
            <p class="text-muted-foreground">
                {{ transaction.status }} · {{ transaction.transaction_date }}
            </p>
        </div>
        <div class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2">
            <p>
                <span class="text-muted-foreground">Banco: </span
                >{{ transaction.bank_name }}
            </p>
            <p>
                <span class="text-muted-foreground">Comprobante: </span
                >{{ transaction.reference }}
            </p>
            <p>
                <span class="text-muted-foreground">Monto: </span
                >{{ usd(transaction.amount_cents) }}
            </p>
            <a
                class="underline"
                :href="evidenceShow(transaction.evidence_id).url"
                >Descargar evidencia</a
            >
        </div>
        <section class="space-y-2">
            <h2 class="text-lg font-medium">Asignaciones</h2>
            <ul class="divide-y rounded-xl border">
                <li
                    v-for="allocation in allocations"
                    :key="allocation.id"
                    class="flex justify-between p-3"
                >
                    <span>{{
                        allocation.contribution_period_id
                            ? `Aporte de ${allocation.month}`
                            : `Préstamo #${allocation.loan_id}, cuota ${allocation.installment_number} (capital ${usd(allocation.capital_cents)}, interés ${usd(allocation.interest_cents)})`
                    }}</span
                    ><span>{{ usd(allocation.amount_cents) }}</span>
                </li>
            </ul>
        </section>
        <section
            v-if="isTreasurer && transaction.status === 'pending'"
            class="space-y-4 rounded-xl border p-5"
        >
            <h2 class="text-lg font-medium">Conciliación</h2>
            <form @submit.prevent="approval.post(approve(transaction.id).url)">
                <Button :disabled="approval.processing"
                    >Aprobar y contabilizar</Button
                >
                <p class="text-sm text-destructive">
                    {{ approval.errors.idempotency_key }}
                </p>
            </form>
            <form
                class="flex flex-col gap-2"
                @submit.prevent="rejection.post(reject(transaction.id).url)"
            >
                <label for="reason">Motivo del rechazo</label
                ><Input id="reason" v-model="rejection.reason" required />
                <p class="text-sm text-destructive">
                    {{ rejection.errors.reason }}
                </p>
                <Button variant="outline" :disabled="rejection.processing"
                    >Rechazar sin asiento</Button
                >
            </form>
        </section>
        <section
            v-if="
                isTreasurer &&
                transaction.status === 'approved' &&
                !transaction.superseded_by_id
            "
            class="rounded-xl border p-5"
        >
            <h2 class="text-lg font-medium">Corrección contable</h2>
            <p class="text-sm text-muted-foreground">
                La reversión y el reemplazo se registran juntos. Debes indicar
                un motivo y aportar todos los datos y asignaciones correctos.
            </p>
            <form
                class="mt-4 grid gap-3"
                @submit.prevent="
                    correction.post(correct(transaction.id).url, {
                        forceFormData: true,
                    })
                "
            >
                <Input
                    v-model="correction.reason"
                    placeholder="Motivo"
                    required
                /><label class="grid gap-1 text-sm"
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
                    v-model="correction.amount"
                    inputmode="decimal"
                    required
                /><label class="text-sm"
                    >Meses corregidos, identificadores separados por comas<Input
                        :model-value="correction.period_ids.join(',')"
                        @update:model-value="
                            correction.period_ids = String($event)
                                .split(',')
                                .map(Number)
                                .filter(Boolean)
                        " /></label
                ><label class="text-sm"
                    >Cuotas corregidas, identificadores separados por
                    comas<Input
                        :model-value="correction.installment_ids.join(',')"
                        @update:model-value="
                            correction.installment_ids = String($event)
                                .split(',')
                                .map(Number)
                                .filter(Boolean)
                        " /></label
                ><Input
                    type="file"
                    accept="image/jpeg,image/png,application/pdf"
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
                    {{ field }}: {{ message }}
                </p>
                <Button variant="outline" :disabled="correction.processing"
                    >Corregir con reversión y reemplazo</Button
                >
            </form>
        </section>
        <section class="space-y-2">
            <h2 class="text-lg font-medium">Historial</h2>
            <ul class="divide-y rounded-xl border">
                <li v-for="event in events" :key="event.id" class="p-3 text-sm">
                    {{ event.created_at }} · {{ event.event }} ·
                    {{ event.data }}
                </li>
            </ul>
        </section>
    </main>
</template>
