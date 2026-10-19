import assets from '../../public/editorial/manifest.json';
import type { Media } from './catalogue';

export function editorialMedia(kind: 'services' | 'about', locale: string): Media {
  const alt = locale === 'ar'
    ? { services: 'محجر للحجر الطبيعي تحت سماء زرقاء', about: 'كتل الحجر الطبيعي في المحجر مع عامل وحفارة' }
    : { services: 'Carrière de pierre naturelle sous un ciel bleu', about: 'Blocs de pierre naturelle, un ouvrier et une excavatrice dans la carrière' };
  return { id: `client-quarry-${kind}`, alt: alt[kind], variants: assets[kind].variants };
}
