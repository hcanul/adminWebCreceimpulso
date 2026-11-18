import { getServerAddress } from './server-settings';
import {
  intakeService,
  type SaveApplication,
  type Team,
  type BranchOption,
  type PromoterOption,
} from './intake.service';
import type { StaffUser } from './auth.service';
export interface OfflineRecord {
  operationId?: string;
  newClientId?: string;
  id: string;
  name: string;
  group: string;
  payload: SaveApplication;
  pending: boolean;
  message: string;
  preparedAt: string;
}
export interface OfflineGroup {
  id: string;
  name: string;
  branch_id: string;
  promoter_id: string;
  pending: boolean;
  message: string;
}
interface VaultData {
  groups?: OfflineGroup[];
  context?: { branches: BranchOption[]; promoters: PromoterOption[]; downloadedAt: string };
  owner: string;
  branch: string | null;
  server: string;
  name: string;
  records: OfflineRecord[];
}
interface Envelope {
  id: string;
  revision: number;
  salt: Uint8Array<ArrayBuffer>;
  iv: Uint8Array<ArrayBuffer>;
  encrypted: ArrayBuffer;
}
export interface Vault {
  id: string;
  revision: number;
  salt: Uint8Array<ArrayBuffer>;
  key: CryptoKey;
  data: VaultData;
}
export const scopeFor = (user: StaffUser) =>
  `${getServerAddress()}|${user.id}|${user.branch_id || 'global'}`;
async function database() {
  if (!crypto.subtle)
    throw new Error(
      'El almacenamiento protegido requiere la app instalada o un navegador con HTTPS.',
    );
  return new Promise<IDBDatabase>((resolve, reject) => {
    const r = indexedDB.open('crece-staff-corrections', 1);
    r.onupgradeneeded = () => r.result.createObjectStore('vaults', { keyPath: 'id' });
    r.onsuccess = () => resolve(r.result);
    r.onerror = () => reject(new Error('No se pudo abrir el almacenamiento local.'));
  });
}
async function envelopes(): Promise<Envelope[]> {
  const db = await database();
  try {
    return await new Promise((resolve, reject) => {
      const r = db.transaction('vaults').objectStore('vaults').getAll();
      r.onsuccess = () => resolve(r.result as Envelope[]);
      r.onerror = () => reject(r.error || new Error('No se pudo leer el almacenamiento local.'));
    });
  } finally {
    db.close();
  }
}
export async function localSpaces() {
  return (await envelopes())
    .map((x) => x.id)
    .filter((id) => id.startsWith(getServerAddress() + '|'));
}
export async function unlockVault(
  id: string,
  passphrase: string,
  user?: StaffUser,
): Promise<Vault> {
  const row = (await envelopes()).find((x) => x.id === id);
  if (!row && (!user || id !== scopeFor(user)))
    throw new Error('No hay expedientes preparados para este espacio.');
  if (passphrase.length < 8) throw new Error('Usa una clave local de al menos ocho caracteres.');
  const salt = row?.salt || crypto.getRandomValues(new Uint8Array(16));
  const material = await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(passphrase),
    'PBKDF2',
    false,
    ['deriveKey'],
  );
  const key = await crypto.subtle.deriveKey(
    { name: 'PBKDF2', salt, iterations: 250000, hash: 'SHA-256' },
    material,
    { name: 'AES-GCM', length: 256 },
    false,
    ['encrypt', 'decrypt'],
  );
  let data: VaultData;
  if (row) {
    try {
      data = JSON.parse(
        new TextDecoder().decode(
          await crypto.subtle.decrypt(
            { name: 'AES-GCM', iv: row.iv, additionalData: new TextEncoder().encode(id) },
            key,
            row.encrypted,
          ),
        ),
      ) as VaultData;
    } catch {
      throw new Error(
        'Clave local incorrecta o archivo local dañado. No se modificó la información.',
      );
    }
  } else
    data = {
      owner: user!.id,
      branch: user!.branch_id,
      server: getServerAddress(),
      name: user!.name,
      records: [],
    };
  return { id, revision: row?.revision || 0, salt, key, data };
}
export async function persistVault(vault: Vault) {
  const revision = vault.revision + 1,
    iv = crypto.getRandomValues(new Uint8Array(12));
  const encrypted = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv, additionalData: new TextEncoder().encode(vault.id) },
    vault.key,
    new TextEncoder().encode(JSON.stringify(vault.data)),
  );
  const db = await database();
  try {
    await new Promise<void>((resolve, reject) => {
      const tx = db.transaction('vaults', 'readwrite'),
        store = tx.objectStore('vaults'),
        r = store.get(vault.id);
      r.onsuccess = () => {
        if (((r.result as Envelope | undefined)?.revision || 0) !== vault.revision) {
          tx.abort();
          return;
        }
        store.put({ id: vault.id, revision, salt: vault.salt, iv, encrypted });
      };
      tx.oncomplete = () => resolve();
      tx.onabort = () =>
        reject(
          new Error(
            'El espacio local cambió en otra ventana. Bloquéalo y vuelve a abrirlo antes de editar.',
          ),
        );
      tx.onerror = () =>
        reject(
          new Error(
            'No se pudo guardar en el dispositivo. Conserva la pantalla abierta y reintenta.',
          ),
        );
    });
    vault.revision = revision;
  } finally {
    db.close();
  }
}
export async function prepareRecord(vault: Vault, team: Team, curp: string) {
  const result = await intakeService.lookup(curp, team.id),
    app = result.application;
  if (!result.can_edit || !result.client || !app || app.status !== 'borrador')
    throw new Error(
      'Solo se preparan expedientes existentes en borrador y autorizados. Crea primero el borrador con conexión.',
    );
  if (vault.data.records.some((x) => x.id === app.id))
    throw new Error(
      'Este expediente ya está preparado. Revisa sus cambios locales antes de volver a descargarlo.',
    );
  const record: OfflineRecord = {
    id: app.id,
    name: result.client.name || curp,
    group: team.name,
    preparedAt: new Date().toISOString(),
    pending: false,
    message: 'Preparado',
    payload: {
      id: app.id,
      version: app.version,
      team_id: team.id,
      curp,
      client_id: result.client.id,
      client_version: result.client.version,
      confirm_existing: true,
      submit: false,
      fields: Object.fromEntries(
        Object.entries(app.fields).filter(([k]) => !['ncliente', 'nCredito'].includes(k)),
      ),
    },
  };
  vault.data.records.push(record);
  try {
    await persistVault(vault);
  } catch (e) {
    vault.data.records.pop();
    throw e;
  }
}

