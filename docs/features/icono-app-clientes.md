# Identidad visual de la app de clientes

El logotipo completo proporcionado se conserva en `frontend/apps/client-app/public/crece-impulso-logo.png`. El icono cuadrado, adaptado a partir del símbolo de la marca sin texto, se encuentra en `frontend/apps/client-app/resources/app-icon.png`.

Para regenerar los tamaños iOS, Android y web en macOS:

```sh
node frontend/apps/client-app/scripts/app-icons.mjs
```

El icono iOS es opaco y mide 1024 × 1024. Android incluye densidades mdpi a xxxhdpi, iconos tradicionales, redondos y recursos adaptativos con margen adicional para máscaras. El logotipo completo sigue disponible dentro de la app. Los cambios de icono nativo requieren una compilación e instalación nueva; no basta recargar la página.
