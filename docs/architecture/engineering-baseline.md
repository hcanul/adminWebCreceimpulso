# CreceImpulso — Marco de ingeniería DDD-Lite

Actualizado: 8 de octubre de 2026. Documento de trabajo basado en los requisitos expresados por el propietario y en la revisión del repositorio. Define el objetivo; no certifica que todo esté implementado.

## Producto y alcance

Plataforma de microfinanzas B2B/B2C, single tenant, con múltiples sucursales en una base unificada. Oficina online: aprobación, tesorería, estados de cuenta y administración autorizada. Campo offline-first: prospectos, grupos, solicitudes y captura de cobranza. Clientes online: consulta individual/grupal según permisos y tickets QR de pago.

Los 72 campos de la solicitud legacy y su formato imprimible siguen siendo requisitos. La garantía retenida del 10 % permanece pendiente de definición; no inferir su tratamiento contable.

## Requisitos de negocio confirmados

- Crédito otorgado al grupo solidario; participaciones individuales vinculadas a la operación.
- Cliente/persona identificado por CURP independiente de la solicitud y de la cuenta de acceso.
- LoanParticipation conserva vínculo con cliente, rol y montos por solicitud; el historial no se modifica por cambios posteriores en el grupo.
- Crédito activo con Presidenta, Tesorera e Integrantes. Los roles se almacenan en base de datos; las reglas de composición se verifican transaccionalmente antes de activarlo. Pendiente precisar si cada cargo debe ser único y si se permite acumular cargos.
- Plazo fijo de 16 pagos semanales; catálogo de tasas con mínimo, máximo y factor.
- Monto solicitado original y monto aprobado separados. No sobrescribir la solicitud al autorizar. Correcciones con versiones/auditoría, no actualización silenciosa del histórico.
- UUID inmutable en entidades de negocio, también cuando se originan en el dispositivo. El servidor valida formato, pertenencia y duplicados. Roles/permisos de Spatie pueden conservar claves enteras.
- branch_id en entidades operativas, con referencias consistentes. Alcance efectivo depende además del rol y de la asignación del usuario.
- MySQL como motor elegido previamente para desarrollo. El Compose del prototipo anterior no existe automáticamente en este workspace.

## Precisiones de versiones y estado

El repositorio revisado contiene Laravel 12.69.3, quasar ^2.34.0 y @quasar/app-vite ^3.10.1. La especificación menciona Laravel 11 y Quasar Framework v3: registrar la diferencia antes de cambiar dependencias. Laravel 11 finalizó soporte de seguridad el 12 de marzo de 2026 según el calendario oficial. Recomendación: no degradar a 11; escoger una versión soportada mediante decisión explícita. No se hizo ninguna actualización o downgrade en este paso.

Quasar Framework y su herramienta app-vite tienen versiones mayores independientes. Consultar documentación compatible con los paquetes instalados; no equiparar app-vite 3 con quasar 3.

Nombres objetivo: apps/admin-web, apps/staff-mobile, apps/client-mobile, packages/shared. Nombres actuales: admin-web, staff-app y client-app; packages está vacío. El renombrado debe incluir filtros pnpm, imports, scripts y CI; aún no se realizó.

Las fases 0–4 se consideran el avance declarado por el propietario. Su aceptación técnica sigue condicionada a resolver las diferencias documentadas en modelos, migraciones, borrado lógico y autenticación. Fase 5 es el siguiente trabajo de implementación.

## Convenciones frontend

- TypeScript estricto. Contratos explícitos para DTO, errores HTTP, estados de sesión y operaciones de sincronización; evitar any y supresiones de errores sin justificación.
- Los tipos no validan datos de red en ejecución. Validar entrada/salida en las fronteras y repetir validaciones autoritativas en la API.
- Vue 3 con `<script setup lang="ts">`, props/emits tipados, computed para valores derivados y composables para comportamiento reutilizable.
- Quasar para presentación y adaptaciones de plataforma; evitar añadir otro framework visual a estas apps.
- Pinia con stores por capacidad. No convertir el store en base durable ni concentrar allí reglas financieras y acceso a SQLite. Las escrituras pasan por casos de uso y adaptadores.
- Vue Router con una estrategia única por app. En la fase 5, consolidar las rutas manuales descritas por el propietario y los layouts MainLayout/BlankLayout. Guards con estado de sesión inicializado y permisos; no sustituyen autorización del servidor.
- Axios con contrato de errores, tiempo límite y adaptación del token. No reintentar escrituras automáticamente sin clave de idempotencia.
- packages/shared dividido internamente en contracts, validation y finance, con exports explícitos y sin dependencias de Vue/Quasar/Pinia en los cálculos. No colocar APIs nativas en código universal.
- Los cálculos compartidos son para validación y presentación. El servidor recalcula y confirma; TypeScript y PHP no comparten código automáticamente, por lo que se necesitan casos de prueba con resultados idénticos.

