<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { edit, update } from '@/routes/administration/treasurer';
import { operationKey, type FundPagination as Pagination } from '@/lib/fund';
import { Button } from '@/components/ui/button';
import FundPagination from '@/components/FundPagination.vue';

defineProps<{
  treasurerId: number | null;
  users: Pagination<{ id: number; name: string; email: string }>;
}>();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Administración', href: edit() },
    ],
  },
});
const form = useForm({ idempotency_key: operationKey(), user_id: '' });
</script>

<template>
  <main class="mx-auto flex w-full max-w-xl flex-col gap-6 p-4 md:p-6">
    <Head title="Designar tesorero" />
    <div>
      <h1 class="text-2xl font-semibold">Designar tesorero</h1>
      <p class="text-muted-foreground">
        Solo una persona puede tener la responsabilidad financiera. El cambio
        conserva el historial.
      </p>
    </div>
    <form
      class="grid gap-4 rounded-xl border p-5"
      @submit.prevent="
        form.post(update().url, {
          onSuccess: () => {
            form.idempotency_key = operationKey();
          },
        })
      "
    >
      <label
        for="treasurer"
        class="font-medium"
        >Cuenta existente</label
      ><select
        id="treasurer"
        v-model="form.user_id"
        required
        class="h-9 rounded-md border bg-background px-3"
      >
        <option value="">Selecciona participante</option>
        <option
          v-for="user in users.data"
          :key="user.id"
          :value="user.id"
        >
          {{ user.name }} ({{ user.email }})
          {{ user.id === treasurerId ? '· tesorero actual' : '' }}
        </option>
      </select>
      <p
        v-for="(message, field) in form.errors"
        :key="field"
        class="text-sm text-destructive"
      >
        {{ message }}
      </p>
      <Button :disabled="form.processing">Confirmar designación</Button>
    </form>
    <FundPagination
      :links="users.links"
      :last-page="users.last_page"
      label="Páginas de participantes"
    />
  </main>
</template>
