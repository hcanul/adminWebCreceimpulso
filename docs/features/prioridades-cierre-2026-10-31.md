# Prioridades de cierre — 31 de octubre de 2026

El propietario autorizó avanzar en este orden. Implementado no equivale a aceptado en operación. Las pruebas manuales de inicio a fin siguen programadas para después del desarrollo.

| Orden | Trabajo | Estado |
|---|---|---|
| 1 | Sesión persistente protegida en ambas apps | Implementada y probada con adaptador simulado; builds iOS/instalación realizados. Pendiente recorrido físico autenticado y Android. Ver sesion-movil-segura.md. |
| 2 | Completar offline de personal | En desarrollo. Correcciones cifradas, reintentos idempotentes y conciliación por campo implementados. Faltan altas de clientes/grupos, contexto autorizado descargable y conciliación de coincidencias CURP. No cobros offline. |
| 3 | Notificaciones | Android configurado y validado sin envío real. Recepción física pendiente. iPhone bloqueado por membresía Apple/APNs; registro Firebase hecho, FCM nativo pendiente. |
| 4 | Documentos definitivos | Perfiles generales/por sucursal implementados. Datos finales y aprobación legal pendientes del titular. |
| 5 | Aceptación de inicio a fin | Pendiente con oficina, impresoras y teléfonos; no sustituida por pruebas automatizadas. |
| 6 | Operación en servidor | Pendientes dominio/servidor HTTPS estable, secretos de producción, respaldo/restauración, monitoreo y calendario del servidor. El calendario de la Mac es de desarrollo. |
| 7 | Consolidación de siete sucursales | Registro y validación en seco existentes. Pendientes exportaciones locales reales, correspondencias, deduplicación y conciliación de saldos. No se importaron bases operativas. |
| 8 | Distribución/piloto | Compilaciones de muestra disponibles. Firma definitiva, canal de distribución y aceptación del piloto pendientes. |

Reglas por definir antes de implementar su comportamiento: departamento de cobranza y destino del remanente de garantía. Las altas offline no pueden prometer unicidad global de CURP hasta sincronizar. No se fusionarán personas ni se cambiarán UUIDs por coincidencias.

La membresía Apple, la aprobación documental, los datos de sucursales/servidor y las exportaciones de las bases son dependencias externas; no se dan por completadas ni se sustituyen con datos inventados.
