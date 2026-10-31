import { defineBoot } from '#q-app';
import { Capacitor } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';
export default defineBoot(async ({ router }) => {
  if (Capacitor.isNativePlatform()) {
    await PushNotifications.addListener('pushNotificationActionPerformed', () => {
      void router.push('/mensajes');
    });
  }
});