## Organización DDD-Lite

Dominios iniciales propuestos: IdentityAccess, Branches, Clients, Groups, Lending, Collections, Treasury y Sync. Introducirlos gradualmente según funcionalidades, no crear capas vacías.

Por dominio: Models, Actions, DTOs, Enums y, cuando exista lógica reutilizable, reglas/valores de dominio. HTTP permanece en Controllers, FormRequests y Resources. La infraestructura offline del frontend permanece separada de la UI.

Controllers: recibir HTTP, delegar validación/autorización, construir DTO, invocar caso de uso y devolver Resource/respuesta.

DTOs: datos de entrada tipados y explícitos; no Request ni modelos Eloquent completos circulando como contrato de red.

Actions: un caso de uso, por ejemplo ApproveLoanAction. Orquestan permisos, invariantes, transacción, persistencia y auditoría. Una Action que consulta Eloquent no es una función pura: extraer cálculo puro cuando aporte pruebas y claridad. No imponer repositorios genéricos por ceremonia.

Enums: estados y roles; validar transiciones, no solo valores aceptados. Enum de PHP, contrato TypeScript y validación de base deben mantenerse alineados.

Eloquent: Active Record permitido; relaciones, casts, claves y política de bajas explícitas. No duplicar todo el ORM en otro modelo de dominio.

## Acceso y sucursales

| Perfil | Alcance acordado |
|---|---|
| Super Admin | Acceso total y configuración técnica de roles/permisos |
| Gerente General | Operación de todas las sucursales; sin crear/editar roles ni asignar permisos del sistema |
| Coordinador / Promotor | Sus grupos y clientes asignados; detalle de diferencias entre ambos por definir |
| Caja / Tesorería | Cobro y cortes de caja de oficina, dentro del alcance autorizado |
| Cliente | Consulta propia; definir qué información grupal puede ver sin exponer expedientes ajenos |

No basar permisos en el nombre personal del gerente. Usar permisos de capacidad y Policies, con pruebas negativas por rol, sucursal y asignación.

Global Scopes ayudan a filtrar, pero no son toda la autorización: pueden omitirse en consultas directas o procesos sin usuario. Contexto de sucursal explícito también en jobs, CLI y sincronización; ausencia de contexto no debe abrir acceso general. El servidor valida branch_id, no confía en el valor enviado por el celular. Definir excepción para usuarios con acceso global y cualquier branch_id nullable.

Sanctum con tokens según la decisión del propietario. Revocación al salir online, expiración, dispositivos y comprobación de usuario activo en solicitudes. En móvil, token en almacenamiento seguro del sistema; no guardar credenciales de usuario en SQLite/IndexedDB sin protección. En web, documentar el riesgo del almacenamiento accesible a JavaScript y no persistir tokens en LocalStorage por comodidad. CORS con orígenes explícitos; CORS no es un control de permisos.

## Offline-first: contrato antes del motor

La implementación completa sigue en fase 9, pero las fases 5–8 deben respetar UUID, versiones e idempotencia desde el principio.

Propuesta a validar:

