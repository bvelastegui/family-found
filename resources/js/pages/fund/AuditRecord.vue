<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as auditIndex } from '@/routes/fund/audit';
import { show as evidenceShow } from '@/routes/fund/evidences';
import { show as entryShow } from '@/routes/fund/audit/entries';
import { Button } from '@/components/ui/button';
import FundStatus from '@/components/FundStatus.vue';
import {
  accountLabels,
  auditRecordRoute,
  type AuditEvent,
  type AuditEntry,
} from '@/lib/audit';
import { fundDateTime, fundMonth, usd } from '@/lib/fund';
import { cn } from '@/lib/utils';
const props = defineProps<{
  record: {
    type: string;
    id: number;
    title: string;
    amount_cents: number | null;
    status: string | null;
    participant_name: string | null;
    reference: string | null;
    bank_name: string | null;
    evidence_id: number | null;
    previous_id: number | null;
    replacement_id: number | null;
  };
  events: AuditEvent[];
  entries: AuditEntry[];
  allocations: {
    id: number;
    amount_cents: number;
    capital_cents: number;
    interest_cents: number;
    month: string | null;
    loan_id: number | null;
    number: number | null;
  }[];
  source: { type: string; id: number } | null;
  selectedEvent: number | null;
  returnTo: string;
}>();
const labels: Record<string, string> = {
  previous: 'Valores anteriores',
  reason: 'Motivo',
  amount_cents: 'Monto',
  principal_cents: 'Capital',
  previous_cents: 'Cuota anterior',
  month: 'Mes',
  date: 'Fecha bancaria',
  reference: 'Referencia',
  email: 'Correo',
  name: 'Nombre',
  active: 'Activo',
  monthly_rate: 'Tasa mensual (%)',
  term_months: 'Plazo en meses',
  user_id: 'Participante',
  previous_id: 'Responsable anterior',
  treasurer_id: 'Tesorero designado',
  auditor_id: 'Auditor designado',
  replacement_id: 'Reemplazo',
  reversal_entry_id: 'Asiento de reverso',
  journal_entry_id: 'Asiento',
  evidence_id: 'Comprobante',
  corrected_from_id: 'Registro anterior',
};
function detail(
  key: string,
  value: string | number | boolean | null | Record<string, string | boolean>,
): string {
  if (value === null) return '—';
  if (key.endsWith('_cents')) return usd(Number(value));
  if (key.endsWith('_id')) return `#${value}`;
  if (typeof value === 'boolean') return value ? 'Sí' : 'No';
  if (typeof value === 'object')
    return Object.entries(value)
      .map(
        ([field, data]) =>
          `${labels[field] ?? field}: ${typeof data === 'boolean' ? (data ? 'Sí' : 'No') : data}`,
      )
      .join(' · ');
  return String(value);
}
function total(entry: AuditEntry, side: string): number {
  return entry.lines
    .filter((line) => line.side === side)
    .reduce((sum, line) => sum + line.amount_cents, 0);
}
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Auditoría', href: auditIndex() },
      { title: 'Detalle' },
    ],
  },
});
</script>
<template>
  <main class="mx-auto flex w-full max-w-6xl flex-col gap-8 p-4 md:p-8">
    <Head :title="`${record.title} · Auditoría`" />
    <header class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-sm text-muted-foreground">Auditoría · Solo consulta</p>
        <h1 class="text-3xl font-semibold tracking-tight">
          {{ record.title }}
        </h1>
        <p
          v-if="record.participant_name"
          class="mt-1 text-muted-foreground"
        >
          {{ record.participant_name
          }}<template v-if="record.amount_cents !== null">
            · {{ usd(record.amount_cents) }}</template
          >
        </p>
      </div>
      <FundStatus
        v-if="record.status"
        :status="record.status"
        subtle
      />
    </header>
    <section
      v-if="
        record.reference ||
        record.evidence_id ||
        source ||
        record.previous_id ||
        record.replacement_id
      "
      class="flex flex-col gap-4"
      aria-label="Comprobante y relaciones"
    >
      <p
        v-if="record.reference"
        class="text-sm"
      >
        {{ record.bank_name }} · Referencia
        <strong>{{ record.reference }}</strong>
      </p>
      <div class="flex flex-wrap gap-3">
        <Button
          v-if="record.evidence_id"
          as-child
          variant="outline"
          ><a :href="evidenceShow(record.evidence_id).url"
            >Descargar comprobante</a
          ></Button
        >
        <Button
          v-if="source && auditRecordRoute(source.type, source.id)"
          as-child
          variant="outline"
          ><Link :href="auditRecordRoute(source.type, source.id, returnTo)!"
            >Abrir origen</Link
          ></Button
        >
        <Button
          v-if="
            record.previous_id &&
            auditRecordRoute(record.type, record.previous_id)
          "
          as-child
          variant="outline"
          ><Link
            :href="auditRecordRoute(record.type, record.previous_id, returnTo)!"
            >Registro anterior #{{ record.previous_id }}</Link
          ></Button
        >
        <Button
          v-if="
            record.replacement_id &&
            auditRecordRoute(record.type, record.replacement_id)
          "
          as-child
          variant="outline"
          ><Link
            :href="
              auditRecordRoute(record.type, record.replacement_id, returnTo)!
            "
            >Reemplazo #{{ record.replacement_id }}</Link
          ></Button
        >
      </div>
    </section>
    <section
      v-if="allocations.length"
      aria-labelledby="allocation-title"
      class="flex flex-col gap-4"
    >
      <h2
        id="allocation-title"
        class="text-lg font-semibold"
      >
        Asignación del depósito
      </h2>
      <div class="overflow-x-auto rounded-lg border">
        <table class="w-full min-w-lg text-left text-sm">
          <caption class="sr-only">
            Asignaciones de la transferencia
          </caption>
          <thead class="bg-muted/70 text-muted-foreground">
            <tr>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Destino
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Capital
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Interés
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Monto
              </th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr
              v-for="allocation in allocations"
              :key="allocation.id"
            >
              <td class="px-4 py-3">
                {{
                  allocation.month
                    ? `Aporte de ${fundMonth(allocation.month.slice(0, 7))}`
                    : `Préstamo #${allocation.loan_id}, cuota ${allocation.number}`
                }}
              </td>
              <td class="px-4 py-3 text-right tabular-nums">
                {{ allocation.loan_id ? usd(allocation.capital_cents) : '—' }}
              </td>
              <td class="px-4 py-3 text-right tabular-nums">
                {{ allocation.loan_id ? usd(allocation.interest_cents) : '—' }}
              </td>
              <td class="px-4 py-3 text-right font-medium tabular-nums">
                {{ usd(allocation.amount_cents) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
    <section
      aria-labelledby="entries-title"
      class="flex flex-col gap-5"
    >
      <h2
        id="entries-title"
        class="text-lg font-semibold"
      >
        Asientos y reversos
      </h2>
      <p
        v-if="!entries.length"
        class="text-sm text-muted-foreground"
      >
        Este registro no tiene asientos contables asociados.
      </p>
      <article
        v-for="entry in entries"
        :key="entry.id"
        class="flex flex-col gap-3"
      >
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h3 class="font-medium">
              Asiento #{{ entry.id
              }}<template v-if="entry.reversal_of_id">
                · Reverso de
                <Link
                  :href="
                    entryShow(entry.reversal_of_id, {
                      query: { return_to: returnTo },
                    })
                  "
                  class="underline underline-offset-4"
                  >#{{ entry.reversal_of_id }}</Link
                ></template
              >
            </h3>
            <p class="text-xs text-muted-foreground">
              {{ entry.actor_name }} · {{ fundDateTime(entry.created_at) }}
            </p>
          </div>
          <Link
            v-if="
              entry.transaction_id &&
              (record.type !== 'transaction' ||
                record.id !== entry.transaction_id)
            "
            :href="
              auditRecordRoute('transaction', entry.transaction_id, returnTo)!
            "
            class="text-sm underline underline-offset-4"
            >Transferencia #{{ entry.transaction_id }}</Link
          >
        </div>
        <div class="overflow-x-auto rounded-lg border">
          <table class="w-full min-w-[650px] text-left text-sm">
            <caption class="sr-only">
              Líneas del asiento
              {{
                entry.id
              }}
            </caption>
            <thead class="bg-muted/70 text-muted-foreground">
              <tr>
                <th
                  scope="col"
                  class="px-4 py-3 font-medium"
                >
                  Cuenta
                </th>
                <th
                  scope="col"
                  class="px-4 py-3 font-medium"
                >
                  Participante
                </th>
                <th
                  scope="col"
                  class="px-4 py-3 font-medium"
                >
                  Préstamo
                </th>
                <th
                  scope="col"
                  class="px-4 py-3 text-right font-medium"
                >
                  Débito
                </th>
                <th
                  scope="col"
                  class="px-4 py-3 text-right font-medium"
                >
                  Crédito
                </th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr
                v-for="line in entry.lines"
                :key="line.id"
              >
                <td class="px-4 py-3">
                  {{ accountLabels[line.account] ?? line.account }}
                </td>
                <td class="px-4 py-3">{{ line.participant_name ?? '—' }}</td>
                <td class="px-4 py-3">
                  {{ line.loan_id ? `#${line.loan_id}` : '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums">
                  {{ line.side === 'debit' ? usd(line.amount_cents) : '—' }}
                </td>
                <td class="px-4 py-3 text-right tabular-nums">
                  {{ line.side === 'credit' ? usd(line.amount_cents) : '—' }}
                </td>
              </tr>
            </tbody>
            <tfoot class="border-t bg-muted/20">
              <tr>
                <th
                  scope="row"
                  colspan="3"
                  class="px-4 py-3 font-medium"
                >
                  Totales
                </th>
                <td class="px-4 py-3 text-right font-medium tabular-nums">
                  {{ usd(total(entry, 'debit')) }}
                </td>
                <td class="px-4 py-3 text-right font-medium tabular-nums">
                  {{ usd(total(entry, 'credit')) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </article>
    </section>
    <section
      aria-labelledby="events-title"
      class="flex flex-col gap-4"
    >
      <h2
        id="events-title"
        class="text-lg font-semibold"
      >
        Historial de decisiones
      </h2>
      <p
        v-if="!events.length"
        class="text-sm text-muted-foreground"
      >
        No hay eventos registrados para este asiento. Puedes consultar su
        operación de origen.
      </p>
      <ol class="flex flex-col gap-4">
        <li
          v-for="event in events"
          :key="event.id"
          :class="
            cn(
              'rounded-lg border p-4',
              selectedEvent === event.id && 'border-primary bg-primary/5',
            )
          "
        >
          <p class="font-medium">{{ event.title }}</p>
          <p class="mt-1 text-xs text-muted-foreground">
            {{ event.actor_name }} · {{ fundDateTime(event.created_at) }}
          </p>
          <dl
            v-if="Object.keys(event.data).length"
            class="mt-3 flex flex-col gap-2 text-sm"
          >
            <div
              v-for="(value, key) in event.data"
              :key="key"
              class="flex flex-wrap gap-x-3 gap-y-1"
            >
              <dt class="text-muted-foreground">{{ labels[key] ?? key }}:</dt>
              <dd class="min-w-0 break-words whitespace-pre-wrap">
                {{ detail(key, value) }}
              </dd>
            </div>
          </dl>
        </li>
      </ol>
    </section>
    <Link
      :href="returnTo"
      class="text-sm underline underline-offset-4"
      >Volver al listado de auditoría</Link
    >
  </main>
</template>
