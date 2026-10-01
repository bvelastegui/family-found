import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import '@/lib/pwa';

const appName = 'Fondo Familiar';
const translations: Record<string, string> = {
  'Delete account': 'Eliminar cuenta',
  'Delete your account and all of its resources':
    'Elimina tu cuenta y todos sus datos',
  Warning: 'Advertencia',
  'Please proceed with caution, this cannot be undone.':
    'Continúa con precaución. Esta acción no se puede deshacer.',
  'Are you sure you want to delete your account?':
    '¿Seguro que quieres eliminar tu cuenta?',
  'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.':
    'Al eliminar tu cuenta, también se borrarán permanentemente todos tus datos. Escribe tu contraseña para confirmar la eliminación.',
  Password: 'Contraseña',
  Cancel: 'Cancelar',
};

void createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: (name) => {
    switch (true) {
      case name.startsWith('auth/'):
        return AuthLayout;
      case name.startsWith('settings/'):
        return [AppLayout, SettingsLayout];
      default:
        return AppLayout;
    }
  },
  withApp: (app) => {
    app.config.globalProperties.__ = (key: string): string =>
      translations[key] ?? key;
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
