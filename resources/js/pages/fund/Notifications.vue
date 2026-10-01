<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import {
  index as notificationsIndex,
  read,
  readAll,
} from '@/routes/fund/notifications';
import {
  store as storeSubscription,
  destroy as destroySubscription,
} from '@/routes/fund/push-subscriptions';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import FundPagination from '@/components/FundPagination.vue';
import { fundDateTime, type FundPagination as Pagination } from '@/lib/fund';
import { canInstall, installApp } from '@/lib/pwa';

type Notice = {
  id: string;
  title: string;
  body: string;
  url: string;
  created_at: string;
  read_at: string | null;
};
const props = defineProps<{
  notifications: Pagination<Notice>;
  vapidPublicKey: string;
  subscribedEndpoints: string[];
}>();
const page = usePage();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Notificaciones', href: notificationsIndex() },
    ],
  },
});

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
const readForm = useForm({});

onMounted(async () => {
  installed.value = window.matchMedia('(display-mode: standalone)').matches;
  installAvailable.value = canInstall();
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

function refreshInstallAvailability(): void {
  installAvailable.value = canInstall();
}

async function install(): Promise<void> {
  await installApp();
  installAvailable.value = canInstall();
}

function applicationServerKey(value: string): ArrayBuffer {
  const padded =
    value.replace(/-/g, '+').replace(/_/g, '/') +
    '='.repeat((4 - (value.length % 4)) % 4);
  const decoded = atob(padded);
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
    subscriptionForm.post(storeSubscription().url, {
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
    removalForm.delete(destroySubscription().url, {
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
  <main class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-8">
    <Head title="Notificaciones" />
    <header>
      <h1 class="text-3xl font-semibold tracking-tight">Notificaciones</h1>
      <p class="mt-1 text-muted-foreground">
        Avisos sobre comprobantes y decisiones de conciliación.
      </p>
    </header>
    <Card v-if="!installed">
      <CardHeader>
        <CardTitle>Instalar Fondo Familiar</CardTitle>
        <CardDescription
          >Abre la aplicación desde la pantalla de inicio de tu dispositivo. En
          iPhone, usa Compartir → Añadir a pantalla de inicio.</CardDescription
        >
      </CardHeader>
      <CardContent>
        <Button
          v-if="installAvailable"
          @click="install"
          >Instalar aplicación</Button
        >
        <p
          v-else
          class="text-sm text-muted-foreground"
        >
          Si tu navegador permite instalar aplicaciones, encontrarás la opción
          en su menú.
        </p>
      </CardContent>
    </Card>
    <Card>
      <CardHeader>
        <CardTitle>Notificaciones en este dispositivo</CardTitle>
        <CardDescription
          >Recibe avisos incluso cuando la aplicación está cerrada. En iPhone,
          instálala en la pantalla de inicio para activar los
          avisos.</CardDescription
        >
      </CardHeader>
      <CardContent class="flex flex-col items-start gap-3">
        <p
          v-if="!pushSupported"
          class="text-sm text-muted-foreground"
        >
          Las notificaciones push requieren un navegador compatible, una
          conexión segura y las claves VAPID configuradas. Los avisos seguirán
          apareciendo aquí.
        </p>
        <Button
          v-else-if="subscribed"
          variant="outline"
          :disabled="removalForm.processing"
          @click="disablePush"
          >Desactivar en este dispositivo</Button
        >
        <Button
          v-else
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
      </CardContent>
    </Card>
    <Card>
      <CardHeader
        class="flex flex-row flex-wrap items-center justify-between gap-3"
      >
        <div>
          <CardTitle>Bandeja de avisos</CardTitle>
          <CardDescription
            >Las notificaciones nuevas se muestran primero.</CardDescription
          >
        </div>
        <Button
          v-if="Number(page.props.unreadNotificationsCount ?? 0) > 0"
          variant="outline"
          size="sm"
          :disabled="readForm.processing"
          @click="readForm.patch(readAll().url)"
          >Marcar todas como leídas</Button
        >
      </CardHeader>
      <CardContent>
        <p
          v-if="!notifications.data.length"
          class="py-6 text-center text-sm text-muted-foreground"
        >
          Todavía no tienes notificaciones.
        </p>
        <ol
          v-else
          class="divide-y"
        >
          <li
            v-for="notice in notifications.data"
            :key="notice.id"
            class="flex flex-wrap items-start justify-between gap-3 py-4"
          >
            <div class="min-w-0 flex-1">
              <p class="font-medium">
                {{ notice.title }}
                <span
                  v-if="!notice.read_at"
                  class="ml-2 text-xs text-primary"
                  >Nueva</span
                >
              </p>
              <p class="text-sm text-muted-foreground">{{ notice.body }}</p>
              <p class="mt-1 text-xs text-muted-foreground">
                {{ fundDateTime(notice.created_at) }}
              </p>
            </div>
            <div class="flex items-center gap-3 text-sm">
              <Button
                v-if="!notice.read_at"
                variant="ghost"
                size="sm"
                as-child
              >
                <Link
                  :href="read(notice.id)"
                  method="patch"
                  as="button"
                  >Marcar leída</Link
                >
              </Button>
              <Link
                :href="notice.url"
                class="font-medium underline underline-offset-4"
                >Ver detalle</Link
              >
            </div>
          </li>
        </ol>
      </CardContent>
    </Card>
    <FundPagination
      :links="notifications.links"
      :last-page="notifications.last_page"
      label="Páginas de notificaciones"
    />
  </main>
</template>
