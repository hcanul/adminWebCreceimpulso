# Entrega móvil — 29 de octubre de 2026

## Cliente

Crédito activo real: capital solicitado y aprobado, interés, entrega neta, garantía original, saldo de cuotas, vencidos y mora. Tablas Personal y Grupo con 16 semanas. Verde = pagado; amarillo = incompleto no vencido; rojo = vencido con saldo, incluso si tiene abono. No se suman cuotas grupales a la deuda individual. Mora se muestra por separado.

Mis comprobantes consulta pagos reales del crédito activo donde la persona aportó o recibió aplicación. Paginación de20, importes personal/grupal separados y sin nombres ni referencias bancarias ajenas. Los QR firmados consultan el estado actual, incluyendo reversos. Una captura de pantalla no acredita vigencia. Una cuenta sin crédito activo no muestra créditos históricos. La demo solo aparece para la cuenta ficticia local y si no tiene crédito real.

## Personal

Menú por permiso: expedientes, grupos/comité, solicitudes, revisión de Gestión, propuesta y autorización, entrega, cartera, estados de cuenta, garantía, cobros en oficina, tickets/reversos y cortes. La app usa su Axios y sesión; backend revalida roles, alcance, estado y versiones. Se limpiaron stores financieros al salir. Promotores no reciben acceso a Caja por este cambio. Todas las operaciones financieras requieren conexión. Documentos, impresión y exportaciones permanecen exclusivamente en admin-web.

Los módulos operativos adaptan los formularios existentes del panel. Se conserva duplicación temporal de componentes/contratos TypeScript entre apps para no mezclar sesiones; una extracción posterior a paquetes compartidos debe preservar los límites de permisos. No hay iframe ni token de admin-web en móvil.

Offline: únicamente correcciones de expedientes borrador preparados en línea, cifrado local con clave, persistencia y sincronización explícita con versiones. Altas de clientes/grupos, envío a Gestión y operaciones financieras siguen en línea. Los conflictos requieren conciliación; no se fuerza sobrescritura. Ver correcciones-offline.md.

## Credenciales al imprimir contrato

Desde admin-web → Documentos, el botón Imprimir contrato usa POST /documents/versions/{id}/print. Se agregan al final nombre, número de cliente, contraseña temporal y vencimiento para cuentas nuevas. Solo perfiles autorizados para atención de accesos y del crédito pueden emitirlas. Se usa la autorización vigente y se audita la emisión. GET/visualización no crea accesos.

Las contraseñas son aleatorias, se guardan como hash, no se agregan al snapshot/HTML persistido del contrato ni a la auditoría. Solo se revelan en la respuesta de impresión. Vencen en7d y la app obliga a cambiarlas antes de consultar información. Reimprimir no resetea cuentas existentes ni revela contraseñas anteriores. La hoja completa permanece bajo custodia de sucursal; entregar a cada titular únicamente su renglón.

Si se pierde la hoja, falla la impresión o vence la clave: admin-web → Acceso y recuperación de clientes → verificar identidad → Recuperar una cuenta existente. Código de30min, un uso, ligado al estado de la contraseña. Emitirlo no cambia la cuenta; canjearlo exige clave diferente y revoca sesiones anteriores. Los códigos no son recuperables en el navegador.

Los formatos contrato/pagaré conservan su estado previo contract_review/promissory_review: documentos en revisión, no habilitados para firma. Esta entrega no los declara aprobados jurídicamente.

## Personal: rotación cada15d

Se aplica en backend, admin-web y staff-app. Al vencer, solo perfil, cierre y cambio de contraseña están disponibles. Se exige contraseña actual, nueva confirmada (12caracteres, letras/números), diferente de la actual y la anterior. Se revocan los otros tokens al cambiar y se entrega nueva sesión de8h. Los hashes no se serializan. Cuentas existentes sin fecha registrada deben renovar al primer acceso después de migrar.

## Estado de despliegue

Migraciones aditivas aplicadas a MySQL local creceimpulso_db el29/10. No se eliminaron clientes ni operaciones. No se enviaron notificaciones reales ni se generaron claves de clientes operativos como prueba. Los tests backend usan SQLite en memoria y UI API simulada; no equivalen al recorrido manual físico que se realizará al final.

## Antes de uso productivo

Servidor HTTPS accesible estable; credenciales de push y scheduler; firma/distribución iOS/Android; validación documental y políticas de privacidad; recorrido completo físico. La sesión móvil sigue usando sessionStorage (8h en servidor); no incluye inicio biométrico ni persistencia de token en Keychain/Keystore. La persistencia de correcciones offline sí es durable y cifrada. No declarar soporte de altas offline ni distribución pública hasta completarlos/validarlos.

## Verificación de cierre (30 de octubre)

147 pruebas automatizadas y 1,917 comprobaciones backend aprobadas. Build SPA y verificación TypeScript/ESLint de admin-web, client-app y staff-app correctos. UI automatizada con API simulada: rutas por cinco perfiles, tokens de sesión del personal, bloqueo por contraseña vencida; tablas/colores/totales del cliente; edición offline cifrada, reapertura, usuario incorrecto, sincronización y conflictos. Estas pruebas técnicas no reemplazan el recorrido manual completo solicitado para después del desarrollo.

Cerrar sesión del cliente desactiva las suscripciones push de su cuenta en todos los dispositivos. Puede activarlas de nuevo en cada dispositivo. El estado de consentimiento se consulta por instalación; no depende de que siga vigente el token de inicio de sesión de8horas.
