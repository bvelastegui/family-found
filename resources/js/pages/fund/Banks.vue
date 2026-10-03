<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import {
  index as banksIndex,
  store as saveBank,
  update as updateBank,
} from '@/routes/fund/banks';
import FundPagination from '@/components/FundPagination.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { operationKey, type FundPagination as Pagination } from '@/lib/fund';

type Bank = { id: number; name: string; active: boolean };
defineProps<{ banks: Pagination<Bank> }>();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Tesorería', href: treasuryIndex() },
      { title: 'Bancos', href: banksIndex() },
    ],
  },
});
const form = useForm({
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
  <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
    <Head title="Bancos" />
    <AppPageHeader title="Bancos" />
    <Card
      ><CardHeader
        ><CardTitle>Agregar banco</CardTitle
        ><CardDescription
          >Los usuarios seleccionarán un banco activo al registrar su
          transferencia.</CardDescription
        ></CardHeader
      ><CardContent
        ><form
          class="flex flex-wrap items-end gap-3"
          @submit.prevent="
            form.post(saveBank().url, {
              onSuccess: () => {
                form.reset('name');
                form.idempotency_key = operationKey();
              },
            })
          "
        >
          <div class="flex min-w-48 flex-1 flex-col gap-2">
            <Label for="bank-name">Nombre del banco</Label
            ><Input
              id="bank-name"
              v-model="form.name"
              required
              :aria-invalid="!!form.errors.name"
            />
            <p
              v-if="form.errors.name"
              class="text-sm text-destructive"
            >
              {{ form.errors.name }}
            </p>
          </div>
          <Button :disabled="form.processing">Añadir banco</Button>
        </form></CardContent
      ></Card
    >
    <Card
      ><CardHeader><CardTitle>Catálogo</CardTitle></CardHeader
      ><CardContent
        ><p
          v-if="!banks.data.length"
          class="text-sm text-muted-foreground"
        >
          No hay bancos registrados.
        </p>
        <ul
          v-else
          class="divide-y"
        >
          <li
            v-for="item in banks.data"
            :key="item.id"
            class="flex flex-wrap items-center justify-between gap-2 py-3"
          >
            <div>
              <p class="font-medium">{{ item.name }}</p>
              <p class="text-xs text-muted-foreground">
                {{
                  item.active
                    ? 'Disponible para nuevos comprobantes'
                    : 'Solo para consultar comprobantes anteriores'
                }}
              </p>
            </div>
            <Button
              size="sm"
              variant="outline"
              :disabled="updating.processing"
              @click="toggleBank(item)"
              >{{ item.active ? 'Desactivar' : 'Activar' }}</Button
            >
          </li>
        </ul>
        <p
          v-if="updating.hasErrors"
          role="alert"
          class="mt-3 text-sm text-destructive"
        >
          No se pudo actualizar el banco. Vuelve a intentarlo.
        </p></CardContent
      ></Card
    ><FundPagination
      :links="banks.links"
      :last-page="banks.last_page"
      label="Páginas del catálogo de bancos"
    />
  </main>
</template>
