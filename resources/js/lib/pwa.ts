type InstallPrompt = Event & { prompt: () => Promise<{ outcome: string }> };

let pendingInstall: InstallPrompt | null = null;

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    pendingInstall = event as InstallPrompt;
    window.dispatchEvent(new Event('fund-install-available'));
  });
  window.addEventListener('appinstalled', () => {
    pendingInstall = null;
  });
}

export function canInstall(): boolean {
  return pendingInstall !== null;
}

export async function installApp(): Promise<void> {
  const prompt = pendingInstall;
  if (prompt) {
    pendingInstall = null;
    await prompt.prompt();
  }
}
