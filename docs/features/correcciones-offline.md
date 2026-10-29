# Correcciones offline del personal

La documentación se genera exclusivamente en admin-web. La app del personal prepara y corrige información; no genera contratos, pagarés ni PDFs.

Con conexión: entra a Grupos, abre integrantes y pulsa Preparar edición sin conexión. Se exige un expediente existente en borrador, acceso autorizado y una clave local de al menos ocho caracteres. Confirma la clave la primera vez. Cada espacio queda separado por servidor, usuario y sucursal. No se reemplaza una copia ya preparada, evitando perder pendientes.

Sin conexión: desde el menú o desde el login abre Correcciones sin conexión. Desbloquea tu espacio con la clave local, pulsa Corregir información, edita y Guarda en dispositivo. El registro cifrado persiste en IndexedDB, incluso al reabrir la app. No se conserva la clave; si se olvida no se pueden recuperar las correcciones locales. Las ediciones aún no guardadas requieren confirmación al salir. Bloquear descarta la clave descifrada de la pantalla. Desinstalar o borrar datos de la app elimina las copias locales.

Se usa AES-GCM con clave derivada mediante PBKDF2 (250.000 iteraciones). La clave de la cuenta y los tokens no se almacenan en el archivo cifrado. Para navegador se requiere contexto seguro (HTTPS/localhost); la app nativa contiene los recursos web. Esto no incorpora una PWA con caché del sitio para recargar la web sin internet. El acceso offline con clave local no puede conocer revocaciones del servidor hasta recuperar conexión.

Para enviar: inicia sesión con la misma cuenta/servidor/sucursal y pulsa Sincronizar pendientes. El servidor vuelve a validar permisos, estado, campos y versiones de expediente y cliente. Los errores mantienen la copia; un conflicto 409 no sobrescribe oficina. Revisa los cambios locales y la versión en oficina antes de conciliarlos. No hay fusión automática ni botón para forzar sobrescritura. Si el servidor guardó pero se perdió su respuesta, el reintento puede dar conflicto y requiere revisión; no se duplica la creación de un expediente.

Esta entrega cubre correcciones de borradores existentes. CURP, asignaciones de sucursal/grupo/promotor, altas nuevas, envío a Gestión, comité, operaciones financieras y cuentas siguen online. No hay sincronización en segundo plano: se realiza explícitamente para que el usuario vea el resultado. Los permisos revocados bloquean el envío sin borrar las correcciones locales.

Prueba automatizada de navegador: preparación, clave incorrecta, edición con red desconectada, cifrado sin texto personal legible en IndexedDB, reapertura, bloqueo de envío con otra cuenta, envío único y conflicto persistente. Usa API simulada, no datos operativos. Las versiones se validan con las reglas existentes del backend.
