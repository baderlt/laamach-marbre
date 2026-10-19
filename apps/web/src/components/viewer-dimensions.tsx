'use client';

import { useId } from 'react';
import { measurementGuides, type MeasurementFrame } from '@/lib/viewer-measurements';
import type { Dimensions } from '@/lib/viewer';

export function DimensionLines({ frame, locale }: { frame: MeasurementFrame; locale: string }) {
  const marker = useId();
  const guides = measurementGuides(frame, locale);
  const names = locale === 'ar' ? { width: 'العرض', length: 'الطول', depth: 'السماكة' } : { width: 'Largeur', length: 'Longueur', depth: 'Épaisseur' };
  return <g className="dimension-guides">
    <defs><marker id={marker} markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto-start-reverse"><path d="M6 3 0 0v6Z" fill="currentColor" /></marker></defs>
    {guides.map(guide => <g key={guide.axis} data-dimension-axis={guide.axis}><title>{`${names[guide.axis]} : ${guide.label}`}</title>
      <path className="dimension-line" d={`M${guide.start.join(' ')} L${guide.end.join(' ')}`} markerStart={`url(#${marker})`} markerEnd={`url(#${marker})`} />
      <rect x={guide.x - guide.labelWidth / 2} y={guide.y - 13} width={guide.labelWidth} height="26" rx="13" /><text x={guide.x} y={guide.y} dominantBaseline="central" textAnchor="middle" direction="ltr">{guide.label}</text>
    </g>)}
  </g>;
}

export function ProductDimensionExample({ locale, measurements }: { locale: string; measurements: Partial<Dimensions> }) {
  const id = useId();
  if (!Object.keys(measurements).length) return null;
  const frame: MeasurementFrame = { width: 600, height: 250, center: [300, 125], dimensions: measurements, edges: [
    { axis: 'width', start: [105, 131], end: [360, 185] },
    { axis: 'length', start: [105, 103], end: [255, 45] },
    { axis: 'depth', start: [510, 99], end: [510, 127] },
  ] };
  return <section className="dimension-example" aria-labelledby={id}>
    <div className="dimension-example-heading"><span>{locale === 'ar' ? 'تفاصيل المنتج' : 'LES MESURES DE LA MATIÈRE'}</span><h2 id={id}>{locale === 'ar' ? 'أبعاد المنتج' : 'Dimensions du produit'}</h2></div>
    <svg viewBox="0 0 600 250" role="img" aria-label={locale === 'ar' ? 'مخطط الأبعاد المسجلة للمنتج' : 'Schéma des dimensions renseignées pour ce produit'}>
      <path className="sample-top" d="m105 103 150-58 255 54-150 58Z" /><path className="sample-front" d="m105 103 255 54v28l-255-54Z" /><path className="sample-side" d="m360 157 150-58v28l-150 58Z" />
      <DimensionLines frame={frame} locale={locale} />
    </svg>
    <p>{locale === 'ar' ? 'مخطط لتوضيح الأبعاد المسجلة لهذا المنتج.' : 'Schéma de repérage des dimensions renseignées pour cette référence.'}</p>
  </section>;
}
