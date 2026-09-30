<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index as fundIndex } from '@/routes/fund';
import {
    create as newTransaction,
    show as transactionShow,
} from '@/routes/fund/transactions';
import { index as loanIndex, show as loanShow } from '@/routes/fund/loans';
import { index as periodIndex } from '@/routes/fund/contribution-periods';
import { edit as treasurerEdit } from '@/routes/administration/treasurer';
import { usd, type FundPagination } from '@/lib/fund';
import { Button } from '@/components/ui/button';

type Transaction = {
    id: number;
    amount_cents: number;
    transaction_date: string;
    status: string;
    user: { name: string };
};
type Loan = {
    id: number;
    principal_cents: number;
    outstanding_cents: number;
    status: string;
    user: { name: string };
};
defineProps<{
    isTreasurer: boolean;
    isAdministrator: boolean;
    balances: {
        contributions: number;
        interest: number;
        principal: number;
        cash: number;
        reserved: number;
        available: number;
    } | null;
    contributedCents: number;
    transactions: FundPagination<Transaction>;
    loans: FundPagination<Loan>;
    paidPeriods: FundPagination<{ month: string; amount_cents: number }>;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Fondo familiar', href: fundIndex() }] },
});
</script>

<template>
    <main class="flex flex-col gap-6 p-4 md:p-6">
        <Head title="Fondo familiar" />
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Fondo familiar</h1>
                <p class="text-muted-foreground">
                    Aportes, préstamos y movimientos conciliados.
                </p>
            </div>
            <Link :href="newTransaction()"
                ><Button>Registrar transferencia</Button></Link
            >
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border bg-card p-5">
                <p class="text-sm text-muted-foreground">
                    Mis aportes aprobados
                </p>
                <strong class="text-2xl">{{ usd(contributedCents) }}</strong>
            </div>
            <template v-if="isTreasurer && balances">
                <div
                    v-for="(value, label) in {
                        'Efectivo del fondo': balances.cash,
                        'Disponible para prestar': balances.available,
                        'Principal pendiente': balances.principal,
                        'Intereses cobrados': balances.interest,
                        Reservado: balances.reserved,
                    }"
                    :key="label"
                    class="rounded-xl border bg-card p-5"
                >
                    <p class="text-sm text-muted-foreground">{{ label }}</p>
                    <strong class="text-2xl">{{ usd(value) }}</strong>
                </div>
            </template>
        </div>
        <div class="flex flex-wrap gap-3 text-sm">
            <Link class="underline underline-offset-4" :href="loanIndex()"
                >Ver préstamos</Link
            >
            <Link
                v-if="isTreasurer"
                class="underline underline-offset-4"
                :href="periodIndex()"
                >Configurar fondo y bancos</Link
            >
            <Link
                v-if="isAdministrator"
                class="underline underline-offset-4"
                :href="treasurerEdit()"
                >Designar tesorero</Link
            >
        </div>
        <section class="space-y-3">
            <h2 class="text-xl font-medium">Meses aportados</h2>
            <p
                v-if="paidPeriods.data.length === 0"
                class="rounded-xl border p-4 text-muted-foreground"
            >
                Aún no tienes meses aprobados.
            </p>
            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="period in paidPeriods.data"
                    :key="period.month"
                    class="flex justify-between p-3"
                >
                    <span>{{ period.month }}</span
                    ><strong>{{ usd(period.amount_cents) }}</strong>
                </li>
            </ul>
            <nav class="flex flex-wrap gap-2" aria-label="Páginas de aportes">
                <Link
                    v-for="(link, i) in paidPeriods.links"
                    :key="i"
                    v-show="link.url"
                    class="rounded border px-2 py-1"
                    :href="link.url ?? fundIndex().url"
                    v-html="link.label"
                />
            </nav>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-medium">Transferencias</h2>
            <div
                v-if="transactions.data.length === 0"
                class="rounded-xl border p-6 text-muted-foreground"
            >
                Todavía no hay transferencias registradas.
            </div>
            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50">
                        <tr>
                            <th class="p-3">Fecha</th>
                            <th class="p-3">Participante</th>
                            <th class="p-3">Monto</th>
                            <th class="p-3">Estado</th>
                            <th class="p-3">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in transactions.data"
                            :key="item.id"
                            class="border-t"
                        >
                            <td class="p-3">{{ item.transaction_date }}</td>
                            <td class="p-3">{{ item.user.name }}</td>
                            <td class="p-3">{{ usd(item.amount_cents) }}</td>
                            <td class="p-3">{{ item.status }}</td>
                            <td class="p-3">
                                <Link
                                    class="underline"
                                    :href="transactionShow(item.id)"
                                    >Abrir</Link
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <nav
                class="flex flex-wrap gap-2"
                aria-label="Páginas de transferencias"
            >
                <Link
                    v-for="(link, i) in transactions.links"
                    :key="i"
                    v-show="link.url"
                    class="rounded border px-2 py-1"
                    :class="
                        link.active ? 'bg-primary text-primary-foreground' : ''
                    "
                    :href="link.url ?? fundIndex().url"
                    v-html="link.label"
                />
            </nav>
        </section>
        <section class="space-y-3">
            <h2 class="text-xl font-medium">Préstamos recientes</h2>
            <div
                v-if="loans.data.length === 0"
                class="rounded-xl border p-6 text-muted-foreground"
            >
                Todavía no hay préstamos.
            </div>
            <ul v-else class="divide-y rounded-xl border">
                <li
                    v-for="loan in loans.data"
                    :key="loan.id"
                    class="flex flex-wrap justify-between gap-2 p-3"
                >
                    <span
                        >{{ loan.user.name }} ·
                        {{ usd(loan.outstanding_cents) }} pendiente ·
                        {{ loan.status }}</span
                    ><Link class="underline" :href="loanShow(loan.id)"
                        >Ver amortización</Link
                    >
                </li>
            </ul>
        </section>
    </main>
</template>
