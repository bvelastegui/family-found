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
    PiggyBank,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { dashboard } from '@/routes';
import { index as contributionsIndex } from '@/routes/fund/contributions';
import { index as transactionsIndex } from '@/routes/fund/transactions';
import { index as loansIndex } from '@/routes/fund/loans';
import { index as treasuryIndex } from '@/routes/fund/treasury';
import { index as periodsIndex } from '@/routes/fund/contribution-periods';
import { index as banksIndex } from '@/routes/fund/banks';
import { edit as treasurerEdit } from '@/routes/administration/treasurer';
import type { NavItem } from '@/types';

const page = usePage();
const roles = computed(
    () =>
        page.props.fundRoles as
            | { treasurer: boolean; administrator: boolean }
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
            title: 'Aportes',
            href: contributionsIndex(),
            icon: CalendarDays,
            isActive: url.startsWith(contributionsIndex().url),
        },
        {
            title: 'Transacciones',
            href: transactionsIndex(),
            icon: ReceiptText,
            isActive: url.startsWith(transactionsIndex().url),
        },
        {
            title: 'Préstamos',
            href: loansIndex(),
            icon: Wallet,
            isActive: url.startsWith(loansIndex().url),
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
            isActive: url.startsWith(treasuryIndex().url),
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
    ];
});
const administrationNavItems = computed<NavItem[]>(() => [
    {
        title: 'Designar tesorero',
        href: treasurerEdit(),
        icon: UsersRound,
        isActive: page.url.split('?')[0].startsWith(treasurerEdit().url),
    },
]);
const name = usePage().props.name;
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        class="data-[slot=sidebar-menu-button]:p-1.5!"
                    >
                        <Link :href="dashboard()">
                            <PiggyBank class="size-5!" />
                            <span class="text-base font-semibold">{{ name }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="General" :items="mainNavItems" />
            <NavMain
                v-if="roles?.treasurer"
                label="Tesorería"
                :items="treasuryNavItems"
            />
            <NavMain
                v-if="roles?.administrator"
                label="Administración"
                :items="administrationNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
