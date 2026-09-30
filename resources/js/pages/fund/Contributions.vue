<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CircleCheck,
    Clock3,
} from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as contributionsIndex } from '@/routes/fund/contributions';
import {
    create as newTransaction,
    show as transactionShow,
} from '@/routes/fund/transactions';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import FundStatus from '@/components/FundStatus.vue';
import { fundMonth, usd } from '@/lib/fund';

type Period = {
    id: number | null;
    month: string;
    amount_cents: number | null;
    status:
        | 'paid'
        | 'pending'
        | 'unpaid'
        | 'upcoming'
        | 'unconfigured'
        | 'before_start';
    transaction_id: number | null;
};
const props = defineProps<{
    periods: Period[];
    year: number;
    years: number[];
    yearTotalCents: number;
    hasConfiguredPeriods: boolean;
    totalCents: number;
    pendingTransactionId: number | null;
}>();
const paidCount = computed(
    () => props.periods.filter((period) => period.status === 'paid').length,
);
const configuredCount = computed(
    () => props.periods.filter((period) => period.id !== null).length,
);

function changeYear(event: Event): void {
    const selectedYear = Number((event.target as HTMLSelectElement).value);
    router.get(contributionsIndex().url, { year: selectedYear });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Inicio', href: dashboard() },
            { title: 'Aportes', href: contributionsIndex() },
        ],
    },
});
</script>

<template>
    <main class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
        <Head title="Mis aportes" />
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    Mi participación en el fondo
                </p>
                <h1 class="text-3xl font-semibold tracking-tight">Aportes</h1>
                <p class="mt-1 text-muted-foreground">
                    Los meses cuentan como pagados cuando se aprueba el
                    comprobante.
                </p>
            </div>
            <Button as-child
                ><Link :href="newTransaction()">Registrar aporte</Link></Button
            >
        </header>

        <div class="grid gap-4 sm:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Total aportado</CardTitle>
                    <CardDescription
                        >Cuotas aprobadas en todos los años</CardDescription
                    >
                </CardHeader>
                <CardContent class="text-3xl font-semibold tabular-nums">{{
                    usd(totalCents)
                }}</CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Estado de revisión</CardTitle>
                    <CardDescription>Un aporte a la vez</CardDescription>
                </CardHeader>
                <CardContent>
                    <Link
                        v-if="pendingTransactionId"
                        class="font-medium underline underline-offset-4"
                        :href="transactionShow(pendingTransactionId)"
                        >Comprobante #{{ pendingTransactionId }} en
                        revisión</Link
                    >
                    <p v-else class="text-sm text-muted-foreground">
                        No tienes aportes esperando aprobación.
                    </p>
                </CardContent>
            </Card>
        </div>

        <section aria-labelledby="calendar-heading" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="calendar-heading" class="text-xl font-semibold">
                        Aportes de {{ year }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ paidCount }} de {{ configuredCount }} cuotas
                        configuradas pagadas ·
                        {{ usd(yearTotalCents) }} aprobados este año.
                    </p>
                </div>
                <nav
                    class="flex flex-wrap items-center gap-2"
                    aria-label="Elegir año de aportes"
                >
                    <Button
                        v-if="years.includes(year - 1)"
                        variant="outline"
                        size="icon"
                        as-child
                    >
                        <Link
                            :href="
                                contributionsIndex({
                                    query: { year: year - 1 },
                                })
                            "
                            :aria-label="`Ver aportes de ${year - 1}`"
                            ><ChevronLeft aria-hidden="true"
                        /></Link>
                    </Button>
                    <label for="contribution-year" class="sr-only"
                        >Año de aportes</label
                    >
                    <select
                        id="contribution-year"
                        :value="year"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        @change="changeYear"
                    >
                        <option
                            v-for="availableYear in years"
                            :key="availableYear"
                            :value="availableYear"
                        >
                            {{ availableYear }}
                        </option>
                    </select>
                    <Button
                        v-if="years.includes(year + 1)"
                        variant="outline"
                        size="icon"
                        as-child
                    >
                        <Link
                            :href="
                                contributionsIndex({
                                    query: { year: year + 1 },
                                })
                            "
                            :aria-label="`Ver aportes de ${year + 1}`"
                            ><ChevronRight aria-hidden="true"
                        /></Link>
                    </Button>
                </nav>
            </div>
            <Card>
                <CardContent class="pt-6">
                    <p
                        v-if="!hasConfiguredPeriods"
                        class="text-muted-foreground"
                    >
                        Todavía no hay cuotas configuradas. El tesorero define
                        el primer período de aportes.
                    </p>
                    <ol
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <li
                            v-for="period in periods"
                            :key="period.month"
                            class="flex min-h-36 items-start gap-3 rounded-lg border p-4"
                            :class="{
                                'border-dashed bg-muted/20': period.id === null,
                            }"
                        >
                            <component
                                :is="
                                    period.status === 'paid'
                                        ? CircleCheck
                                        : period.status === 'pending'
                                          ? Clock3
                                          : CalendarDays
                                "
                                class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium capitalize">
                                    {{ fundMonth(period.month) }}
                                </h3>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        period.amount_cents !== null
                                            ? usd(period.amount_cents)
                                            : period.status === 'before_start'
                                              ? 'El fondo aún no iniciaba'
                                              : 'Cuota por definir'
                                    }}
                                </p>
                                <FundStatus
                                    :status="period.status"
                                    class="mt-2"
                                />
                                <p
                                    v-if="period.transaction_id"
                                    class="mt-2 text-sm"
                                >
                                    <Link
                                        :href="
                                            transactionShow(
                                                period.transaction_id,
                                            )
                                        "
                                        class="underline underline-offset-4"
                                        >Ver comprobante</Link
                                    >
                                </p>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </section>
    </main>
</template>
