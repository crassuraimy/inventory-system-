import { useEffect, useState } from 'react';
import api from './client';

let cachedMeta = null;

export function useAppMeta() {
  const [meta, setMeta] = useState(cachedMeta || { company_name: 'Inventory Management' });

  useEffect(() => {
    if (cachedMeta) return;
    let active = true;
    api.get('/v1/meta')
      .then((res) => {
        cachedMeta = res.data;
        if (active) setMeta(res.data);
      })
      .catch(() => {});
    return () => { active = false; };
  }, []);

  return meta;
}
