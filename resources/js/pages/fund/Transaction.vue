<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import {
    index as transactionsIndex,
    approve,
    reject,
    edit,
} from '@/routes/fund/transactions';
import { show as evidenceShow } from '@/routes/fund/evidences';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import AutoResizeTextarea from '@/components/AutoResizeTextarea.vue';
import { Label } from '@/components/ui/label';
import FundStatus from '@/components/FundStatus.vue';
import {
    fundDate,
    fundDateTime,
    fundMonth,
    operationKey,
    usd,
} from '@/lib/fund';

type Transaction = {
    id: number;
    status: string;
    amount_cents: number;
    transaction_date: string;
    created_at: string;
    bank_name: string;
    reference: string;
    evidence_id: number;
    superseded_by_id: number | null;
};
type Allocation = {
    id: number;
    amount_cents: number;
    contribution_period_id: number | null;
    loan_installment_id: number | null;
    month: string | null;
    installment_number: number | null;
    loan_id: number | null;
    capital_cents: number;
    interest_cents: number;
};
type Event = {
    id: number;
    event: string;
    created_at: string;
    actor_id: number;
    actor_name: string;
    data: string | { reason?: string };
};
const props = defineProps<{
    transaction: Transaction;
    allocations: Allocation[];
    events: Event[];
    isTreasurer: boolean;
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inicio', href: dashboard() },
            { title: 'Transacciones', href: transactionsIndex() },
        ],
    },
});
const approval = useForm({ idempotency_key: operationKey() });
const rejection = useForm({ idempotency_key: operationKey(), reason: '' });
const approvalEvent = computed(() =>
    props.events.find((event) => event.event === 'transaction.approved'),
);

function eventTitle(event: Event): string {
    const titles: Record<string, string> = {
        'transaction.registered': 'Comprobante registrado',
        'transaction.approved': 'Transferencia aprobada',
        'transaction.rejected': 'Transferencia rechazada',
        'transaction.corrected': 'Corrección autorizada',
    };

    return titles[event.event] ?? 'Movimiento registrado';
}

function eventDescription(event: Event): string | null {
    const descriptions: Record<string, string> = {
        'transaction.registered':
            'Se envió a revisión, sin efecto en el fondo.',
        'transaction.approved':
            'La transferencia se concilió y se asentó en el ledger.',
        'transaction.rejected': 'No se generaron movimientos contables.',
        'transaction.corrected':
            'Se conservó el original y se contabilizó el reemplazo.',
    };

    return descriptions[event.event] ?? null;
}

function reason(event: Event): string | null {
    try {
        const data =
            typeof event.data === 'string'
                ? (JSON.parse(event.data) as { reason?: string })
                : event.data;
        return typeof data.reason === 'string' ? data.reason : null;
    } catch {
        return null;
    }
}
</script>

