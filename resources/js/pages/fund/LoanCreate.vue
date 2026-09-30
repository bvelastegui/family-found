<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import { create as createLoan } from '@/routes/fund/treasury/loans';
import { store as reserveLoan } from '@/routes/fund/loans';
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
import { operationKey, usd } from '@/lib/fund';

const props = defineProps<{
    users: { id: number; name: string }[];
    availableCents: number;
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inicio', href: dashboard() },
            { title: 'Tesorería', href: treasuryIndex() },
            { title: 'Reservar préstamo', href: createLoan() },
        ],
    },
});

const form = useForm({
    idempotency_key: operationKey(),
    user_id: '',
    amount: '',
    monthly_rate: '',
    term_months: '',
});
const enteredCents = computed(() => {
    if (!/^\d+(?:\.\d{1,2})?$/.test(form.amount)) return 0;
    const [whole, cents = ''] = form.amount.split('.');
    return Number(whole) * 100 + Number(cents.padEnd(2, '0'));
});
const insufficientFunds = computed(
    () => enteredCents.value > props.availableCents,
);
</script>

<template>
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-8">
        <Head title="Reservar préstamo" />
        <header>
            <p class="text-sm text-muted-foreground">Tesorería · Préstamos</p>
            <h1 class="text-3xl font-semibold tracking-tight">
                Reservar un préstamo
            </h1>
            <p class="mt-1 text-muted-foreground">
                Selecciona el prestatario y las condiciones que tendrá el
                préstamo.
            </p>
        </header>
        <Card>
            <CardHeader>
                <CardTitle
                    >Dinero disponible: {{ usd(availableCents) }}</CardTitle
                >
                <CardDescription
                    >La reserva aparta capital del fondo. Todavía no transfiere
                    dinero, genera deuda ni crea asientos. El desembolso se
                    confirmará después con su evidencia.</CardDescription
                >
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-5 sm:grid-cols-2"
                    @submit.prevent="form.post(reserveLoan().url)"
                >
                    <div class="flex flex-col gap-2 sm:col-span-2">
                        <Label for="loan-borrower">Prestatario</Label>
                        <select
                            id="loan-borrower"
                            v-model="form.user_id"
                            required
                            class="h-9 rounded-md border bg-background px-3"
                            :aria-invalid="!!form.errors.user_id"
                        >
                            <option value="">Selecciona un participante</option>
                            <option
                                v-for="user in users"
                                :key="user.id"
                                :value="user.id"
                            >
                                {{ user.name }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.user_id"
                            role="alert"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.user_id }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="loan-amount"
                            >Capital a reservar en USD</Label
                        >
                        <Input
                            id="loan-amount"
                            v-model="form.amount"
                            inputmode="decimal"
                            placeholder="100.00"
                            required
                            :aria-invalid="
                                !!form.errors.amount || insufficientFunds
                            "
                        />
                        <p
                            v-if="insufficientFunds"
                            role="alert"
                            class="text-sm text-destructive"
                        >
                            El importe supera el disponible del fondo.
                        </p>
                        <p
                            v-if="form.errors.amount"
                            role="alert"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.amount }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="loan-rate"
                            >Tasa de interés mensual (%)</Label
                        >
                        <Input
                            id="loan-rate"
                            v-model="form.monthly_rate"
                            inputmode="decimal"
                            placeholder="1.00"
                            required
                            :aria-invalid="!!form.errors.monthly_rate"
                        />
                        <p class="text-xs text-muted-foreground">
                            También se admite tasa 0.
                        </p>
                        <p
                            v-if="form.errors.monthly_rate"
                            role="alert"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.monthly_rate }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="loan-term">Plazo en meses</Label>
                        <Input
                            id="loan-term"
                            v-model="form.term_months"
                            type="number"
                            min="1"
                            required
                            :aria-invalid="!!form.errors.term_months"
                        />
                        <p
                            v-if="form.errors.term_months"
                            role="alert"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.term_months }}
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-3 sm:col-span-2"
                    >
                        <Button
                            :disabled="
                                form.processing ||
                                !form.user_id ||
                                enteredCents <= 0 ||
                                insufficientFunds ||
                                !form.monthly_rate ||
                                !form.term_months
                            "
                            >Reservar capital</Button
                        >
                        <Link
                            :href="treasuryIndex()"
                            class="text-sm underline underline-offset-4"
                            >Volver a Tesorería</Link
                        >
                    </div>
                </form>
            </CardContent>
        </Card>
    </main>
</template>
