<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
  layout: {
    title: 'Ingresa a tu cuenta',
    description: 'Accede al fondo familiar.',
  },
});

defineProps<{
  status?: string;
  canResetPassword: boolean;
}>();
</script>

<template>
  <Head title="Ingreso" />

  <div
    v-if="status"
    class="mb-4 text-center text-sm font-medium text-green-600"
  >
    {{ status }}
  </div>

  <Form
    v-bind="store.form()"
    :reset-on-success="['password']"
    v-slot="{ errors, processing }"
    class="flex flex-col gap-6"
  >
    <div class="grid gap-6">
      <div class="grid gap-2">
        <Label for="email">Correo electrónico</Label>
        <Input
          id="email"
          type="email"
          class="h-12 text-base!"
          name="email"
          required
          v-focus
          :tabindex="1"
          autocomplete="email"
          placeholder="email@example.com"
        />
        <InputError :message="errors.email" />
      </div>

      <div class="grid gap-2">
        <div class="flex items-center justify-between">
          <Label for="password">Contraseña</Label>
          <TextLink
            v-if="canResetPassword"
            :href="request()"
            class="inline-flex min-h-11 items-center text-sm"
            :tabindex="5"
          >
            ¿Olvidaste tu contraseña?
          </TextLink>
        </div>
        <PasswordInput
          id="password"
          name="password"
          class="h-12 text-base!"
          required
          :tabindex="2"
          autocomplete="current-password"
          placeholder="Contraseña"
        />
        <InputError :message="errors.password" />
      </div>

      <div class="flex items-center justify-between">
        <Label
          for="remember"
          class="flex min-h-11 items-center space-x-3"
        >
          <Checkbox
            id="remember"
            name="remember"
            :tabindex="3"
          />
          <span>Recuérdame</span>
        </Label>
      </div>

      <Button
        type="submit"
        class="h-12 w-full rounded-xl"
        :tabindex="4"
        :disabled="processing"
        data-test="login-button"
      >
        <Spinner v-if="processing" />
        Ingresar
      </Button>
    </div>
  </Form>
</template>