<template>
    <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
        <Head :title="`Transferencia #${transaction.id}`" />
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm text-muted-foreground">
                    Historial de transferencias
                </p>
                <h1 class="text-3xl font-semibold tracking-tight">
                    Comprobante #{{ transaction.id }}
                </h1>
                <p class="mt-1 text-muted-foreground">
                    Transferencia del
                    {{ fundDate(transaction.transaction_date) }}
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Registrada en el sistema el
                    {{ fundDateTime(transaction.created_at) }}
                </p>
                <p v-if="approvalEvent" class="mt-2 text-sm font-medium">
                    Autorizada por {{ approvalEvent.actor_name }} el
                    {{ fundDateTime(approvalEvent.created_at) }}
                </p>
            </div>
            <FundStatus :status="transaction.status" />
        </header>
        <Card
            ><CardHeader
                ><CardTitle>Información bancaria</CardTitle
                ><CardDescription
                    >Este registro se contabiliza únicamente si el tesorero lo
                    aprueba.</CardDescription
                ></CardHeader
            ><CardContent class="grid gap-4 text-sm sm:grid-cols-2"
                ><div>
                    <p class="text-muted-foreground">Banco de origen</p>
                    <p class="font-medium">{{ transaction.bank_name }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground">Número de comprobante</p>
                    <p class="font-medium">{{ transaction.reference }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground">Monto transferido</p>
                    <p class="text-xl font-semibold">
                        {{ usd(transaction.amount_cents) }}
                    </p>
                </div>
                <div>
                    <p class="text-muted-foreground">Evidencia</p>
                    <a
                        :href="evidenceShow(transaction.evidence_id).url"
                        class="font-medium underline underline-offset-4"
                        >Descargar comprobante</a
                    >
                </div></CardContent
            ></Card
        >
        <Card
            ><CardHeader><CardTitle>Asignación del pago</CardTitle></CardHeader
            ><CardContent
                ><ul class="divide-y">
                    <li
                        v-for="allocation in allocations"
                        :key="allocation.id"
                        class="flex flex-wrap justify-between gap-2 py-3"
                    >
                        <div>
                            <p class="font-medium">
                                {{
                                    allocation.contribution_period_id
                                        ? `Aporte de ${allocation.month ? fundMonth(allocation.month.slice(0, 7)) : ''}`
                                        : `Préstamo #${allocation.loan_id}, cuota ${allocation.installment_number}`
                                }}
                            </p>
                            <p
                                v-if="allocation.loan_installment_id"
                                class="text-sm text-muted-foreground"
                            >
                                Capital {{ usd(allocation.capital_cents) }} ·
                                Interés {{ usd(allocation.interest_cents) }}
                            </p>
                        </div>
                        <strong>{{ usd(allocation.amount_cents) }}</strong>
                    </li>
                </ul></CardContent
            ></Card
        >
        <Card v-if="isTreasurer && transaction.status === 'pending'"
            ><CardHeader
                ><CardTitle>Conciliar comprobante</CardTitle
                ><CardDescription
                    >Revisa la evidencia y el monto antes de decidir. El rechazo
                    requiere un motivo.</CardDescription
                ></CardHeader
            ><CardContent class="flex flex-col gap-5"
                ><form
                    @submit.prevent="approval.post(approve(transaction.id).url)"
                >
                    <Button :disabled="approval.processing"
                        >Aprobar y contabilizar</Button
                    >
                    <p
                        v-if="approval.hasErrors"
                        role="alert"
                        class="mt-2 text-sm text-destructive"
                    >
                        La aprobación no se completó. Comprueba que las
                        asignaciones sigan siendo válidas.
                    </p>
                </form>
                <form
                    class="flex flex-col gap-2"
                    @submit.prevent="rejection.post(reject(transaction.id).url)"
                >
                    <Label for="rejection-reason">Motivo del rechazo</Label
                    ><AutoResizeTextarea
                        id="rejection-reason"
                        v-model="rejection.reason"
                        required
                        :aria-invalid="!!rejection.errors.reason"
                        maxlength="5000"
                    />
                    <p
                        v-if="rejection.errors.reason"
                        class="text-sm text-destructive"
                    >
                        {{ rejection.errors.reason }}
                    </p>
                    <Button variant="outline" :disabled="rejection.processing"
                        >Rechazar sin asiento</Button
                    >
                </form></CardContent
            ></Card
        >
        <Card
            v-if="
                isTreasurer &&
                transaction.status === 'approved' &&
                !transaction.superseded_by_id
            "
            ><CardHeader
                ><CardTitle>¿Necesitas corregir el registro?</CardTitle
                ><CardDescription
                    >La corrección conserva el comprobante original y
                    contabiliza la reversión y el reemplazo
                    juntos.</CardDescription
                ></CardHeader
            ><CardContent
                ><Button variant="outline" as-child
                    ><Link :href="edit(transaction.id)"
                        >Preparar corrección</Link
                    ></Button
                ></CardContent
            ></Card
        >
        <Card
            ><CardHeader
                ><CardTitle>Historial de decisiones</CardTitle
                ><CardDescription
                    >Quién intervino y qué sucedió con este comprobante.
                    Horarios de Ecuador.</CardDescription
                ></CardHeader
            ><CardContent
                ><ol
                    v-if="events.length"
                    class="text-sm"
                    aria-label="Decisiones sobre la transferencia"
                >
                    <li
                        v-for="event in events"
                        :key="event.id"
                        class="relative border-s-2 border-border ps-5 pb-6 last:border-transparent last:pb-0"
                    >
                        <span
                            class="absolute -start-1.5 top-0.5 size-3 rounded-full border-2 border-primary bg-background"
                            aria-hidden="true"
                        />
                        <p class="font-semibold">{{ eventTitle(event) }}</p>
                        <p class="mt-1 text-muted-foreground">
                            {{ event.actor_name }} ·
                            <time
                                :datetime="
                                    event.created_at.replace(' ', 'T') + 'Z'
                                "
                                >{{ fundDateTime(event.created_at) }}</time
                            >
                        </p>
                        <p
                            v-if="eventDescription(event)"
                            class="mt-2 text-muted-foreground"
                        >
                            {{ eventDescription(event) }}
                        </p>
                        <p
                            v-if="reason(event)"
                            class="mt-2 rounded-md bg-muted px-3 py-2 break-words whitespace-pre-wrap"
                        >
                            Motivo: {{ reason(event) }}
                        </p>
                    </li>
                </ol>
                <p v-else class="text-muted-foreground">
                    Todavía no hay decisiones registradas.
                </p></CardContent
            ></Card
        ><Link
            :href="transactionsIndex()"
            class="text-sm underline underline-offset-4"
            >Volver al historial</Link
        >
    </main>
</template>
