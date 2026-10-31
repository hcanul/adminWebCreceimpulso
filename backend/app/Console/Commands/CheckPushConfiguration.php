<?php

namespace App\Console\Commands;

use App\Domains\Push\Services\PushSender;
use Illuminate\Console\Command;

final class CheckPushConfiguration extends Command
{
    protected $signature = 'messages:check-push {--verify-fcm : Valida OAuth y permisos FCM sin entregar notificaciones}';

    protected $description = 'Revisa la configuración push sin mostrar claves ni enviar mensajes.';

    public function handle(PushSender $sender): int
    {
        $this->table(['Configuración', 'Estado'], [
            ['Envíos habilitados', config('client_push.enabled') ? 'Sí' : 'No'],
            ['Android', $sender->ready('android') ? 'Configurado; falta comprobar entrega' : 'Pendiente'],
            ['iPhone', $sender->ready('ios') ? 'Configurado; falta comprobar firma y entrega' : 'Pendiente de Apple'],
            ['Zona horaria', config('client_push.timezone')],
        ]);
        if ($this->option('verify-fcm')) {
            try {
                if (! $sender->verifyAndroid()) {
                    $this->error('FCM rechazó la validación. Revisa la API habilitada y el permiso de envío de la cuenta de servicio.');

                    return self::FAILURE;
                }
                $this->info('FCM validó las credenciales y el mensaje de prueba. No se envió ninguna notificación.');
            } catch (\Throwable) {
                $this->error('No fue posible validar FCM. Revisa el archivo privado y la conexión con Google.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
