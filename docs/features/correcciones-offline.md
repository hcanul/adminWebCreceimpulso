# Correcciones offline del personal

La documentación se genera exclusivamente en admin-web. La app del personal prepara y corrige información; no genera contratos, pagarés ni PDFs.

Con conexión: entra a Grupos, abre integrantes y pulsa Preparar edición sin conexión. Se exige un expediente existente en borrador, acceso autorizado y una clave local de al menos ocho caracteres. Confirma la clave la primera vez. Cada espacio queda separado por servidor, usuario y sucursal. No se reemplaza una copia ya preparada, evitando perder pendientes.

Sin conexión: desde el menú o desde el login abre Correcciones sin conexión. Desbloquea tu espacio con la clave local, pulsa Corregir información, edita y Guarda en dispositivo. El registro cifrado persiste en IndexedDB, incluso al reabrir la app. No se conserva la clave; si se olvida no se pueden recuperar las correcciones locales. Las ediciones aún no guardadas requieren confirmación al salir. Bloquear descarta la clave descifrada de la pantalla. Desinstalar o borrar datos de la app elimina las copias locales.

Se usa AES-GCM con clave derivada mediante PBKDF2 (250.000 iteraciones). La clave de la cuenta y los tokens no se almacenan en el archivo cifrado. Para navegador se requiere contexto seguro (HTTPS/localhost); la app nativa contiene los recursos web. Esto no incorpora una PWA con caché del sitio para recargar la web sin internet. El acceso offline con clave local no puede conocer revocaciones del servidor hasta recuperar conexión.

Para enviar: inicia sesión con la misma cuenta/servidor/sucursal y pulsa Sincronizar pendientes. El servidor vuelve a validar permisos, estado, campos y versiones de expediente y cliente. Los errores mantienen la copia; un conflicto 409 no sobrescribe oficina. Revisa los cambios locales y la versión en oficina antes de conciliarlos. No hay fusión automática ni botón para forzar sobrescritura. Cada corrección guardada recibe un UUID durable. Si se pierde la respuesta después del guardado, se reintenta con el mismo UUID y contenido sin crear otra revisión. Si oficina realizó cambios posteriores, se informa un conflicto y se conserva la corrección local. El servidor revalida alcance y permisos también al reconocer reintentos.

Esta entrega cubre correcciones de borradores existentes. CURP, asignaciones de sucursal/grupo/promotor, altas nuevas, envío a Gestión, comité, operaciones financieras y cuentas siguen online. No hay sincronización en segundo plano: se realiza explícitamente para que el usuario vea el resultado. Los permisos revocados bloquean el envío sin borrar las correcciones locales.

Prueba automatizada de navegador: preparación, clave incorrecta, edición con red desconectada, cifrado sin texto personal legible en IndexedDB, reapertura, bloqueo de envío con otra cuenta, envío único y conflicto persistente. Usa API simulada, no datos operativos. Las versiones se validan con las reglas existentes del backend.

## Conciliación explícita

Cuando aparece un conflicto, pulsa **Revisar conflicto con oficina** con conexión y la misma cuenta. Se muestran los campos diferentes con el valor local y el de oficina. Es obligatorio elegir qué conservar en cada diferencia. Guardar la conciliación modifica únicamente la copia cifrada, registra las versiones consultadas y genera un nuevo UUID de operación. Después se utiliza **Sincronizar pendientes**. Si oficina vuelve a cambiar el expediente, se informa otro conflicto; nunca se fuerza una escritura. Si dejó de ser borrador o ya no tienes acceso, se conserva la copia local y no se permite conciliar.

Verificación: recorrido de navegador simulado que conserva la copia al perder la red, restaura tras reapertura, bloquea otras cuentas y concilia campo por campo antes de sincronizar. La suite backend de 156 casos pasó en SQLite antes de esta actualización de interfaz. La verificación adicional de MySQL está pendiente porque el servicio local no responde en 3306; no se modificó la base operativa para probar.

Si la escritura del estado local falla después de que el servidor aceptó el cambio, la pantalla conserva la corrección pendiente y su mismo UUID. Antes de enviar cualquier intento se comprueba que el archivo local se haya guardado. No se marca éxito hasta persistir la confirmación.