export async function prepareContext(vault: Vault, user: StaffUser) {
  if (scopeFor(user) !== vault.id)
    throw new Error('El espacio pertenece a otra cuenta o servidor.');
  const options = await intakeService.options();
  const before = vault.data.context;
  vault.data.context = {
    branches: options.branches,
    promoters: options.promoters,
    downloadedAt: new Date().toISOString(),
  };
  try {
    await persistVault(vault);
  } catch (e) {
    if (before) vault.data.context = before;
    else delete vault.data.context;
    throw e;
  }
}
export async function createLocalGroup(
  vault: Vault,
  name: string,
  branch: string,
  promoter: string,
) {
  const context = vault.data.context;
  if (
    !name.trim() ||
    name.trim().length > 150 ||
    !context?.branches.some((b) => b.id === branch) ||
    !context.promoters.some((p) => p.id === promoter && p.branch_id === branch)
  )
    throw new Error(
      'Selecciona una sucursal y promotor del contexto descargado y escribe el nombre.',
    );
  const group: OfflineGroup = {
    id: crypto.randomUUID(),
    name: name.trim(),
    branch_id: branch,
    promoter_id: promoter,
    pending: true,
    message: 'Pendiente de sincronizar',
  };
  vault.data.groups ??= [];
  vault.data.groups.push(group);
  try {
    await persistVault(vault);
  } catch (e) {
    vault.data.groups.pop();
    throw e;
  }
}

export async function createLocalClient(
  vault: Vault,
  groupId: string,
  curpInput: string,
  firstName: string,
  lastName: string,
) {
  const group = vault.data.groups?.find((g) => g.id === groupId),
    curp = curpInput.trim().toUpperCase();
  if (
    !group ||
    !/^[A-Z][AEIOUX][A-Z]{2}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/.test(curp) ||
    !firstName.trim() ||
    !lastName.trim()
  )
    throw new Error(
      'Selecciona un grupo y completa CURP, nombre y apellido. La CURP se verificará también al sincronizar.',
    );
  if (vault.data.records.some((r) => r.payload.curp === curp))
    throw new Error('Esta CURP ya está capturada en este espacio. Revisa el expediente existente.');
  const record: OfflineRecord = {
    id: crypto.randomUUID(),
    newClientId: crypto.randomUUID(),
    name: `${firstName.trim()} ${lastName.trim()}`,
    group: group.name,
    preparedAt: new Date().toISOString(),
    pending: true,
    message: 'Alta pendiente de validar en servidor',
    payload: {
      id: '',
      version: 0,
      team_id: group.id,
      curp,
      client_id: null,
      client_version: null,
      confirm_existing: false,
      submit: false,
      fields: { pNombre: firstName.trim(), aPaterno: lastName.trim() },
    },
  };
  record.payload.id = record.id;
  vault.data.records.push(record);
  try {
    await persistVault(vault);
  } catch (e) {
    vault.data.records.pop();
    throw e;
  }
}
