<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { index as fundIndex } from '@/routes/fund';
import { store, show } from '@/routes/fund/loans';
import { operationKey, usd, type FundPagination } from '@/lib/fund';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Loan = {
    id: number;
    principal_cents: number;
    outstanding_cents: number;
    monthly_rate: string;
    term_months: number;
    status: string;
    user: { name: string };
};
defineProps<{
    loans: FundPagination<Loan>;
    isTreasurer: boolean;
    users: { id: number; name: string }[];
    banks: { id: number; name: string }[];
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Fondo familiar', href: fundIndex() }] },
});
const form = useForm({
    idempotency_key: operationKey(),
    user_id: '',
    amount: '',
    monthly_rate: '',
    term_months: '',
});
</script>

<template>
    <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
        <Head title="Préstamos" />
        <div>
            <h1 class="text-2xl font-semibold">Préstamos</h1>
            <p class="text-muted-foreground">
                Consulta reservas, desembolsos y tablas de amortización.
            </p>
        </div>
        <section
            v-if="isTreasurer"
            class="space-y-4 rounded-xl border bg-card p-5"
        >
            <h2 class="text-lg font-medium">Reservar un préstamo</h2>
            <form
                class="grid gap-3 sm:grid-cols-2"
                @submit.prevent="
                    form.post(store().url, {
                        onSuccess: () => {
                            form.idempotency_key = operationKey();
                        },
                    })
                "
            >
                <label class="grid gap-1 text-sm"
                    >Participante<select
                        v-model="form.user_id"
                        required
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">Selecciona usuario</option>
                        <option
                            v-for="user in users"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.name }}
                        </option>
                    </select></label
                ><label class="grid gap-1 text-sm"
                    >Principal en USD<Input
                        v-model="form.amount"
                        inputmode="decimal"
                        required /></label
                ><label class="grid gap-1 text-sm"
                    >Tasa mensual porcentual<Input
                        v-model="form.monthly_rate"
                        inputmode="decimal"
                        placeholder="1.00"
                        required /></label
                ><label class="grid gap-1 text-sm"
                    >Plazo en meses<Input
                        v-model="form.term_months"
                        type="number"
                        min="1"
                        required
                /></label>
                <p
                    v-for="(message, field) in form.errors"
                    :key="field"
                    class="text-sm text-destructive"
                >
                    {{ message }}
                </p>
                <Button :disabled="form.processing"
                    >Reservar capital del fondo</Button
                >
            </form>
        </section>
        <section class="space-y-3">
            <h2 class="text-lg font-medium">Historial de préstamos</h2>
            <p
                v-if="loans.data.length === 0"
                class="rounded-xl border p-5 text-muted-foreground"
            >
                Todavía no se han registrado préstamos.
            </p>
            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="loan in loans.data"
                    :key="loan.id"
                    class="flex flex-wrap items-center justify-between gap-2 p-4"
                >
                    <span
                        >#{{ loan.id }} · {{ loan.user.name }} ·
                        {{ usd(loan.outstanding_cents) }} pendiente ·
                        {{ loan.status }}</span
                    ><Link class="underline" :href="show(loan.id)">Abrir</Link>
                </li>
            </ul>
            <nav class="flex gap-2" aria-label="Páginas de préstamos">
                <Link
                    v-for="(link, i) in loans.links"
                    :key="i"
                    v-show="link.url"
                    class="rounded border px-2 py-1"
                    :href="link.url ?? fundIndex().url"
                    v-html="link.label"
                />
            </nav>
        </section>
    </main>
</template>
