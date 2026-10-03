<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import {
  index as participantsIndex,
  store as createParticipant,
  invite as issueInvitation,
  cancel as cancelInvitation,
} from '@/routes/fund/treasury/participants';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import FundPagination from '@/components/FundPagination.vue';
import AppPageHeader from '@/components/AppPageHeader.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
  fundDateTime,
  operationKey,
  type FundPagination as Pagination,
} from '@/lib/fund';

type Invitation = {
  id: number;
  email: string;
  expires_at: string;
  used_at: string | null;
  cancelled_at: string | null;
  status: 'pending' | 'accepted' | 'cancelled' | 'expired' | 'superseded';
  created_at: string;
};
defineProps<{
  participants: Pagination<{
    id: number;
    name: string;
    email: string;
    created_at: string;
  }>;
  invitations: Pagination<Invitation>;
}>();
defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Inicio', href: dashboard() },
      { title: 'Tesorería', href: treasuryIndex() },
      { title: 'Participantes', href: participantsIndex() },
    ],
  },
});

const mode = ref<'invite' | 'direct'>('invite');
const cancellation = useForm({ idempotency_key: operationKey() });
const cancellingId = ref<number | null>(null);
const cancellationError = ref('');
const invitationStatuses = {
  pending: 'En espera',
  accepted: 'Aceptada',
  cancelled: 'Cancelada',
  expired: 'Vencida',
  superseded: 'Reemplazada',
} as const;
const invitation = useForm({ idempotency_key: operationKey(), email: '' });
const direct = useForm({
  idempotency_key: operationKey(),
  name: '',
  email: '',
});

function submitInvitation(): void {
  invitation.post(issueInvitation().url, {
    onSuccess: () => {
      invitation.reset('email');
      invitation.idempotency_key = operationKey();
    },
  });
}

function submitDirect(): void {
  direct.post(createParticipant().url, {
    onSuccess: () => {
      direct.reset('name', 'email');
      direct.idempotency_key = operationKey();
    },
  });
}

function cancelPendingInvitation(invite: Invitation): void {
  cancellingId.value = invite.id;
  cancellationError.value = '';
  cancellation.post(cancelInvitation(invite.id).url, {
    preserveScroll: true,
    onSuccess: () => {
      cancellation.idempotency_key = operationKey();
    },
    onError: (errors) => {
      cancellationError.value =
        errors.invitation ??
        errors.idempotency_key ??
        'No se pudo cancelar la invitación.';
    },
    onFinish: () => {
      cancellingId.value = null;
    },
  });
}
</script>

