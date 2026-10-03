<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import DeviceNotificationsSettings from '@/components/DeviceNotificationsSettings.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Perfil',
        href: edit(),
      },
    ],
  },
});

const page = usePage();
defineProps<{ vapidPublicKey: string; subscribedEndpoints: string[] }>();
const user = computed(() => page.props.auth.user);
</script>

<template>
  <Head title="Perfil" />

  <h1 class="sr-only">Perfil</h1>

  <div class="flex flex-col space-y-6">
    <Heading
      variant="small"
      title="Perfil"
      description="Actualiza tu nombre y correo electrónico"
    />

    <Form
      v-bind="ProfileController.update.form()"
      class="space-y-6"
      v-slot="{ errors, processing }"
    >
      <div class="grid gap-2">
        <Label for="name">Nombre</Label>
        <Input
          id="name"
          class="mt-1 block h-12 w-full text-base!"
          name="name"
          :default-value="user.name"
          required
          autocomplete="name"
          placeholder="Nombre completo"
        />
        <InputError
          class="mt-2"
          :message="errors.name"
        />
      </div>

      <div class="grid gap-2">
        <Label for="email">Correo electrónico</Label>
        <Input
          id="email"
          type="email"
          class="mt-1 block h-12 w-full text-base!"
          name="email"
          :default-value="user.email"
          required
          autocomplete="username"
          placeholder="Correo electrónico"
        />
        <InputError
          class="mt-2"
          :message="errors.email"
        />
      </div>

      <div class="flex items-center gap-4">
        <Button
          class="min-h-11 w-full sm:w-auto"
          :disabled="processing"
          data-test="update-profile-button"
          >Guardar</Button
        >
      </div>
    </Form>
    <DeviceNotificationsSettings
      :vapid-public-key="vapidPublicKey"
      :subscribed-endpoints="subscribedEndpoints"
    />
  </div>
</template>
