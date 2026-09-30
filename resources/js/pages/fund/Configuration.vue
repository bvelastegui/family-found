<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { index as fundIndex } from '@/routes/fund';
import { store as savePeriod } from '@/routes/fund/contribution-periods';
import { store as saveBank, update as updateBank } from '@/routes/fund/banks';
import { usd, operationKey, type FundPagination } from '@/lib/fund';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Period = {
    id: number;
    month: string;
    amount_cents: number;
    locked_at: string | null;
};
type Bank = { id: number; name: string; active: boolean };
defineProps<{ periods: FundPagination<Period>; banks: FundPagination<Bank> }>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Fondo familiar', href: fundIndex() }] },
});
const period = useForm({
    idempotency_key: operationKey(),
    month: '',
    amount: '',
});
const bank = useForm({
    idempotency_key: operationKey(),
    name: '',
    active: true,
});
const updating = useForm({
    idempotency_key: operationKey(),
    name: '',
    active: false,
});
function toggleBank(item: Bank): void {
    updating.idempotency_key = operationKey();
    updating.name = item.name;
    updating.active = !item.active;
    updating.patch(updateBank(item.id).url);
}
</script>

<template>
    <main class="mx-auto flex w-full max-w-4xl flex-col gap-8 p-4 md:p-6">
        <Head title="Configurar fondo" />
        <div>
            <h1 class="text-2xl font-semibold">Configuración del fondo</h1>
            <p class="text-muted-foreground">
                El tesorero define las cuotas comunes y los bancos aceptados.
            </p>
        </div>
        <section class="space-y-4 rounded-xl border p-5">
            <h2 class="text-xl font-medium">Cuotas mensuales</h2>
            <form
                class="flex flex-wrap gap-3"
                @submit.prevent="
                    period.post(savePeriod().url, {
                        onSuccess: () => {
                            period.reset('month', 'amount');
                            period.idempotency_key = operationKey();
                        },
                    })
                "
            >
                <label class="grid gap-1"
                    >Mes (AAAA-MM)<Input
                        v-model="period.month"
                        placeholder="2026-10"
                        required /></label
                ><label class="grid gap-1"
                    >Aporte en USD<Input
                        v-model="period.amount"
                        inputmode="decimal"
                        required /></label
                ><Button class="self-end" :disabled="period.processing"
                    >Guardar cuota</Button
                >
            </form>
            <p
                v-for="(message, field) in period.errors"
                :key="field"
                class="text-sm text-destructive"
            >
                {{ message }}
            </p>
            <ul class="divide-y">
                <li
                    v-for="item in periods.data"
                    :key="item.id"
                    class="flex justify-between py-2"
                >
                    <span
                        >{{ item.month }} ·
                        {{
                            item.locked_at
                                ? 'Fijada por una transacción'
                                : 'Sin usar'
                        }}</span
                    ><strong>{{ usd(item.amount_cents) }}</strong>
                </li>
            </ul>
            <nav class="flex gap-2" aria-label="Páginas de cuotas">
                <Link
                    v-for="(link, i) in periods.links"
                    :key="i"
                    v-show="link.url"
                    class="rounded border px-2 py-1"
                    :href="link.url ?? fundIndex().url"
                    v-html="link.label"
                />
            </nav>
        </section>
        <section class="space-y-4 rounded-xl border p-5">
            <h2 class="text-xl font-medium">Bancos</h2>
            <form
                class="flex flex-wrap gap-3"
                @submit.prevent="
                    bank.post(saveBank().url, {
                        onSuccess: () => {
                            bank.reset('name');
                            bank.idempotency_key = operationKey();
                        },
                    })
                "
            >
                <label class="grid gap-1"
                    >Nombre del banco<Input
                        v-model="bank.name"
                        required /></label
                ><Button class="self-end" :disabled="bank.processing"
                    >Añadir banco</Button
                >
            </form>
            <p
                v-for="(message, field) in bank.errors"
                :key="field"
                class="text-sm text-destructive"
            >
                {{ message }}
            </p>
            <ul class="divide-y">
                <li
                    v-for="item in banks.data"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 py-2"
                >
                    <span
                        >{{ item.name }} ·
                        {{ item.active ? 'Activo' : 'Inactivo' }}</span
                    ><Button
                        size="sm"
                        variant="outline"
                        :disabled="updating.processing"
                        @click="toggleBank(item)"
                        >{{ item.active ? 'Desactivar' : 'Activar' }}</Button
                    >
                </li>
            </ul>
            <nav class="flex gap-2" aria-label="Páginas de bancos">
                <Link
                    v-for="(link, i) in banks.links"
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
