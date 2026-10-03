<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { canInstall, installApp } from '@/lib/pwa';
import { store, destroy } from '@/routes/fund/push-subscriptions';

const props = defineProps<{
  vapidPublicKey: string;
  subscribedEndpoints: string[];
}>();
const pushSupported = ref(false);
const subscribed = ref(false);
const pushError = ref('');
const installAvailable = ref(false);
const installed = ref(false);
const subscriptionForm = useForm({
  endpoint: '',
  keys: { p256dh: '', auth: '' },
  content_encoding: 'aes128gcm',
});
const removalForm = useForm({ endpoint: '' });

function refreshInstallAvailability(): void {
  installAvailable.value = canInstall();
}
onMounted(async () => {
  installed.value = window.matchMedia('(display-mode: standalone)').matches;
  refreshInstallAvailability();
  window.addEventListener('fund-install-available', refreshInstallAvailability);
  pushSupported.value =
    window.isSecureContext &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window &&
    !!props.vapidPublicKey;
  if (pushSupported.value) {
    try {
      const registration = await navigator.serviceWorker.ready;
      const subscription = await registration.pushManager.getSubscription();
      subscribed.value =
        !!subscription &&
        props.subscribedEndpoints.includes(subscription.endpoint);
    } catch {
      pushError.value =
        'No se pudo verificar la suscripción de este dispositivo.';
    }
  }
});
onUnmounted(() =>
  window.removeEventListener(
    'fund-install-available',
    refreshInstallAvailability,
  ),
);
async function install(): Promise<void> {
  await installApp();
  refreshInstallAvailability();
}
function applicationServerKey(value: string): ArrayBuffer {
  const decoded = atob(
    value.replace(/-/g, '+').replace(/_/g, '/') +
      '='.repeat((4 - (value.length % 4)) % 4),
  );
  const key = new ArrayBuffer(decoded.length);
  const bytes = new Uint8Array(key);
  for (let index = 0; index < decoded.length; index++) {
    bytes[index] = decoded.charCodeAt(index);
  }
  return key;
}
async function enablePush(): Promise<void> {
  pushError.value = '';
  try {
    if ((await Notification.requestPermission()) !== 'granted') {
      pushError.value =
        'Debes permitir las notificaciones en el navegador para activarlas.';
      return;
    }
    const registration = await navigator.serviceWorker.ready;
    let subscription = await registration.pushManager.getSubscription();
    if (
      subscription &&
      !props.subscribedEndpoints.includes(subscription.endpoint)
    ) {
      await subscription.unsubscribe();
      subscription = null;
    }
    subscription ??= await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: applicationServerKey(props.vapidPublicKey),
    });
    const keys = subscription.toJSON().keys;
    if (!keys?.p256dh || !keys.auth) {
      throw new Error('La suscripción no incluye claves de cifrado.');
    }
    subscriptionForm.endpoint = subscription.endpoint;
    subscriptionForm.keys = { p256dh: keys.p256dh, auth: keys.auth };
    subscriptionForm.post(store().url, {
      preserveScroll: true,
      onSuccess: () => {
        subscribed.value = true;
      },
      onError: () => {
        pushError.value =
          'No se pudo guardar la suscripción de este dispositivo.';
      },
    });
  } catch {
    pushError.value =
      'No se pudieron activar las notificaciones. Comprueba los permisos y vuelve a intentarlo.';
  }
}
async function disablePush(): Promise<void> {
  pushError.value = '';
  try {
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscribed.value = false;
      return;
    }
    removalForm.endpoint = subscription.endpoint;
    removalForm.delete(destroy().url, {
      preserveScroll: true,
      onSuccess: () => {
        subscribed.value = false;
        void subscription.unsubscribe();
      },
      onError: () => {
        pushError.value =
          'No se pudo desactivar la suscripción de este dispositivo.';
      },
    });
  } catch {
    pushError.value =
      'No se pudo desactivar la suscripción de este dispositivo.';
  }
}
</script>

<template>
  <section
    class="flex flex-col gap-4 border-t pt-6"
    aria-label="Configuración del dispositivo"
  >
    <h2 class="text-lg font-semibold">Notificaciones del dispositivo</h2>
    <p class="text-sm text-muted-foreground">
      Recibe avisos incluso con la aplicación cerrada. En iPhone, instala
      primero la aplicación en la pantalla de inicio.
    </p>
    <p
      v-if="!pushSupported"
      class="text-sm text-muted-foreground"
    >
      Este dispositivo no permite activar los avisos. Puedes consultar tus
      notificaciones dentro de la aplicación.
    </p>
    <Button
      v-else-if="subscribed"
      variant="outline"
      class="min-h-11 self-start"
      :disabled="removalForm.processing"
      @click="disablePush"
      >Desactivar en este dispositivo</Button
    >
    <Button
      v-else
      class="min-h-11 self-start"
      :disabled="subscriptionForm.processing"
      @click="enablePush"
      >Activar notificaciones</Button
    >
    <p
      v-if="pushError"
      role="alert"
      class="text-sm text-destructive"
    >
      {{ pushError }}
    </p>
    <div
      v-if="!installed"
      class="flex flex-col gap-3 border-t pt-4"
    >
      <h2 class="text-lg font-semibold">Instalar aplicación</h2>
      <Button
        v-if="installAvailable"
        class="min-h-11 self-start"
        @click="install"
        >Instalar Fondo Familiar</Button
      >
      <p
        v-else
        class="text-sm text-muted-foreground"
      >
        Busca la opción de instalación en el menú de tu navegador. En iPhone,
        usa Compartir → Añadir a pantalla de inicio.
      </p>
    </div>
  </section>
</template>
