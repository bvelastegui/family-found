<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import { index as participantsIndex, store as createParticipant, invite as issueInvitation } from '@/routes/fund/treasury/participants';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import FundPagination from '@/components/FundPagination.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fundDateTime, operationKey, type FundPagination as Pagination } from '@/lib/fund';

type Invitation = { id: number; email: string; expires_at: string; used_at: string | null; created_at: string };
defineProps<{
    participants: Pagination<{ id: number; name: string; email: string; created_at: string }>;
    invitations: Pagination<Invitation>;
}>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Inicio', href: dashboard() }, { title: 'Tesorería', href: treasuryIndex() }, { title: 'Participantes', href: participantsIndex() }] } });

const mode = ref<'invite' | 'direct'>('invite');
const invitation = useForm({ idempotency_key: operationKey(), email: '' });
const direct = useForm({ idempotency_key: operationKey(), name: '', email: '' });

function submitInvitation(): void {
    invitation.post(issueInvitation().url, { onSuccess: () => { invitation.reset('email'); invitation.idempotency_key = operationKey(); } });
}

function submitDirect(): void {
    direct.post(createParticipant().url, { onSuccess: () => { direct.reset('name', 'email'); direct.idempotency_key = operationKey(); } });
}

function invitationStatus(invite: Invitation): string {
    if (invite.used_at) return 'Aceptada';
    return new Date(invite.expires_at).getTime() < Date.now() ? 'Vencida' : 'En espera';
}
</script>

<template>
    <main class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
        <Head title="Participantes" />
        <header><p class="text-sm text-muted-foreground">Tesorería</p><h1 class="text-3xl font-semibold tracking-tight">Participantes</h1><p class="mt-1 text-muted-foreground">Invita por correo o crea una cuenta directamente. Solo su propietario establecerá la contraseña.</p></header>

        <div class="flex flex-wrap gap-2" role="group" aria-label="Forma de incorporación">
            <Button type="button" :variant="mode === 'invite' ? 'default' : 'outline'" :aria-pressed="mode === 'invite'" @click="mode = 'invite'">Enviar invitación</Button>
            <Button type="button" :variant="mode === 'direct' ? 'default' : 'outline'" :aria-pressed="mode === 'direct'" @click="mode = 'direct'">Crear cuenta directamente</Button>
        </div>

        <Card v-if="mode === 'invite'">
            <CardHeader><CardTitle>Invitar a una persona</CardTitle><CardDescription>Enviaremos un enlace personal al correo indicado. Vence en siete días y puede utilizarse una sola vez.</CardDescription></CardHeader>
            <CardContent><form class="flex flex-wrap items-end gap-3" @submit.prevent="submitInvitation"><div class="flex min-w-52 flex-1 flex-col gap-2"><Label for="invite-email">Correo electrónico</Label><Input id="invite-email" v-model="invitation.email" type="email" autocomplete="email" required :aria-invalid="!!invitation.errors.email" /><p v-if="invitation.errors.email" role="alert" class="text-sm text-destructive">{{ invitation.errors.email }}</p></div><Button :disabled="invitation.processing">Enviar invitación</Button></form></CardContent>
        </Card>

        <Card v-else>
            <CardHeader><CardTitle>Crear cuenta</CardTitle><CardDescription>Indica nombre y correo. Enviaremos un enlace para que la persona defina su contraseña; no tendrás acceso a ella.</CardDescription></CardHeader>
            <CardContent><form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitDirect"><div class="flex flex-col gap-2"><Label for="direct-name">Nombre completo</Label><Input id="direct-name" v-model="direct.name" required :aria-invalid="!!direct.errors.name" /><p v-if="direct.errors.name" role="alert" class="text-sm text-destructive">{{ direct.errors.name }}</p></div><div class="flex flex-col gap-2"><Label for="direct-email">Correo electrónico</Label><Input id="direct-email" v-model="direct.email" type="email" autocomplete="email" required :aria-invalid="!!direct.errors.email" /><p v-if="direct.errors.email" role="alert" class="text-sm text-destructive">{{ direct.errors.email }}</p></div><Button :disabled="direct.processing">Crear cuenta y enviar acceso</Button></form></CardContent>
        </Card>

        <section class="flex flex-col gap-3" aria-labelledby="registered-heading"><h2 id="registered-heading" class="text-xl font-semibold">Cuentas registradas</h2><p v-if="!participants.data.length" class="rounded-lg border p-5 text-sm text-muted-foreground">Todavía no hay cuentas registradas.</p><ul v-else class="divide-y rounded-lg border"><li v-for="user in participants.data" :key="user.id" class="flex flex-wrap justify-between gap-2 p-3 text-sm"><span class="font-medium">{{ user.name }}</span><span class="text-muted-foreground">{{ user.email }}</span></li></ul><FundPagination :links="participants.links" :last-page="participants.last_page" label="Páginas de participantes" /></section>
        <section class="flex flex-col gap-3" aria-labelledby="invitations-heading"><h2 id="invitations-heading" class="text-xl font-semibold">Invitaciones</h2><p v-if="!invitations.data.length" class="rounded-lg border p-5 text-sm text-muted-foreground">Aún no hay invitaciones enviadas.</p><ul v-else class="divide-y rounded-lg border"><li v-for="invite in invitations.data" :key="invite.id" class="flex flex-wrap items-center justify-between gap-2 p-3 text-sm"><div><p class="font-medium">{{ invite.email }}</p><p class="text-muted-foreground">Enviada el {{ fundDateTime(invite.created_at) }} · Vence el {{ fundDateTime(invite.expires_at) }}</p></div><Badge variant="outline">{{ invitationStatus(invite) }}</Badge></li></ul><FundPagination :links="invitations.links" :last-page="invitations.last_page" label="Páginas de invitaciones" /></section>
    </main>
</template>
