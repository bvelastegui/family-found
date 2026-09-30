<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import {
    index as transactionsIndex,
    show as transactionShow,
    correct,
} from '@/routes/fund/transactions';
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
import AutoResizeTextarea from '@/components/AutoResizeTextarea.vue';
import { fundDate, fundMonth, operationKey, usd } from '@/lib/fund';

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
    transaction: {
        id: number;
        bank_id: number;
        reference: string;
        transaction_date: string;
        amount_cents: number;
    };
    banks: { id: number; name: string }[];
    periods: Period[];
    loans: { id: number; installments: Installment[] }[];
    selectedPeriodIds: number[];
    selectedInstallmentIds: number[];
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
const form = useForm({
    idempotency_key: operationKey(),
    reason: '',
    bank_id: props.transaction.bank_id,
    reference: props.transaction.reference,
    transaction_date: props.transaction.transaction_date,
    amount: (props.transaction.amount_cents / 100).toFixed(2),
    period_ids: [...props.selectedPeriodIds],
    installment_ids: [...props.selectedInstallmentIds],
    evidence: null as File | null,
});
const unpaidPeriods = computed(() =>
    props.periods.filter((item) => !item.paid),
);
const groupedInstallments = computed(() =>
    props.loans.map((loan) => ({
        id: loan.id,
        installments: loan.installments.filter((item) => !item.paid),
    })),
);
const allocated = computed(
    () =>
        props.periods
            .filter((item) => form.period_ids.includes(item.id))
            .reduce((sum, item) => sum + item.amount_cents, 0) +
        props.loans
            .flatMap((loan) => loan.installments)
            .filter((item) => form.installment_ids.includes(item.id))
            .reduce((sum, item) => sum + item.amount_cents, 0),
);
const amountCents = computed(() => {
    if (!/^\d+(\.\d{1,2})?$/.test(form.amount)) return 0;
    const [whole, fraction = ''] = form.amount.split('.');
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
});
const nextPeriod = computed(
    () =>
        unpaidPeriods.value.find((item) => !form.period_ids.includes(item.id))
            ?.id,
);

function choosePeriod(id: number): void {
    const position = form.period_ids.indexOf(id);
    if (position >= 0) {
        form.period_ids = form.period_ids.slice(0, position);
        return;
    }
    const period = unpaidPeriods.value.find((item) => item.id === id);
    if (
        id === nextPeriod.value &&
        period &&
        allocated.value + period.amount_cents <= amountCents.value
    )
        form.period_ids.push(id);
}
function chooseInstallment(loanId: number, id: number): void {
    const items =
        groupedInstallments.value.find((loan) => loan.id === loanId)
            ?.installments ?? [];
    const position = items.findIndex((item) => item.id === id);
    if (position < 0) return;
    if (form.installment_ids.includes(id)) {
        form.installment_ids = form.installment_ids.filter(
            (value) => !items.slice(position).some((item) => item.id === value),
        );
        return;
    }
    if (
        (position === 0 ||
            form.installment_ids.includes(items[position - 1].id)) &&
        allocated.value + items[position].amount_cents <= amountCents.value
    )
        form.installment_ids.push(id);
}
function next(): void {
    form.clearErrors();
    if (
        step.value === 1 &&
        (!form.reason.trim() ||
            !form.bank_id ||
            !form.reference.trim() ||
            !form.transaction_date ||
            amountCents.value <= 0 ||
            !form.evidence)
    ) {
        form.setError(
            'reason',
            'Completa motivo, datos bancarios, monto y nueva evidencia.',
        );
        return;
    }
    if (step.value === 2 && allocated.value !== amountCents.value) {
        form.setError(
            'amount',
            'El monto debe coincidir exactamente con los aportes y cuotas seleccionados.',
        );
        return;
    }
    step.value = Math.min(3, step.value + 1);
}
function submit(): void {
    if (step.value === 3 && allocated.value === amountCents.value)
        form.post(correct(props.transaction.id).url, { forceFormData: true });
}
</script>

