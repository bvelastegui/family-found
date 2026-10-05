<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { index as contributionsIndex } from '@/routes/fund/contributions';
import { show } from '@/routes/fund/transactions';
import { usd } from '@/lib/fund';

defineProps<{ transaction: { id: number; amount_cents: number } }>();
defineOptions({ layout: { showHeader: false, showMobileHeader: false } });
</script>

<template>
  <main
    class="mx-auto flex min-h-[70svh] w-full max-w-md flex-col items-center justify-center gap-6 px-6 py-8 text-center"
  >
    <Head title="Comprobante registrado" />
    <div
      class="flex size-20 items-center justify-center rounded-full bg-primary/10 text-primary motion-safe:animate-in motion-safe:zoom-in-75"
    >
      <CircleCheck
        class="size-12"
        aria-hidden="true"
      />
    </div>
    <div class="space-y-3">
      <h1 class="text-2xl font-semibold tracking-tight">
        ¡Comprobante registrado!
      </h1>
      <p class="text-3xl font-semibold tabular-nums">
        {{ usd(transaction.amount_cents) }}
      </p>
      <p class="text-sm text-muted-foreground">
        Recibimos tu comprobante #{{ transaction.id }}. El tesorero lo revisará
        antes de aprobar la transferencia.
      </p>
      <p class="text-sm text-muted-foreground">
        Te avisaremos cuando se apruebe o rechace. Por ahora, el monto aún no se
        ha acreditado al fondo.
      </p>
    </div>
    <div class="flex w-full flex-col gap-3">
      <Button
        class="h-12 rounded-xl"
        as-child
        ><Link :href="contributionsIndex()">Volver a Inicio</Link></Button
      >
      <Button
        variant="outline"
        class="h-12 rounded-xl"
        as-child
        ><Link :href="show(transaction.id)">Ver comprobante</Link></Button
      >
    </div>
  </main>
</template>