1. Lecturas de campo desde base local. Cambio de entidad y alta en outbox/cola se guardan en una misma transacción local.
2. Cada comando tiene operation_id UUID estable, entity_id UUID, tipo, versión de contrato, versión base, dependencias y fecha declarada por el dispositivo. La identidad efectiva proviene del token y se comprueba en servidor.
3. Entrega con reintentos y deduplicación persistida en el servidor. Misma operación/mismo contenido devuelve el resultado previo; misma clave/contenido distinto es conflicto. Registro de idempotencia y efectos financieros se confirman atómicamente.
4. Respuesta bulk por operación: aceptada, duplicada, conflicto o rechazada, con versión canónica y error recuperable. Lotes acotados; dependencias cliente→grupo/solicitud→participación respetadas. Definir frontera de transacción por agregado, no asumir atomicidad universal del lote.
5. Pull incremental mediante cursor del servidor y marcas de eliminación; no depender del reloj local. Aplicación local y avance del cursor deben ser atómicos para soportar interrupciones.
6. Conflictos de ficha: control optimista por versión y reglas por campo; revisión humana cuando sea necesario. CURP duplicada con UUID distintos requiere conciliación, nunca cambio silencioso del UUID.
7. Cobranza: registrar movimientos inmutables y reversos autorizados. No resolver pagos por “último timestamp gana”, ni sincronizar el saldo como un valor editable. Una operación capturada offline permanece pendiente de validación/sincronización claramente identificada.
8. Permisos retirados, token vencido, grupo cambiado y dispositivo perdido requieren política explícita. No borrar una cola pendiente al cerrar sesión ni enviar datos de otro usuario al cambiar cuenta.
9. SQLite nativo e IndexedDB web detrás de interfaces; probar capacidades, migraciones, almacenamiento y protección antes de elegir bibliotecas concretas.

Los timestamps sirven como evidencia y orden auxiliar; no prueban quién tiene razón si un reloj está desajustado. El servidor confirma aceptación y asiento financiero. Esta es una decisión de diseño propuesta para proteger la cobranza, no una afirmación de que ya exista el motor.

## Decisiones financieras pendientes

Dieciséis semanas y un factor de ejemplo del 15 % no especifican toda la fórmula. Antes de implementar cálculos confirmar: interés global/plano o sobre saldo, periodicidad del factor, comisiones/impuestos si aplican, precisión y redondeo, asignación de residuos, fecha inicial/vencimientos, mora, pagos parciales/anticipados, cancelaciones y garantía.

Usar dinero exacto (centavos o decimal con escala definida), no floats binarios para cálculos financieros. Congelar condiciones de la tasa en cada crédito para que editar el catálogo no cambie el histórico. QR representa una referencia o intención verificable, no prueba por sí mismo que el pago se realizó.

## Próximo incremento: cierre de fase 5

- Elegir rutas manuales y reparar layouts; registrar plugins utilizados.
- DTO frontend de usuario/token/permisos y estado explícito de sesión.
- Login, consulta de usuario, 401, usuario inactivo y logout con revocación; errores diferenciados.
- Autorización de servidor y separación de capacidades administrativas.
- Pruebas: login correcto/incorrecto, límites de intentos, token revocado, usuario desactivado, recarga, rutas protegidas y ausencia de bucle de redirección.
- Documentar lo validado y pendientes antes de avanzar al dashboard de fase 6.

No iniciar los tres frontends, emuladores o contenedores automáticamente. Respetar la preferencia de consumo bajo del equipo; ejecutar verificaciones focalizadas y finalizar los procesos temporales.

## Fuentes oficiales consultadas

- TypeScript estricto: https://www.typescriptlang.org/tsconfig/strict.html
- Vue SFC y Composition API: https://vuejs.org/api/sfc-script-setup.html
- Pinia: https://pinia.vuejs.org/core-concepts/
- Vue Router y guards: https://router.vuejs.org/guide/advanced/navigation-guards.html
- Quasar/app-vite: https://quasar.dev/quasar-cli-vite/upgrade-guide/
- Quasar y Capacitor: https://quasar.dev/quasar-cli-vite/developing-capacitor-apps/introduction/
- Ciclo de soporte Laravel: https://laravel.com/framework/docs/11.x/releases
- Sanctum: https://laravel.com/framework/docs/12.x/sanctum
- Conceptos de arquitectura offline (referencia conceptual, no decisión de usar Kotlin): https://developer.android.com/topic/architecture/data-layer/offline-first

## Precisión del propietario: consolidación y app de clientes

Confirmado el 8 de octubre de 2026: siete sucursales (Carrillo, Tulum, Morelos, Playa 1, Playa 2, Cancún 1 y Cancún 2), actualmente en bases separadas. Verificar el mapa base/citys antes de importar; no asumir que city_id es globalmente único.

La app de clientes se limita a su crédito activo, vencimientos, atrasos y comprobantes de pagos recibidos con QR verificable. Esto sustituye el alcance anterior de generar referencias para hacer pagos. No incluye generar pagos, ver expedientes de otras personas ni un portal de historial general. Los QR corresponden a comprobantes de movimientos confirmados; la captura offline pendiente no se presenta como pago confirmado.
