export const getDockerBaseUrl = (defaultPort = '2266') => {
  if (typeof window === 'undefined') return '';
  const { protocol, hostname, port } = window.location;
  const effectivePort = port || defaultPort;
  const portSegment = effectivePort ? `:${effectivePort}` : '';
  return `${protocol}//${hostname}${portSegment}`;
};

export const buildPublicMapUrl = (mapId: string | number, defaultPort = '2266') => {
  const base = getDockerBaseUrl(defaultPort);
  return base ? `${base}/public_map.php?map_id=${mapId}` : '';
};
