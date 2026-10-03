<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppPageHeader from '@/components/AppPageHeader.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
  {
    title: 'Perfil',
    href: editProfile(),
  },
  {
    title: 'Seguridad',
    href: editSecurity(),
  },
  {
    title: 'Apariencia',
    href: editAppearance(),
  },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
  <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 md:p-8">
    <AppPageHeader title="Configuración" />

    <div class="flex flex-col lg:flex-row lg:space-x-12">
      <aside class="w-full max-w-xl lg:w-48">
        <nav
          class="grid grid-cols-3 gap-1 rounded-xl bg-muted p-1 lg:flex lg:flex-col"
          aria-label="Configuración"
        >
          <Button
            v-for="item in sidebarNavItems"
            :key="toUrl(item.href)"
            variant="ghost"
            :class="[
              'min-h-11 w-full justify-center rounded-lg lg:justify-start',
              {
                'bg-background text-foreground shadow-sm': isCurrentOrParentUrl(
                  item.href,
                ),
              },
            ]"
            as-child
          >
            <Link
              :href="item.href"
              :aria-current="
                isCurrentOrParentUrl(item.href) ? 'page' : undefined
              "
            >
              <component
                :is="item.icon"
                class="h-4 w-4"
              />
              {{ item.title }}
            </Link>
          </Button>
        </nav>
      </aside>

      <Separator class="my-4 lg:hidden" />

      <div class="flex-1 md:max-w-2xl">
        <section class="max-w-xl space-y-6">
          <slot />
        </section>
      </div>
    </div>
  </div>
</template>
