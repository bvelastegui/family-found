<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Check, Download, EllipsisVertical, X } from '@lucide/vue';
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
  showHeader: false,
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
const showingApproval = ref(false);
const evidenceZoom = ref(1);
const { setOpenMobile } = useSidebar();
const isPreviewable = computed(() =>
  ['application/pdf', 'image/jpeg', 'image/png'].includes(props.evidence.mime),
);
const evidencePreviewUrl = computed(
  () => evidencePreview(props.evidence.id).url,
);
const rejectionReason = computed(() => {
  const event = [...props.events]
    .reverse()
    .find((item) => item.event === 'transaction.rejected');
  return event ? reason(event) : null;
});
const participantStatus = computed(() => {
  if (props.transaction.superseded_by_id) {
    return {
      title: 'Transferencia corregida',
      description: 'Este comprobante fue sustituido por un nuevo registro.',
    };
  }
  if (props.transaction.status === 'approved') {
    return {
      title: 'Transferencia aprobada',
      description:
        'Tu pago fue validado y aplicado a los aportes o cuotas seleccionados.',
    };
  }
  if (props.transaction.status === 'rejected') {
    return {
      title: 'Transferencia rechazada',
      description:
        'Este comprobante no se acreditó. Consulta el motivo antes de registrar otro.',
    };
  }
  return {
    title: 'Esperando aprobación',
    description:
      'Recibimos tu comprobante. El tesorero lo revisará; todavía no se ha acreditado al fondo.',
  };
});

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
  <main
    class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-3 sm:gap-6 sm:p-4 md:p-8"
  >
    <Head :title="`Transferencia #${transaction.id}`" />
    <p
      class="sr-only"
      aria-live="polite"
    >
      {{
        isTreasurer && transaction.status === 'pending'
          ? 'Acciones disponibles: volver, aprobar o rechazar.'
          : `Estado: ${transaction.status}`
      }}
    </p>
    <header
      class="sticky top-0 z-20 -mx-3 -mt-3 flex items-center gap-1 border-b bg-background px-2 py-2 sm:-mx-4 sm:-mt-4 md:-mx-8 md:-mt-8"
    >
      <Link
        :href="returnTo ?? transactionsIndex().url"
        aria-label="Volver"
        class="inline-flex size-10 shrink-0 items-center justify-center rounded-md hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring sm:size-11"
        @click="setOpenMobile(false)"
      >
        <ArrowLeft class="size-5" />
      </Link>
      <div class="min-w-0 flex-1">
        <h1 class="text-sm font-semibold sm:text-lg">
          Comprobante #{{ transaction.id }} |
          {{ usd(transaction.amount_cents) }}
        </h1>
      </div>
      <div
        v-if="isTreasurer && transaction.status === 'pending'"
        class="flex shrink-0 items-center gap-1"
      >
        <form
          class="shrink-0"
          @submit.prevent="showingApproval = true"
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
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button
            variant="ghost"
            size="icon"
            class="size-10 shrink-0"
            aria-label="Más acciones"
          >
            <EllipsisVertical class="size-5" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem as-child>
            <a :href="evidenceShow(transaction.evidence_id).url">
              <Download class="size-4" />
              Descargar comprobante
            </a>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </header>
    <section
      v-if="!isTreasurer"
      class="space-y-3"
      aria-label="Estado de tu transferencia"
    >
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ participantStatus.title }}</h2>
        <FundStatus
          :status="
            transaction.superseded_by_id ? 'superseded' : transaction.status
          "
          subtle
        />
      </div>
      <p class="text-sm text-muted-foreground">
        {{ participantStatus.description }}
      </p>
      <p
        v-if="transaction.status === 'rejected' && rejectionReason"
        class="border-s-2 border-destructive ps-3 text-sm break-words whitespace-pre-wrap"
      >
        {{ rejectionReason }}
      </p>
    </section>
    <p
      v-if="approval.hasErrors"
      role="alert"
      class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
    >
      La aprobación no se completó. Comprueba que las asignaciones sigan siendo
      válidas.
    </p>
    <section
      aria-label="Evidencia de la transferencia"
      class="space-y-2"
      :class="!isTreasurer ? 'order-last' : ''"
    >
      <div
        v-if="isTreasurer"
        class="space-y-3"
      >
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-base font-semibold">Comprobante para conciliar</h2>
          <FundStatus
            :status="
              transaction.superseded_by_id ? 'superseded' : transaction.status
            "
            subtle
          />
        </div>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          <div>
            <dt class="text-xs text-muted-foreground">Monto registrado</dt>
            <dd class="font-semibold tabular-nums">
              {{ usd(transaction.amount_cents) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">
              Fecha de transferencia
            </dt>
            <dd class="font-medium">
              {{ fundDate(transaction.transaction_date) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Banco de origen</dt>
            <dd class="font-medium break-words">{{ transaction.bank_name }}</dd>
          </div>
          <div>
            <dt class="text-xs text-muted-foreground">Referencia</dt>
            <dd class="font-medium break-all">{{ transaction.reference }}</dd>
          </div>
        </dl>
        <p class="text-xs text-muted-foreground">
          Contrasta estos datos con el comprobante y el movimiento recibido en
          tu estado de cuenta.
        </p>
      </div>
      <h2
        v-else
        class="text-base font-semibold"
      >
        Tu comprobante
      </h2>
      <div
        v-if="isPreviewable"
        class="relative"
      >
        <div
          class="overflow-auto rounded-md border bg-muted/30"
          :class="isTreasurer ? 'max-h-[80dvh]' : ''"
        >
          <img
            v-if="evidence.mime.startsWith('image/')"
            :src="evidencePreviewUrl"
            :alt="`Evidencia de la transferencia #${transaction.id}`"
            class="mx-auto block h-auto object-contain"
            :class="
              isTreasurer ? 'max-w-none' : 'max-h-[50vh] w-auto max-w-full'
            "
            :style="
              isTreasurer ? { width: `${evidenceZoom * 100}%` } : undefined
            "
            loading="eager"
            fetchpriority="high"
          />
          <iframe
            v-else
            :src="evidencePreviewUrl"
            :title="`Evidencia de la transferencia #${transaction.id}`"
            class="h-[78vh] min-h-[32rem] w-full"
          />
        </div>
        <div
          v-if="
            isTreasurer && isPreviewable && evidence.mime.startsWith('image/')
          "
          class="absolute right-3 bottom-3 flex items-center gap-1 rounded-full border bg-background/95 p-1 shadow-sm"
          role="group"
          aria-label="Ampliación del comprobante"
        >
          <Button
            type="button"
            variant="ghost"
            class="size-11 rounded-full"
            :disabled="evidenceZoom <= 1"
            aria-label="Reducir comprobante"
            @click="evidenceZoom = Math.max(1, evidenceZoom - 0.5)"
            >−</Button
          >
          <span
            class="px-1 text-xs tabular-nums"
            aria-live="polite"
            >{{ evidenceZoom * 100 }} %</span
          >
          <Button
            type="button"
            variant="ghost"
            class="size-11 rounded-full"
            :disabled="evidenceZoom >= 4"
            aria-label="Ampliar comprobante"
            @click="evidenceZoom = Math.min(4, evidenceZoom + 0.5)"
            >+</Button
          >
        </div>
      </div>
      <p
        v-else
        class="rounded-md border px-3 py-2 text-sm text-muted-foreground"
      >
        Este tipo de archivo no admite vista previa.
      </p>
    </section>
    <section
      v-if="!isTreasurer"
      class="space-y-2 border-t pt-3"
    >
      <div>
        <h2 class="text-base font-semibold">Detalles de la transacción</h2>
        <p
          v-if="isTreasurer"
          class="text-xs text-muted-foreground"
        >
          Solo se contabiliza al aprobar la transferencia.
        </p>
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
          <p class="font-medium">
            {{ fundDate(transaction.transaction_date) }}
          </p>
        </div>
        <div>
          <p class="text-muted-foreground">Fecha de registro</p>
          <p class="font-medium">{{ fundDateTime(transaction.created_at) }}</p>
        </div>
        <div>
          <p class="mb-1 text-muted-foreground">Estado</p>
          <FundStatus :status="transaction.status" />
        </div>
      </div>
    </section>
    <section
      v-if="!isTreasurer || transaction.status !== 'pending'"
      class="space-y-2 border-t pt-3"
    >
      <h2 class="text-base font-semibold">
        {{ isTreasurer ? 'Asignación del pago' : 'Destino de tu pago' }}
      </h2>
      <ul class="grid gap-3 text-sm sm:grid-cols-2">
        <li
          v-for="allocation in allocations"
          :key="allocation.id"
          class="flex min-w-0 items-start justify-between gap-3"
        >
          <div>
            <p class="text-muted-foreground">
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
          <span class="shrink-0 font-medium tabular-nums">{{
            usd(allocation.amount_cents)
          }}</span>
        </li>
      </ul>
    </section>
    <Dialog v-model:open="showingApproval">
      <DialogContent
        class="inset-0 flex h-dvh max-h-dvh w-full max-w-none translate-x-0 translate-y-0 flex-col overflow-y-auto rounded-none border-0 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-[max(1rem,env(safe-area-inset-bottom))] sm:max-w-none"
      >
        <DialogHeader>
          <DialogTitle>Aprobar transferencia</DialogTitle>
          <DialogDescription
            >Confirma que recibiste {{ usd(transaction.amount_cents) }} en la
            cuenta del fondo y que el banco, la referencia y la fecha coinciden
            con el comprobante y el estado de cuenta.</DialogDescription
          >
        </DialogHeader>
        <p class="text-sm text-muted-foreground">
          Al aprobar, se registra el ingreso en el fondo y se acreditan los
          aportes o cuotas que se indican a continuación. No se realiza una
          transferencia bancaria.
        </p>
        <section
          class="space-y-2"
          aria-label="Asignación del pago a aprobar"
        >
          <h2 class="text-sm font-semibold">Asignación del pago</h2>
          <ul class="divide-y text-sm">
            <li
              v-for="allocation in allocations"
              :key="allocation.id"
              class="flex items-start justify-between gap-3 py-2"
            >
              <div>
                <p>
                  {{
                    allocation.contribution_period_id
                      ? `Aporte de ${allocation.month ? fundMonth(allocation.month.slice(0, 7)) : ''}`
                      : `Préstamo #${allocation.loan_id}, cuota ${allocation.installment_number}`
                  }}
                </p>
                <p
                  v-if="allocation.loan_installment_id"
                  class="text-xs text-muted-foreground"
                >
                  Capital {{ usd(allocation.capital_cents) }} · Interés
                  {{ usd(allocation.interest_cents) }}
                </p>
              </div>
              <span class="shrink-0 font-medium tabular-nums">{{
                usd(allocation.amount_cents)
              }}</span>
            </li>
          </ul>
        </section>
        <p
          v-if="approval.hasErrors"
          role="alert"
          class="text-sm text-destructive"
        >
          No se pudo aprobar la transferencia. Revisa las asignaciones e intenta
          nuevamente.
        </p>
        <DialogFooter class="mt-auto gap-2 border-t pt-4">
          <Button
            type="button"
            variant="outline"
            class="min-h-11"
            :disabled="approval.processing"
            @click="showingApproval = false"
            >Cancelar</Button
          >
          <Button
            type="button"
            class="min-h-11"
            :disabled="approval.processing || rejection.processing"
            @click="
              approval.post(
                approve(transaction.id, { query: returnQuery }).url,
                {
                  onSuccess: () => {
                    showingApproval = false;
                  },
                },
              )
            "
            >{{
              approval.processing ? 'Aprobando…' : 'Confirmar aprobación'
            }}</Button
          >
        </DialogFooter>
      </DialogContent>
    </Dialog>
    <Dialog v-model:open="showingRejection">
      <DialogContent
        class="inset-0 flex h-dvh max-h-dvh w-full max-w-none translate-x-0 translate-y-0 flex-col overflow-y-auto rounded-none border-0 px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-[max(1rem,env(safe-area-inset-bottom))] sm:max-w-none"
      >
        <DialogHeader>
          <DialogTitle>Rechazar comprobante</DialogTitle>
          <DialogDescription>
            Indica el motivo del rechazo. No se registrará ningún movimiento en
            el fondo.
          </DialogDescription>
        </DialogHeader>
        <form
          id="rejection-form"
          class="flex w-full flex-1 flex-col gap-4"
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
          <DialogFooter
            class="mt-auto flex-col-reverse gap-2 border-t pt-4 sm:flex-row"
          >
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
          La corrección conserva el comprobante original y contabiliza la
          reversión y el reemplazo juntos.
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
    <section class="space-y-5 border-t pt-3">
      <div>
        <h2 class="text-base font-semibold">Historial de decisiones</h2>
        <p class="text-xs text-muted-foreground">
          Quién intervino y qué sucedió con este comprobante. Horarios de
          Ecuador.
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
  </main>
</template>
