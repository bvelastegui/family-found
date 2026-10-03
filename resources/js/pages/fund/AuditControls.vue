<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck, CircleAlert } from '@lucide/vue';
import { dashboard } from '@/routes';
import { index as auditIndex, controls } from '@/routes/fund/audit';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import FundPagination from '@/components/FundPagination.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
import { auditRecordRoute } from '@/lib/audit';
import {
  fundDateTime,
  usd,
  type FundPagination as Pagination,
} from '@/lib/fund';
type Check = { key: string; title: string; description: string; count: number };
type Finding = {
  check_key: string;
  entity_type: string;
  entity_id: number;
  reference: string;
  created_at: string;
  expected_cents: number | null;
  actual_cents: number | null;
};
const props = defineProps<{
  checks: Check[];
  findings: Pagination<Finding>;
  checkedAt: string;
  selectedCheck: string;
}>();
const page = usePage();
const titles = computed(() =>
  Object.fromEntries(props.checks.map((check) => [check.key, check.title])),
);
const total = computed(() =>
  props.checks.reduce((sum, check) => sum + check.count, 0),
);
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Auditoría', href: auditIndex() },
      { title: 'Controles', href: controls() },
    ],
  },
});
</script>
<template>
  <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-8">
    <Head title="Controles de auditoría" />
    <header class="flex flex-wrap items-center justify-between gap-4">
      <AppPageHeader title="Controles del fondo">
        <template #actions>
          <Button
            class="hidden sm:inline-flex"
            as-child
            variant="outline"
          >
            <Link :href="controls()">Volver a comprobar</Link>
          </Button>
        </template>
      </AppPageHeader>
    </header>
    <div class="overflow-x-auto rounded-lg border">
      <table class="w-full min-w-lg text-left text-sm">
        <caption class="sr-only">
          Resultado de los controles automáticos
        </caption>
        <thead class="bg-muted/70 text-muted-foreground">
          <tr>
            <th
              scope="col"
              class="px-4 py-3 font-medium"
            >
              Control
            </th>
            <th
              scope="col"
              class="px-4 py-3 font-medium"
            >
              Resultado
            </th>
            <th
              scope="col"
              class="px-4 py-3 text-right font-medium"
            >
              Hallazgos
            </th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr
            v-for="check in checks"
            :key="check.key"
            class="hover:bg-muted/30"
          >
            <th
              scope="row"
              class="px-4 py-3 font-medium"
            >
              {{ check.title
              }}<span
                class="mt-1 block text-xs font-normal text-muted-foreground"
                >{{ check.description }}</span
              >
            </th>
            <td class="px-4 py-3">
              <Badge variant="outline"
                ><CircleAlert
                  v-if="check.count"
                  aria-hidden="true"
                /><CircleCheck
                  v-else
                  aria-hidden="true"
                />{{ check.count ? 'Revisar' : 'Sin inconsistencias' }}</Badge
              >
            </td>
            <td class="px-4 py-3 text-right">
              <Link
                :href="controls({ query: { check: check.key } })"
                class="font-medium tabular-nums underline underline-offset-4"
                >{{ check.count }}</Link
              >
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <section
      class="flex flex-col gap-4"
      aria-labelledby="findings-title"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2
          id="findings-title"
          class="text-lg font-semibold"
        >
          {{ selectedCheck ? titles[selectedCheck] : 'Hallazgos para revisar' }}
        </h2>
        <Link
          v-if="selectedCheck"
          :href="controls()"
          class="text-sm underline underline-offset-4"
          >Mostrar todos</Link
        >
      </div>
      <div class="overflow-x-auto rounded-lg border">
        <table class="w-full min-w-[700px] text-left text-sm">
          <caption class="sr-only">
            Inconsistencias detectadas en el fondo
          </caption>
          <thead class="bg-muted/70 text-muted-foreground">
            <tr>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Control
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Registro
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Comparación
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Detalle
              </th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr
              v-for="(finding, index) in findings.data"
              :key="`${finding.check_key}-${finding.entity_id}-${index}`"
              class="hover:bg-muted/30"
            >
              <td class="px-4 py-3">{{ titles[finding.check_key] }}</td>
              <td class="px-4 py-3">
                <span class="font-medium">{{ finding.reference }}</span
                ><span class="block text-xs text-muted-foreground">{{
                  fundDateTime(finding.created_at)
                }}</span>
              </td>
              <td class="px-4 py-3 text-sm">
                <template
                  v-if="
                    finding.expected_cents !== null &&
                    finding.actual_cents !== null
                  "
                  >{{ usd(finding.expected_cents) }} frente a
                  {{ usd(finding.actual_cents) }}</template
                ><span
                  v-else
                  class="text-muted-foreground"
                  >Consulta el registro y sus asientos.</span
                >
              </td>
              <td class="px-4 py-3 text-right">
                <Link
                  v-if="
                    auditRecordRoute(finding.entity_type, finding.entity_id)
                  "
                  :href="
                    auditRecordRoute(
                      finding.entity_type,
                      finding.entity_id,
                      page.url,
                    )!
                  "
                  class="font-medium underline underline-offset-4"
                  >Examinar</Link
                ><span v-else>—</span>
              </td>
            </tr>
            <tr v-if="!findings.data.length">
              <td
                colspan="4"
                class="p-8 text-center text-muted-foreground"
              >
                No se detectaron inconsistencias en
                {{
                  selectedCheck ? 'este control' : 'los controles ejecutados'
                }}.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <FundPagination
        :links="findings.links"
        :last-page="findings.last_page"
        label="Páginas de hallazgos"
      />
    </section>
    <p class="text-xs text-muted-foreground">
      Los controles consultan el historial completo y comprueban la existencia
      de los archivos. La revisión no modifica operaciones ni equivale a una
      conciliación con el extracto bancario.
    </p>
  </main>
</template>
