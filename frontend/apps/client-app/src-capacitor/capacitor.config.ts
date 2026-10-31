import { defineCapacitorConfig } from '@quasar/app-vite/capacitor';

export default defineCapacitorConfig({
  appId: 'com.creceimpulso.clientes',
  appName: 'Crece Impulso',
  plugins: {
    PushNotifications: { presentationOptions: ['alert', 'sound'] },
  },
});
