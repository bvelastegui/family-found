<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{ status: string }>();
const label = computed(
  () =>
    ({
      pending: 'En revisión',
      approved: 'Aprobada',
      rejected: 'Rechazada',
      paid: 'Pagado',
      unpaid: 'Pendiente',
      upcoming: 'Próximo',
      unconfigured: 'Sin cuota',
      before_start: 'Antes del inicio',
      reserved: 'Reservado',
      disbursed: 'Desembolsado',
      cancelled: 'Cancelado',
      superseded: 'Sustituido',
      locked: 'Fijada por una transacción',
      unavailable: 'Mes no editable',
    })[props.status] ?? props.status,
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
  <Badge :variant="variant">{{ label }}</Badge>
</template>
