# Mensajes para clientes y colaboradores

Actualizado: 30 de octubre de 2026. La bandeja está disponible en Admin Web, Clientes y Colaboradores. Las notificaciones del sistema requieren las apps nativas, consentimiento y proveedor configurado. La bandeja funciona aunque los envíos push estén deshabilitados.

## Eventos y permisos

| Evento | Regla |
| --- | --- |
| Un día antes | Aviso si queda saldo de la cuota que vence mañana. |
| Día de pago | Aviso si queda saldo de la cuota que vence hoy. |
| Pago registrado | Confirmación después de confirmar el cobro, para los clientes que aportaron o recibieron una aplicación. No equivale a liquidación total. |
| Día siguiente | Agradecimiento si la cuota de ayer está cubierta; recordatorio si queda saldo. Un seguimiento por cuota. |
| Mensaje personalizado | Selección de cliente o colaborador mediante búsqueda, asunto, mensaje y confirmación de envío. |

Super Admin, Gerente General y Gerente tienen `messages.send`. Gerencia se limita a su sucursal; Super Admin y Gerente General tienen alcance general. Otros perfiles pueden recibir y solo enviar si se les asigna el permiso. Ningún cliente puede enviar por esta función. Los mensajes completos se ven después del acceso; el aviso personalizado en pantalla bloqueada es genérico.

## Funcionamiento

Laravel programa `messages:plan-payments` cada hora al minuto 05, de 09:05 a 20:05, hora de Cancún. La primera ejecución diaria crea los avisos; las siguientes permiten recuperar una interrupción sin duplicarlos. No genera avisos anteriores al día actual. `messages:dispatch` revisa pendientes cada minuto. El aviso de pago se guarda dentro de la transacción del cobro, por lo que no depende de llamar a Google durante el registro financiero.

Cada evento tiene una clave única por destinatario. Cada intento tiene una clave única por mensaje y dispositivo y se registra antes de llamar al proveedor. Un resultado `accepted` indica aceptación del proveedor, **no entrega ni lectura**. `unknown`, `rejected` o un intento interrumpido no se reenvían automáticamente, para evitar duplicados; requieren revisión. Los mensajes expiran para envío, pero permanecen en la bandeja. Al revertir un pago se cancela su confirmación pendiente y se retira de la bandeja sin borrar el registro de auditoría. Una notificación ya entregada no puede retirarse del teléfono.

Tokens y cuerpo de mensajes se cifran con `APP_KEY`. Los títulos no se cifran. Cerrar sesión desactiva los dispositivos de esa cuenta; al entrar se pueden activar nuevamente. Un cambio de contraseña invalida la suscripción anterior. El dispositivo necesita internet y permiso de notificaciones. La sesión web del administrador recibe bandeja; no se implementó push del navegador.

## Firebase para Android

Proyecto previsto/creado en Google Cloud: `creceimpulso-apps-2026` (Crece Impulso Apps). Si Firebase responde 403 al añadirlo, revisar las condiciones de primer uso en su consola y permisos. No crear otro proyecto para resolverlo.

Registrar en el mismo proyecto:

- Clientes: `com.creceimpulso.clientes`, configuración en `frontend/apps/client-app/src-capacitor/android/app/google-services.json`.
- Colaboradores: `com.creceimpulso.colaboradores`, configuración en `frontend/apps/staff-app/src-capacitor/android/app/google-services.json`.

Mantener plan Spark. Cloud Messaging figura sin costo; no se necesitan Firestore, Cloud Functions ni facturación para este diseño. La programación corre en Laravel. El backend utiliza una cuenta de servicio dedicada con permiso FCM y una clave JSON fuera del repositorio, indicada por `CLIENT_PUSH_FCM_CREDENTIALS`. Esa clave privada nunca se copia a una aplicación.

```dotenv
CLIENT_PUSH_ENABLED=true
CLIENT_PUSH_FCM_CREDENTIALS=/ruta/privada/crece-push-service-account.json
```

Con el archivo configurado, ejecutar desde `backend`:

```sh
php artisan config:clear
php artisan messages:check-push --verify-fcm
```

La comprobación usa `validate_only=true`: verifica la autenticación y permiso de FCM sin entregar mensajes. Recompilar ambas APK después de agregar `google-services.json`. En cada app, entrar a **Mis mensajes → Activar notificaciones**. El envío personalizado desde Admin Web permite después comprobar recepción con un destinatario de prueba.

## Apple para iPhone

Capacitor utiliza APNs directamente para iOS en esta implementación. Firebase Android no habilita automáticamente los avisos del iPhone. Se necesita un equipo Apple que admita Push Notifications, la capacidad en ambos identificadores, perfiles actualizados y clave APNs `.p8` del equipo. El Personal Team de las muestras no habilita push.

```dotenv
CLIENT_PUSH_APNS_KEY=/ruta/privada/AuthKey.p8
CLIENT_PUSH_APNS_KEY_ID=ID_DE_CLAVE
CLIENT_PUSH_APNS_TEAM_ID=ID_DEL_EQUIPO
CLIENT_PUSH_APNS_TOPIC=com.creceimpulso.clientes
CLIENT_PUSH_APNS_STAFF_TOPIC=com.creceimpulso.colaboradores
CLIENT_PUSH_APNS_SANDBOX=true
```

Usar sandbox para la firma de desarrollo; producción para distribución. Activar Push Notifications en los targets de Xcode y regenerar los perfiles. Recompilar e instalar. Los callbacks nativos ya están preparados. No basta con cambiar una variable del backend. No se ha contratado la membresía ni aceptado condiciones de Apple en nombre del titular.

## Servicio programado

En el servidor desplegado, ejecutar `php artisan schedule:run` cada minuto, desde la carpeta `backend`, mediante cron o el supervisor correspondiente. En desarrollo puede usarse `php artisan schedule:work` mientras la Mac esté encendida. Mantener reloj correcto, zona Cancún para el calendario, `APP_KEY` estable y caché compartida si hay varios servidores. No programar además el comando antiguo `clients:payment-reminders`: fue sustituido por este flujo.

## Verificación pendiente en equipos

Después de configurar el proveedor: registro Android/iOS, permiso denegado, app abierta/en segundo plano/cerrada, toque que abre Mis mensajes, cierre de sesión, cambio de contraseña y mensaje propio entre sucursales autorizadas. El proveedor puede retrasar los avisos; el estado de cuenta sigue siendo la fuente del saldo.

Fuentes: [precios Firebase](https://firebase.google.com/pricing), [FCM HTTP v1](https://firebase.google.com/docs/cloud-messaging/send/v1-api), [Capacitor Push](https://capacitorjs.com/docs/apis/push-notifications), [Firebase en proyecto existente](https://firebase.google.com/docs/projects/use-firebase-with-existing-cloud-project).
