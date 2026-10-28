/** Accept a server root or API base; credentials must never be part of this URL. */
export function normalizeServerAddress(input: string): string {
  let value = input.trim();
  if (!value || /\s/.test(value)) throw new Error('Escribe una IP o dominio válido, sin espacios.');
  if (!value.includes('://')) {
    const local =
      /^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(value) ||
      /^[\w-]+\.local(?::\d+)?(?:\/|$)/i.test(value);
    value = `${local ? 'http' : 'https'}://${value}`;
  }
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new Error('La dirección del servidor no es válida.');
  }
  if (
    !['http:', 'https:'].includes(url.protocol) ||
    url.username ||
    url.password ||
    url.search ||
    url.hash
  )
    throw new Error('Usa HTTP o HTTPS sin contraseñas, parámetros ni fragmentos.');
  const host = url.hostname.toLowerCase();
  if (['localhost', '0.0.0.0', '[::]', '[::1]'].includes(host) || host.startsWith('127.'))
    throw new Error('Usa la IP de tu servidor, no localhost ni la dirección del propio teléfono.');
  const privateIP = /^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(host);
  if (url.protocol === 'http:' && !privateIP && !host.endsWith('.local'))
    throw new Error(
      'Para un dominio público utiliza HTTPS. HTTP solo se permite en una red local.',
    );
  const path = url.pathname.replace(/\/+$/, '');
  url.pathname = path.endsWith('/api') ? path : `${path}/api`;
  return url.toString().replace(/\/$/, '');
}
