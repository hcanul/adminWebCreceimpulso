import { BrowserWindow, app, shell, dialog } from 'electron';
import path from 'node:path';
import { registerQuasarRuntime, resolveElectronAssetsPath } from '#q-app/electron/main';

async function createWindow() {
  const mainWindow = new BrowserWindow({
    title: 'Crece Impulso Administración',
    icon: resolveElectronAssetsPath('icons/icon.png'),
    width: 1360,
    height: 900,
    minWidth: 900,
    minHeight: 640,
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
      webSecurity: true,
      preload: path.join(import.meta.dirname, 'electron-preload.cjs'),
    },
  });
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (url.startsWith('https://') || url.startsWith('http://')) void shell.openExternal(url);
    return { action: 'deny' };
  });
  mainWindow.webContents.on('will-navigate', (event, url) => {
    const current = mainWindow.webContents.getURL().split('#')[0];
    if (url.split('#')[0] !== current) event.preventDefault();
  });
  mainWindow.webContents.session.setPermissionRequestHandler((_contents, _permission, callback) =>
    callback(false),
  );
  mainWindow.webContents.session.on('will-download', (_event, item) => {
    item.setSaveDialogOptions({ title: 'Guardar archivo de Crece Impulso' });
  });
  if (import.meta.env.QUASAR_DEV) await mainWindow.loadURL(import.meta.env.QUASAR_APP_URL);
  else await mainWindow.loadFile('index.html');
}

void app.whenReady().then(async () => {
  registerQuasarRuntime();
  try {
    await createWindow();
  } catch {
    dialog.showErrorBox('Crece Impulso', 'No se pudo abrir la aplicación.');
  }
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) void createWindow();
  });
});
app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});
