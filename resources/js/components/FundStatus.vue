<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { CircleCheck, CircleX, Clock, Minus } from '@lucide/vue';

const props = withDefaults(
  defineProps<{ status: string; subtle?: boolean; label?: string }>(),
  { subtle: false },
);
const icon = computed(() =>
  props.status === 'paid' || props.status === 'approved'
    ? CircleCheck
    : props.status === 'rejected'
      ? CircleX
      : props.status === 'not_applicable'
        ? Minus
        : Clock,
);
const label = computed(
  () =>
    props.label ??
    {
      pending: 'Pendiente',
      approved: 'Aprobada',
      rejected: 'Rechazada',
      paid: 'Pagado',
      unpaid: 'Pendiente',
      not_applicable: 'No aplica',
      upcoming: 'Próximo',
      unconfigured: 'Sin cuota',
      before_start: 'Antes del inicio',
      reserved: 'Reservado',
      disbursed: 'Desembolsado',
      cancelled: 'Cancelado',
      superseded: 'Sustituido',
      locked: 'Fijada por una transacción',
      unavailable: 'Mes no editable',
    }[props.status] ??
    props.status,
);
const variant = computed(() =>
  props.status === 'rejected'
    ? 'destructive'
    : props.status === 'approved' || props.status === 'paid'
      ? 'default'
      : props.status === 'unconfigured' ||
          props.status === 'before_start' ||
          props.status === 'superseded'
        ? 'outline'
        : 'secondary',
);
</script>

<template>
  <Badge :variant="subtle ? 'outline' : variant"
    ><component
      :is="icon"
      v-if="subtle"
      aria-hidden="true"
    />{{ label }}</Badge
  >
</template>