<template>
  <main class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
    <Head title="Participantes" />
    <AppPageHeader title="Participantes" />

    <div
      class="flex flex-wrap gap-2"
      role="group"
      aria-label="Forma de incorporación"
    >
      <Button
        type="button"
        :variant="mode === 'invite' ? 'default' : 'outline'"
        :aria-pressed="mode === 'invite'"
        @click="mode = 'invite'"
        >Enviar invitación</Button
      >
      <Button
        type="button"
        :variant="mode === 'direct' ? 'default' : 'outline'"
        :aria-pressed="mode === 'direct'"
        @click="mode = 'direct'"
        >Crear cuenta directamente</Button
      >
    </div>

    <Card v-if="mode === 'invite'">
      <CardHeader
        ><CardTitle>Invitar a una persona</CardTitle
        ><CardDescription
          >Enviaremos un enlace personal al correo indicado. Vence en siete días
          y puede utilizarse una sola vez.</CardDescription
        ></CardHeader
      >
      <CardContent
        ><form
          class="flex flex-wrap items-end gap-3"
          @submit.prevent="submitInvitation"
        >
          <div class="flex min-w-52 flex-1 flex-col gap-2">
            <Label for="invite-email">Correo electrónico</Label
            ><Input
              id="invite-email"
              v-model="invitation.email"
              type="email"
              autocomplete="email"
              required
              :aria-invalid="!!invitation.errors.email"
            />
            <p
              v-if="invitation.errors.email"
              role="alert"
              class="text-sm text-destructive"
            >
              {{ invitation.errors.email }}
            </p>
          </div>
          <Button :disabled="invitation.processing">Enviar invitación</Button>
        </form></CardContent
      >
    </Card>

    <Card v-else>
      <CardHeader
        ><CardTitle>Crear cuenta</CardTitle
        ><CardDescription
          >Indica nombre y correo. Enviaremos un enlace para que la persona
          defina su contraseña; no tendrás acceso a ella.</CardDescription
        ></CardHeader
      >
      <CardContent
        ><form
          class="grid gap-4 sm:grid-cols-2"
          @submit.prevent="submitDirect"
        >
          <div class="flex flex-col gap-2">
            <Label for="direct-name">Nombre completo</Label
            ><Input
              id="direct-name"
              v-model="direct.name"
              required
              :aria-invalid="!!direct.errors.name"
            />
            <p
              v-if="direct.errors.name"
              role="alert"
              class="text-sm text-destructive"
            >
              {{ direct.errors.name }}
            </p>
          </div>
          <div class="flex flex-col gap-2">
            <Label for="direct-email">Correo electrónico</Label
            ><Input
              id="direct-email"
              v-model="direct.email"
              type="email"
              autocomplete="email"
              required
              :aria-invalid="!!direct.errors.email"
            />
            <p
              v-if="direct.errors.email"
              role="alert"
              class="text-sm text-destructive"
            >
              {{ direct.errors.email }}
            </p>
          </div>
          <Button :disabled="direct.processing"
            >Crear cuenta y enviar acceso</Button
          >
        </form></CardContent
      >
    </Card>

    <section
      class="flex flex-col gap-3"
      aria-labelledby="registered-heading"
    >
      <h2
        id="registered-heading"
        class="text-xl font-semibold"
      >
        Cuentas registradas
      </h2>
      <p
        v-if="!participants.data.length"
        class="rounded-lg border p-5 text-sm text-muted-foreground"
      >
        Todavía no hay cuentas registradas.
      </p>
      <ul
        v-else
        class="divide-y rounded-lg border"
      >
        <li
          v-for="user in participants.data"
          :key="user.id"
          class="flex flex-wrap justify-between gap-2 p-3 text-sm"
        >
          <span class="font-medium">{{ user.name }}</span
          ><span class="text-muted-foreground">{{ user.email }}</span>
        </li>
      </ul>
      <FundPagination
        :links="participants.links"
        :last-page="participants.last_page"
        label="Páginas de participantes"
      />
    </section>
    <section
      class="flex flex-col gap-3"
      aria-labelledby="invitations-heading"
    >
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2
            id="invitations-heading"
            class="text-xl font-semibold"
          >
            Invitaciones
          </h2>
          <p class="mt-1 text-sm text-muted-foreground">
            Cancela las invitaciones enviadas por error. El enlace dejará de
            funcionar y el registro se conservará.
          </p>
        </div>
        <Badge variant="secondary"
          >{{ invitations.total }}
          {{ invitations.total === 1 ? 'invitación' : 'invitaciones' }}</Badge
        >
      </div>
      <p
        v-if="cancellationError"
        role="alert"
        class="text-sm text-destructive"
      >
        {{ cancellationError }}
      </p>
      <p
        v-if="!invitations.data.length"
        class="rounded-lg border p-5 text-sm text-muted-foreground"
      >
        Aún no hay invitaciones enviadas.
      </p>
      <div
        v-else
        class="overflow-x-auto rounded-lg border"
      >
        <table class="w-full min-w-[760px] text-left text-sm">
          <caption class="sr-only">
            Historial de invitaciones y acciones disponibles
          </caption>
          <thead class="bg-muted/50 text-muted-foreground">
            <tr>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Correo electrónico
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Enviada
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Vencimiento
              </th>
              <th
                scope="col"
                class="px-4 py-3 font-medium"
              >
                Estado
              </th>
              <th
                scope="col"
                class="px-4 py-3 text-right font-medium"
              >
                Acciones
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="invite in invitations.data"
              :key="invite.id"
              class="border-t transition-colors hover:bg-muted/30"
            >
              <th
                scope="row"
                class="px-4 py-4 font-medium"
              >
                <span class="break-all">{{ invite.email }}</span>
              </th>
              <td class="px-4 py-4 text-muted-foreground">
                {{ fundDateTime(invite.created_at) }}
              </td>
              <td class="px-4 py-4 text-muted-foreground">
                {{ fundDateTime(invite.expires_at) }}
              </td>
              <td class="px-4 py-4">
                <Badge
                  :variant="
                    invite.status === 'pending' ? 'secondary' : 'outline'
                  "
                  >{{ invitationStatuses[invite.status] }}</Badge
                >
                <p
                  v-if="invite.cancelled_at"
                  class="mt-1 text-xs text-muted-foreground"
                >
                  {{ fundDateTime(invite.cancelled_at) }}
                </p>
              </td>
              <td class="px-4 py-4 text-right">
                <Button
                  v-if="invite.status === 'pending'"
                  variant="outline"
                  size="sm"
                  :disabled="cancellation.processing"
                  :aria-label="`Cancelar invitación a ${invite.email}`"
                  @click="cancelPendingInvitation(invite)"
                >
                  <Spinner v-if="cancellingId === invite.id" />
                  {{
                    cancellingId === invite.id
                      ? 'Cancelando…'
                      : 'Cancelar invitación'
                  }}
                </Button>
                <span
                  v-else
                  class="text-xs text-muted-foreground"
                  >Sin acciones</span
                >
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <FundPagination
        :links="invitations.links"
        :last-page="invitations.last_page"
        label="Páginas de invitaciones"
      />
    </section>
  </main>
</template>
