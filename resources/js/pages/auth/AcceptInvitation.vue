<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';

defineOptions({
  layout: {
    title: 'Unirse al Fondo Familiar',
    description:
      'Completa tu cuenta utilizando la invitación que recibiste por correo.',
  },
});
defineProps<{ email: string; invitationId: number; acceptUrl: string }>();
</script>

<template>
  <div class="flex flex-col gap-6">
    <Head title="Aceptar invitación" />
    <Form
      :action="acceptUrl"
      method="post"
      v-slot="{ errors, processing }"
      class="flex flex-col gap-5"
    >
      <div class="flex flex-col gap-2">
        <Label for="invitation-email">Correo invitado</Label
        ><Input
          id="invitation-email"
          :model-value="email"
          readonly
        />
      </div>
      <div class="flex flex-col gap-2">
        <Label for="invitation-name">Tu nombre completo</Label
        ><Input
          id="invitation-name"
          name="name"
          autocomplete="name"
          required
        /><InputError :message="errors.name" />
      </div>
      <div class="flex flex-col gap-2">
        <Label for="invitation-password">Elige tu contraseña</Label
        ><PasswordInput
          id="invitation-password"
          name="password"
          autocomplete="new-password"
          required
        /><InputError :message="errors.password" />
      </div>
      <div class="flex flex-col gap-2">
        <Label for="invitation-password-confirmation"
          >Confirma tu contraseña</Label
        ><PasswordInput
          id="invitation-password-confirmation"
          name="password_confirmation"
          autocomplete="new-password"
          required
        /><InputError :message="errors.password_confirmation" />
      </div>
      <Button :disabled="processing">Crear mi cuenta</Button>
    </Form>
  </div>
</template>
