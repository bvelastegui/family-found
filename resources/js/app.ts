import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import '@/lib/pwa';

const appName = 'Fondo Familiar';

void createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: (name) => {
    switch (true) {
      case name === 'Welcome':
        return null;
      case name.startsWith('auth/'):
        return AuthLayout;
      case name.startsWith('settings/'):
        return [AppLayout, SettingsLayout];
      default:
        return AppLayout;
    }
  },
  withApp: (app) => {
    app.directive('focus', {
      mounted: (el: HTMLElement, shouldFocus) => {
        if (shouldFocus.value !== false) {
          el.focus();
        }
      },
    });
  },
  progress: {
    color: '#4B5563',
  },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('message', (event: MessageEvent) => {
    if (event.data?.type === 'fund-notification') {
      router.reload({ only: ['unreadNotificationsCount', 'notifications'] });
    }
  });
  window.addEventListener('load', () => {
    void navigator.serviceWorker.register('/sw.js');
  });
}
