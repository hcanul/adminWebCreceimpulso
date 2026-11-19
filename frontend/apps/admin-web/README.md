# admin-web (admin-web)

## Install the dependencies

```bash
pnpm install
# or: yarn/npm/bun install
```

### Start the app in development mode (HMR, error reporting, etc.)

```bash
quasar dev
```

### Format & Lint the files

```bash
pnpm run lint
# or: yarn/npm/bun run lint
```

...or just check formatting & linting:

```bash
pnpm run lint:check
# or: yarn/npm/bun run lint:check
```

### Build the app for production

```bash
quasar build
```

### Customize the configuration

See [Configuring quasar.config.js](https://v2.quasar.dev/quasar-cli-vite/quasar-config-file).

## Aplicación de escritorio

Consulta [DESKTOP.md](DESKTOP.md) para compilar con Electron, crear el instalador macOS y configurar el servidor.

Para generar el instalador de Windows x64: `pnpm desktop:build:windows`. Consulta [WINDOWS.md](WINDOWS.md).
