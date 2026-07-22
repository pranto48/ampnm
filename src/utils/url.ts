/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
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