<template>
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-8">
        <Head :title="`Corregir comprobante #${transaction.id}`" />
        <header>
            <p class="text-sm text-muted-foreground">Tesorería · Corrección</p>
            <h1 class="text-3xl font-semibold tracking-tight">
                Corregir comprobante #{{ transaction.id }}
            </h1>
            <p class="mt-1 text-muted-foreground">
                El original y su evidencia permanecen en el historial. La
                reversión y el reemplazo se confirman juntos.
            </p>
        </header>
        <nav aria-label="Pasos de la corrección">
            <ol class="grid grid-cols-3 gap-2 text-center text-xs sm:text-sm">
                <li
                    v-for="(label, index) in [
                        'Datos',
                        'Asignación',
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
        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <Card v-if="step === 1"
                ><CardHeader
                    ><CardTitle>Datos corregidos</CardTitle
                    ><CardDescription
                        >Explica el cambio y adjunta una nueva
                        evidencia.</CardDescription
                    ></CardHeader
                ><CardContent class="grid gap-4 sm:grid-cols-2"
                    ><div class="flex flex-col gap-2 sm:col-span-2">
                        <Label for="correction-reason"
                            >Motivo de corrección</Label
                        ><AutoResizeTextarea
                            id="correction-reason"
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
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="correction-bank">Banco</Label
                        ><select
                            id="correction-bank"
                            v-model="form.bank_id"
                            class="h-9 rounded-md border bg-background px-3"
                            required
                        >
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
                        <Label for="correction-reference"
                            >Número de comprobante</Label
                        ><Input
                            id="correction-reference"
                            v-model="form.reference"
                            required
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="correction-date"
                            >Fecha de transferencia</Label
                        ><Input
                            id="correction-date"
                            v-model="form.transaction_date"
                            type="date"
                            required
                        />
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="correction-amount">Monto en USD</Label
                        ><Input
                            id="correction-amount"
                            v-model="form.amount"
                            inputmode="decimal"
                            required
                        />
                    </div>
                    <div class="flex flex-col gap-2 sm:col-span-2">
                        <Label for="correction-evidence"
                            >Nueva evidencia (JPG, PNG o PDF)</Label
                        ><Input
                            id="correction-evidence"
                            type="file"
                            accept="image/jpeg,image/png,application/pdf"
                            required
                            @change="
                                form.evidence =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        /></div></CardContent
            ></Card>
            <Card v-if="step === 2"
                ><CardHeader
                    ><CardTitle>Distribuye el monto</CardTitle
                    ><CardDescription
                        >Elige meses y cuotas completas en orden. La selección
                        original ya está precargada.</CardDescription
                    ></CardHeader
                ><CardContent class="flex flex-col gap-5"
                    ><div>
                        <h2 class="mb-2 font-medium">Aportes mensuales</h2>
                        <p
                            v-if="!unpaidPeriods.length"
                            class="text-sm text-muted-foreground"
                        >
                            No hay períodos disponibles.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="period in unpaidPeriods"
                                :key="period.id"
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                                :class="
                                    form.period_ids.includes(period.id)
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-background'
                                "
                                :aria-pressed="
                                    form.period_ids.includes(period.id)
                                "
                                :disabled="
                                    !form.period_ids.includes(period.id) &&
                                    (period.id !== nextPeriod ||
                                        allocated + period.amount_cents >
                                            amountCents)
                                "
                                @click="choosePeriod(period.id)"
                            >
                                {{ fundMonth(period.month) }} ·
                                {{ usd(period.amount_cents) }}
                            </button>
                        </div>
                    </div>
                    <div v-for="loan in groupedInstallments" :key="loan.id">
                        <h2 class="mb-2 font-medium">
                            Préstamo #{{ loan.id }}
                        </h2>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(item, position) in loan.installments"
                                :key="item.id"
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
                                :class="
                                    form.installment_ids.includes(item.id)
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-background'
                                "
                                :aria-pressed="
                                    form.installment_ids.includes(item.id)
                                "
                                :disabled="
                                    !form.installment_ids.includes(item.id) &&
                                    ((position > 0 &&
                                        !form.installment_ids.includes(
                                            loan.installments[position - 1].id,
                                        )) ||
                                        allocated + item.amount_cents >
                                            amountCents)
                                "
                                @click="chooseInstallment(loan.id, item.id)"
                            >
                                Cuota {{ item.number }} ·
                                {{ usd(item.amount_cents) }}
                            </button>
                        </div>
                    </div>
                    <p aria-live="polite" class="text-sm">
                        Asignado: <strong>{{ usd(allocated) }}</strong> de
                        {{ usd(amountCents) }}
                    </p>
                    <p
                        v-if="form.errors.amount"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.amount }}
                    </p></CardContent
                ></Card
            >
            <Card v-if="step === 3"
                ><CardHeader
                    ><CardTitle>Confirma el reemplazo</CardTitle
                    ><CardDescription
                        >Si un paso falla, el asiento original seguirá
                        vigente.</CardDescription
                    ></CardHeader
                ><CardContent class="flex flex-col gap-2 text-sm"
                    ><p>Motivo: {{ form.reason }}</p>
                    <p>
                        Banco:
                        {{
                            banks.find(
                                (item) => item.id === Number(form.bank_id),
                            )?.name
                        }}
                        · {{ form.reference }}
                    </p>
                    <p>
                        Fecha: {{ fundDate(form.transaction_date) }} ·
                        Evidencia:
                        {{ form.evidence?.name }}
                    </p>
                    <p>
                        Meses seleccionados: {{ form.period_ids.length }} ·
                        Cuotas: {{ form.installment_ids.length }}
                    </p>
                    <p class="font-semibold">
                        Total asignado: {{ usd(allocated) }} de
                        {{ usd(amountCents) }}
                    </p>
                    <p
                        v-for="(message, field) in form.errors"
                        :key="field"
                        role="alert"
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
                ><Button v-if="step < 3" type="button" @click="next"
                    >Continuar</Button
                ><Button
                    v-if="step === 3"
                    :disabled="
                        form.processing ||
                        allocated !== amountCents ||
                        !form.evidence
                    "
                    >Confirmar corrección</Button
                ><Link
                    class="text-sm underline underline-offset-4"
                    :href="transactionShow(transaction.id)"
                    >Cancelar</Link
                >
            </div>
        </form>
    </main>
</template>
