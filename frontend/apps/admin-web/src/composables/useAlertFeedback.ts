import { watch, type Ref } from 'vue';
import { alerts } from '../services/alerts.service';

// Mantener detalles y errores por campo en la pantalla, además del aviso general.
export function useAlertFeedback(error: Ref<string>, success?: Ref<string>) {
  watch(error, (message) => {
    if (message) void alerts.error(message);
  });
  if (success)
    watch(success, (message) => {
      if (message) void alerts.success(message);
    });
}
