<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
  CalendarDays,
  Landmark,
  ClipboardCheck,
  LayoutDashboard,
  ReceiptText,
  Settings2,
  UsersRound,
  Wallet,
  Galaxy,
  ShieldCheck,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useSidebar } from '@/components/ui/sidebar/utils';
import { dashboard } from '@/routes';
import { index as contributionsIndex } from '@/routes/fund/contributions';
import { index as transactionsIndex } from '@/routes/fund/transactions';
import { index as loansIndex } from '@/routes/fund/loans';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import { index as treasuryContributionsIndex } from '@/routes/fund/treasury/contributions';
import { index as reconciliationIndex } from '@/routes/fund/treasury/reconciliation';
import { index as periodsIndex } from '@/routes/fund/contribution-periods';
import { index as banksIndex } from '@/routes/fund/banks';
import { index as participantsIndex } from '@/routes/fund/treasury/participants';
import {
  index as auditIndex,
  controls as auditControls,
} from '@/routes/fund/audit';
import type { NavItem } from '@/types';

const page = usePage();
const { isMobile, setOpenMobile } = useSidebar();
const roles = computed(
  () =>
    page.props.fundRoles as
      | { treasurer: boolean; administrator: boolean; auditor: boolean }
      | undefined,
);
const mainNavItems = computed<NavItem[]>(() => {
  const url = page.url.split('?')[0];
  return [
    {
      title: 'Inicio',
      href: dashboard(),
      icon: LayoutDashboard,
      isActive: url === dashboard().url,
    },
    {
      title: 'Mis aportes',
      href: contributionsIndex(),
      icon: CalendarDays,
      isActive: url.startsWith(contributionsIndex().url),
    },
    {
      title: 'Mis transacciones',
      href: transactionsIndex(),
      icon: ReceiptText,
      isActive: url.startsWith(transactionsIndex().url),
    },
  ];
});
const treasuryNavItems = computed<NavItem[]>(() => {
  const url = page.url.split('?')[0];

  return [
    {
      title: 'Resumen',
      href: treasuryIndex(),
      icon: ClipboardCheck,
      isActive: url === treasuryIndex().url,
    },
    {
      title: 'Conciliación',
      href: reconciliationIndex(),
      icon: ClipboardCheck,
      isActive: url === reconciliationIndex().url,
    },
    {
      title: 'Aportes',
      href: treasuryContributionsIndex(),
      icon: CalendarDays,
      isActive: url === treasuryContributionsIndex().url,
    },
    {
      title: 'Préstamos',
      href: loansIndex(),
      icon: Wallet,
      isActive: url.startsWith(loansIndex().url),
    },
    {
      title: 'Cuotas',
      href: periodsIndex(),
      icon: Settings2,
      isActive: url.startsWith(periodsIndex().url),
    },
    {
      title: 'Bancos',
      href: banksIndex(),
      icon: Landmark,
      isActive: url.startsWith(banksIndex().url),
    },
    {
      title: 'Participantes',
      href: participantsIndex(),
      icon: UsersRound,
      isActive: url.startsWith(participantsIndex().url),
    },
  ];
});
const name = usePage().props.name;
const auditNavItems = computed<NavItem[]>(() => {
  const url = page.url.split('?')[0];
  return [
    {
      title: 'Trazabilidad',
      href: auditIndex(),
      icon: ReceiptText,
      isActive: url.startsWith(auditIndex().url) && url !== auditControls().url,
    },
    {
      title: 'Controles',
      href: auditControls(),
      icon: ShieldCheck,
      isActive: url === auditControls().url,
    },
  ];
});

watch(
  () => page.url,
  () => {
    if (isMobile.value) {
      setOpenMobile(false);
    }
  },
);
</script>

<template>
  <Sidebar
    collapsible="icon"
    variant="inset"
  >
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton
            as-child
            class="data-[slot=sidebar-menu-button]:p-1.5!"
          >
            <Link :href="dashboard()">
              <Galaxy class="size-5!" />
              <span class="text-base font-semibold">{{ name }}</span>
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain
        label="Mi cuenta"
        :items="mainNavItems"
      />
      <NavMain
        v-if="roles?.treasurer"
        label="Tesorería"
        :items="treasuryNavItems"
      />
      <NavMain
        v-if="roles?.auditor"
        label="Auditoría"
        :items="auditNavItems"
      />
    </SidebarContent>

    <SidebarFooter>
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
