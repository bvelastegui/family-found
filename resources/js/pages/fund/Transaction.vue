<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Check, Download, X } from '@lucide/vue';
import { useSidebar } from '@/components/ui/sidebar/utils';
import { dashboard } from '@/routes';
import {
  index as transactionsIndex,
  approve,
  reject,
  edit,
} from '@/routes/fund/transactions';
import {
  show as evidenceShow,
  preview as evidencePreview,
} from '@/routes/fund/evidences';
import { Button } from '@/components/ui/button';
import AutoResizeTextarea from '@/components/AutoResizeTextarea.vue';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
type Evidence = {
  id: number;
  mime: string;
  original_name: string;
};
const props = defineProps<{
  transaction: Transaction;
  evidence: Evidence;
  allocations: Allocation[];
  events: Event[];
  isTreasurer: boolean;
  returnTo: string | null;
}>();
const returnQuery = computed(() =>
  props.returnTo ? { return_to: props.returnTo } : {},
);
setLayoutProps({
  breadcrumbs: [
    { title: 'Inicio', href: dashboard() },
    {
      title: props.returnTo ? 'Tesorería' : 'Mis transacciones',
      href: props.returnTo ?? transactionsIndex().url,
    },
    { title: `Comprobante #${props.transaction.id}` },
  ],
});
const approval = useForm({ idempotency_key: operationKey() });
const rejection = useForm({ idempotency_key: operationKey(), reason: '' });
const showingRejection = ref(false);
const { setOpenMobile } = useSidebar();
const isPreviewable = computed(() =>
  ['application/pdf', 'image/jpeg', 'image/png'].includes(props.evidence.mime),
);
const evidencePreviewUrl = computed(
  () => evidencePreview(props.evidence.id).url,
);
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
    'transaction.registered': 'Se envió a revisión, sin efecto en el fondo.',
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
  <main class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-3 pb-24 sm:gap-6 sm:p-4 sm:pb-8 md:p-8">
    <Head :title="`Transferencia #${transaction.id}`" />
    <p class="sr-only" aria-live="polite">
      {{ isTreasurer && transaction.status === 'pending' ? 'Acciones disponibles: volver, aprobar o rechazar.' : `Estado: ${transaction.status}` }}
    </p>
    <header class="sticky top-0 z-20 -mx-3 flex items-center gap-1 border-b bg-background/95 px-2 py-2 backdrop-blur sm:static sm:mx-0 sm:gap-2 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none">
      <Link
        :href="returnTo ?? transactionsIndex().url"
        aria-label="Volver"
        class="inline-flex size-10 shrink-0 items-center justify-center rounded-md hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring sm:size-11"
        @click="setOpenMobile(false)"
      >
        <ArrowLeft class="size-5" />
      </Link>
      <div class="min-w-0 flex-1">
        <h1 class="truncate text-base font-semibold sm:text-2xl">
          Comprobante #{{ transaction.id }}
        </h1>
        <p class="truncate text-xs text-muted-foreground sm:text-sm">
          {{ fundDate(transaction.transaction_date) }} · {{ usd(transaction.amount_cents) }}
        </p>
      </div>
      <FundStatus :status="transaction.status" />
      <div
        v-if="isTreasurer && transaction.status === 'pending'"
        class="hidden items-center gap-2 sm:flex"
      >
        <form
          class="shrink-0"
          @submit.prevent="approval.post(approve(transaction.id, { query: returnQuery }).url)"
        >
          <Button
            type="submit"
            size="icon"
            class="size-10 sm:size-11"
            aria-label="Aprobar y contabilizar"
            title="Aprobar y contabilizar"
            :disabled="approval.processing || rejection.processing"
          >
            <Check class="size-5" />
          </Button>
        </form>
        <Button
          v-if="!showingRejection"
          type="button"
          variant="destructive"
          size="icon"
          class="size-10 shrink-0 sm:size-11"
          aria-label="Rechazar comprobante"
          title="Rechazar comprobante"
          :disabled="approval.processing"
          @click="showingRejection = true"
        >
          <X class="size-5" />
        </Button>
      </div>
    </header>
    <p
      v-if="approval.hasErrors"
      role="alert"
      class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
    >
      La aprobación no se completó. Comprueba que las asignaciones sigan siendo válidas.
    </p>
    <p class="hidden text-sm text-muted-foreground sm:block">
      Transferencia del {{ fundDate(transaction.transaction_date) }} · Registrada en el sistema el
      {{ fundDateTime(transaction.created_at) }}
      <span v-if="approvalEvent" class="font-medium text-foreground">
        · Autorizada por {{ approvalEvent.actor_name }} el {{ fundDateTime(approvalEvent.created_at) }}
      </span>
    </p>
    <section aria-labelledby="evidence-title" class="space-y-2">
      <header class="flex items-baseline justify-between gap-2">
        <h2 id="evidence-title" class="text-base font-semibold">Comprobante</h2>
        <p class="truncate text-xs text-muted-foreground">{{ evidence.original_name }}</p>
      </header>
      <p class="text-sm text-muted-foreground">
        Comprueba que el valor indicado en la evidencia coincida con la transferencia registrada:
        <strong class="whitespace-nowrap text-foreground">{{ usd(transaction.amount_cents) }}</strong>.
      </p>
        <div
          v-if="isPreviewable"
          class="overflow-auto rounded-md border bg-muted/30"
        >
          <img
            v-if="evidence.mime.startsWith('image/')"
            :src="evidencePreviewUrl"
            :alt="`Vista previa del comprobante ${evidence.original_name}`"
            class="mx-auto block h-auto max-h-[78vh] w-auto max-w-full object-contain"
            loading="eager"
            fetchpriority="high"
          />
          <iframe
            v-else
            :src="evidencePreviewUrl"
            :title="`Vista previa del comprobante ${evidence.original_name}`"
            class="h-[78vh] min-h-[32rem] w-full"
          />
        </div>
        <p v-else class="rounded-md border px-3 py-2 text-sm text-muted-foreground">
          Este tipo de archivo no admite vista previa.
        </p>
        <div class="flex flex-wrap gap-2">
          <Button variant="outline" size="sm" as-child>
            <a :href="evidenceShow(transaction.evidence_id).url">
              <Download class="size-4" />
              Descargar comprobante
            </a>
          </Button>
        </div>
    </section>
    <section class="space-y-2 border-t pt-3">
      <div>
        <h2 class="text-base font-semibold">Información bancaria</h2>
        <p class="text-xs text-muted-foreground">Solo se contabiliza al aprobar la transferencia.</p>
      </div>
      <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
        <div class="min-w-0">
          <p class="text-muted-foreground">Banco de origen</p>
          <p class="truncate font-medium">{{ transaction.bank_name }}</p>
        </div>
        <div class="min-w-0">
          <p class="text-muted-foreground">Número de comprobante</p>
          <p class="truncate font-medium">{{ transaction.reference }}</p>
        </div>
        <div>
          <p class="text-muted-foreground">Monto transferido</p>
          <p class="font-semibold">
            {{ usd(transaction.amount_cents) }}
          </p>
        </div>
        <div>
          <p class="text-muted-foreground">Fecha del movimiento</p>
          <p class="font-medium">{{ fundDate(transaction.transaction_date) }}</p>
        </div>
      </div>
    </section>
    <section class="space-y-2 border-t pt-3">
      <h2 class="text-base font-semibold">Asignación del pago</h2>
      <ul class="divide-y">
          <li
            v-for="allocation in allocations"
            :key="allocation.id"
            class="flex flex-wrap justify-between gap-2 py-2"
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
                Capital {{ usd(allocation.capital_cents) }} · Interés
                {{ usd(allocation.interest_cents) }}
              </p>
            </div>
            <strong>{{ usd(allocation.amount_cents) }}</strong>
          </li>
      </ul>
    </section>
    <Dialog v-model:open="showingRejection">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Rechazar comprobante</DialogTitle>
          <DialogDescription>
            Indica el motivo del rechazo. No se registrará ningún movimiento en el fondo.
          </DialogDescription>
        </DialogHeader>
        <form
          id="rejection-form"
          class="flex w-full flex-col gap-4"
          @submit.prevent="
            rejection.post(reject(transaction.id, { query: returnQuery }).url, {
              onSuccess: () => {
                showingRejection = false;
              },
            })
          "
        >
          <div class="flex w-full flex-col gap-2">
            <Label for="rejection-reason"
              >Motivo del rechazo (obligatorio)</Label
            >
            <AutoResizeTextarea
              id="rejection-reason"
              v-model="rejection.reason"
              required
              :aria-invalid="!!rejection.errors.reason"
              :aria-describedby="
                rejection.errors.reason ? 'rejection-error' : undefined
              "
              maxlength="5000"
            />
            <p
              v-if="rejection.errors.reason"
              id="rejection-error"
              role="alert"
              class="text-sm text-destructive"
            >
              {{ rejection.errors.reason }}
            </p>
          </div>
          <DialogFooter class="flex-col-reverse gap-2 sm:flex-row">
            <Button
              type="submit"
              variant="destructive"
              :disabled="rejection.processing || approval.processing"
            >
              Confirmar rechazo
            </Button>
            <Button
              type="button"
              variant="ghost"
              :disabled="rejection.processing"
              @click="
                showingRejection = false;
                rejection.clearErrors();
              "
            >
              Cancelar
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
    <section
      v-if="
        isTreasurer &&
        transaction.status === 'approved' &&
        !transaction.superseded_by_id
      "
      class="space-y-2 border-t py-3"
      >
      <div>
        <h2 class="font-semibold">¿Necesitas corregir el registro?</h2>
        <p class="text-xs text-muted-foreground">
          La corrección conserva el comprobante original y contabiliza la reversión y el reemplazo juntos.
        </p>
      </div>
      <div>
        <Button
          variant="outline"
          as-child
        >
          <Link :href="edit(transaction.id)">Preparar corrección</Link>
        </Button>
      </div>
    </section>
    <section class="space-y-2 border-t pt-3">
      <div>
        <h2 class="text-base font-semibold">Historial de decisiones</h2>
        <p class="text-xs text-muted-foreground">
          Quién intervino y qué sucedió con este comprobante. Horarios de Ecuador.
        </p>
      </div>
      <ol
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
              <time :datetime="event.created_at.replace(' ', 'T') + 'Z'">{{
                fundDateTime(event.created_at)
              }}</time>
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
        <p
          v-else
          class="text-muted-foreground"
        >
          Todavía no hay decisiones registradas.
        </p>
    </section>
    <Link
      :href="returnTo ?? transactionsIndex().url"
      class="hidden text-sm underline underline-offset-4 sm:inline"
      >{{ returnTo ? 'Volver al listado' : 'Volver a mis transacciones' }}</Link
    >
    <div
      v-if="isTreasurer && transaction.status === 'pending'"
      class="fixed inset-x-0 bottom-0 z-30 flex gap-3 border-t bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur sm:hidden"
    >
      <Button
        class="h-12 flex-1"
        :disabled="approval.processing || rejection.processing"
        @click="
          approval.post(approve(transaction.id, { query: returnQuery }).url)
        "
      >
        <Check class="size-4" />
        Aprobar
      </Button>
      <Button
        class="h-12 flex-1"
        variant="destructive"
        :disabled="approval.processing"
        @click="showingRejection = true"
      >
        <X class="size-4" />
        Rechazar
      </Button>
    </div>
  </main>
</template>
