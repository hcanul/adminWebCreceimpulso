import Swal from 'sweetalert2';
import { ref } from 'vue';

export const alertIsOpen = ref(false);

const dialog = Swal.mixin({
  confirmButtonText: 'Entendido',
  cancelButtonText: 'Cancelar',
  confirmButtonColor: '#811335',
  cancelButtonColor: '#626b7b',
  heightAuto: false,
  willOpen: () => {
    alertIsOpen.value = true;
  },
  didDestroy: () => {
    alertIsOpen.value = false;
  },
  keydownListenerCapture: true,
  customClass: {
    container: 'crece-alert-container',
    popup: 'crece-alert',
    htmlContainer: 'crece-alert-text',
  },
});

// El contenido de la API siempre se presenta como texto, nunca como HTML.
export const alerts = {
  async confirm(
    title: string,
    text: string,
    confirmButtonText = 'Sí, continuar',
  ): Promise<boolean> {
    const result = await dialog.fire({
      titleText: title,
      text,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText,
      focusCancel: true,
      allowOutsideClick: false,
    });
    return result.isConfirmed;
  },
  async error(text: string, title = 'No se pudo completar la operación'): Promise<void> {
    await dialog.fire({ titleText: title, text, icon: 'error' });
  },
  async info(title: string, text: string): Promise<void> {
    await dialog.fire({ titleText: title, text, icon: 'info' });
  },
  async success(text: string): Promise<void> {
    await dialog.fire({
      titleText: text,
      icon: 'success',
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3500,
      timerProgressBar: true,
      didOpen: (popup) => {
        popup.onmouseenter = Swal.stopTimer;
        popup.onmouseleave = Swal.resumeTimer;
      },
    });
  },
};
