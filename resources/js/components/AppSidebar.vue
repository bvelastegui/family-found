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
  Ellipsis,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import { edit as profileEdit } from '@/routes/profile';
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
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';

const page = usePage();
const { isMobile, setOpenMobile } = useSidebar();
const isMoreMenuOpen = ref(false);
const roles = computed(
  () =>
    page.props.fundRoles as
      | { treasurer: boolean; administrator: boolean; auditor: boolean }
      | undefined,
);
const mainNavItems = computed<NavItem[]>(() => {
  const url = page.url.split('?')[0];
  const homeHref = contributionsIndex();

  return [
    {
      title: 'Inicio',
      href: homeHref,
      icon: LayoutDashboard,
      isActive: url === homeHref.url,
    },
    {
      title: 'Perfil',
      href: profileEdit(),
      icon: Settings2,
      isActive: url.startsWith('/settings'),
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
const moreNavItems = computed<NavItem[]>(() => [
  {
    title: 'Perfil y configuración',
    href: profileEdit(),
    icon: Settings2,
    isActive: page.url.split('?')[0].startsWith('/settings'),
  },
  ...(!roles.value?.treasurer
    ? [
        {
          title: 'Mis préstamos',
          href: loansIndex(),
          icon: Wallet,
          isActive: page.url.split('?')[0].startsWith(loansIndex().url),
        },
      ]
    : []),
  ...(roles.value?.treasurer ? treasuryNavItems.value : []),
  ...(roles.value?.auditor ? auditNavItems.value : []),
]);
const mobileNavItems = mainNavItems;

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
  <nav
    class="fixed inset-x-0 bottom-0 z-40 grid grid-cols-4 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_-12px_rgba(0,0,0,0.3)] backdrop-blur md:hidden"
    aria-label="Navegación principal"
  >
    <Link
      v-for="item in mobileNavItems"
      :key="item.title"
      :href="item.href"
      class="flex min-h-16 flex-col items-center justify-center gap-1 px-1 text-[11px] text-muted-foreground transition-colors"
      :class="item.isActive ? 'font-semibold text-primary' : ''"
      :aria-current="item.isActive ? 'page' : undefined"
      @click="setOpenMobile(false)"
    >
      <component
        :is="item.icon"
        class="size-5"
      />
      <span>{{
        item.title === 'Mis aportes'
          ? 'Aportes'
          : item.title === 'Mis transacciones'
            ? 'Transacciones'
            : item.title
      }}</span>
    </Link>
    <button
      type="button"
      class="flex min-h-16 flex-col items-center justify-center gap-1 px-1 text-[11px] text-muted-foreground"
      :aria-expanded="isMoreMenuOpen"
      aria-haspopup="dialog"
      @click="isMoreMenuOpen = true"
    >
      <Ellipsis class="size-5" />
      <span>Más</span>
    </button>
  </nav>
  <Sheet v-model:open="isMoreMenuOpen">
    <SheetContent
      side="bottom"
      class="max-h-[80vh] rounded-t-2xl px-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
    >
      <SheetHeader class="px-0 text-left">
        <SheetTitle>Más opciones</SheetTitle>
        <SheetDescription
          >Tu cuenta y opciones disponibles del fondo</SheetDescription
        >
      </SheetHeader>
      <div class="mt-3 grid gap-1 overflow-y-auto">
        <Link
          v-for="item in moreNavItems"
          :key="item.title"
          :href="item.href"
          class="flex min-h-12 items-center gap-3 rounded-md px-3 text-sm hover:bg-accent"
          :class="
            item.isActive ? 'bg-accent font-medium text-accent-foreground' : ''
          "
          :aria-current="item.isActive ? 'page' : undefined"
          @click="isMoreMenuOpen = false"
        >
          <component
            :is="item.icon"
            class="size-5"
          />
          <span>{{ item.title }}</span>
        </Link>
      </div>
    </SheetContent>
  </Sheet>
  <slot />
</template>
