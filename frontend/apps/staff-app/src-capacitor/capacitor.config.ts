import { defineCapacitorConfig } from '@quasar/app-vite/capacitor';
export default defineCapacitorConfig({
  appId: 'com.creceimpulso.colaboradores',
  appName: 'Crece Colaboradores',
  plugins: {
    PushNotifications: { presentationOptions: ['alert', 'sound'] },
  },
});
