<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import {
    index as transactionsIndex,
    show as transactionShow,
    create as newTransaction,
} from '@/routes/fund/transactions';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import FundPagination from '@/components/FundPagination.vue';
import FundStatus from '@/components/FundStatus.vue';
import {
    fundDate,
    fundDateTime,
    usd,
    type FundPagination as Pagination,
} from '@/lib/fund';

type Transaction = {
    id: number;
    amount_cents: number;
    transaction_date: string;
    status: string;
    bank_name: string;
    reference: string;
    approved_by: string | null;
    approved_at: string | null;
    user: { name: string };
};
const props = defineProps<{
    transactions: Pagination<Transaction>;
    filters: { status: string; type: string };
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
function filter(status: string, type: string): void {
    router.get(
        transactionsIndex().url,
        { ...(status ? { status } : {}), ...(type ? { type } : {}) },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
        <Head title="Transacciones" />
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    Comprobantes y movimientos
                </p>
                <h1 class="text-3xl font-semibold tracking-tight">
                    Transacciones
                </h1>
                <p class="mt-1 text-muted-foreground">
                    Consulta aportes y pagos de préstamos en un solo historial.
                </p>
            </div>
            <Button as-child
                ><Link :href="newTransaction()"
                    >Registrar transferencia</Link
                ></Button
            >
        </header>
        <Card
            ><CardContent class="flex flex-col gap-5 pt-6"
                ><div class="flex flex-wrap gap-3">
                    <label
                        class="flex flex-col gap-1 text-sm"
                        for="status-filter"
                        >Estado<select
                            id="status-filter"
                            :value="filters.status"
                            class="h-9 rounded-md border bg-background px-3"
                            @change="
                                filter(
                                    ($event.target as HTMLSelectElement).value,
                                    props.filters.type,
                                )
                            "
                        >
                            <option value="">Todos</option>
                            <option value="pending">En revisión</option>
                            <option value="approved">Aprobados</option>
                            <option value="rejected">Rechazados</option>
                        </select></label
                    ><label
                        class="flex flex-col gap-1 text-sm"
                        for="type-filter"
                        >Destino<select
                            id="type-filter"
                            :value="filters.type"
                            class="h-9 rounded-md border bg-background px-3"
                            @change="
                                filter(
                                    props.filters.status,
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="">Todos</option>
                            <option value="contribution">Aportes</option>
                            <option value="loan">Préstamos</option>
                        </select></label
                    >
                </div>
                <p
                    v-if="!transactions.data.length"
                    class="py-6 text-center text-muted-foreground"
                >
                    No hay transacciones para estos filtros.
                </p>
                <div v-else class="flex flex-col divide-y">
                    <Link
                        v-for="item in transactions.data"
                        :key="item.id"
                        :href="transactionShow(item.id)"
                        class="flex flex-wrap items-center justify-between gap-3 py-4 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring"
                        ><div>
                            <p class="font-medium">
                                {{ usd(item.amount_cents) }} ·
                                {{ item.bank_name }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ fundDate(item.transaction_date) }} ·
                                {{ item.reference
                                }}<span v-if="isTreasurer">
                                    · {{ item.user.name }}</span
                                >
                            </p>
                            <p
                                v-if="
                                    item.status === 'approved' &&
                                    item.approved_by &&
                                    item.approved_at
                                "
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Autorizada por {{ item.approved_by }} ·
                                {{ fundDateTime(item.approved_at) }}
                            </p>
                        </div>
                        <FundStatus :status="item.status"
                    /></Link>
                </div> </CardContent></Card
        ><FundPagination
            :links="transactions.links"
            :last-page="transactions.last_page"
            label="Páginas del historial de transacciones"
        />
    </main>
</template>
