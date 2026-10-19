'use client';
import { useEffect, useId, useRef, useState, type ReactNode } from 'react';
import { CatalogueImage } from './catalogue-image';
import type { Media } from '@/lib/catalogue';
import { viewerCopy, viewerDimensions, type ViewerConfiguration, type Dimensions } from '@/lib/viewer';
import type { ViewerApi } from '@/lib/viewer-core';
import type { MeasurementFrame } from '@/lib/viewer-measurements';
import { DimensionLines } from './viewer-dimensions';
import { SiteIcon } from './site-icon';

type Props = { configuration?: ViewerConfiguration | null; measurements?: Partial<Dimensions>; locale: string; cover: Media | null; sizeId?: string | null; onSize(value: string): void; children: ReactNode };
export function ProductViewer(props: Props) {
  return props.configuration ? <InteractiveViewer {...props} configuration={props.configuration} /> : props.children;
}
function InteractiveViewer({ configuration, measurements, locale, cover, sizeId, onSize, children }: Props & { configuration: ViewerConfiguration }) {
  const copy = viewerCopy(locale), id = useId();
  const [opened, setOpened] = useState(false), [phase, setPhase] = useState<'loading' | 'ready' | 'error'>('loading'), [reason, setReason] = useState(''), [attempt, setAttempt] = useState(0);
  const host = useRef<HTMLDivElement>(null), panel = useRef<HTMLDivElement>(null), trigger = useRef<HTMLButtonElement>(null), closeButton = useRef<HTMLButtonElement>(null), api = useRef<ViewerApi | null>(null);
  const [measurementFrame, setMeasurementFrame] = useState<MeasurementFrame | null>(null);
  const [interacted, setInteracted] = useState(false);
  const dimensions = viewerDimensions(configuration, sizeId), dimensionRef = useRef(dimensions);
  const labelledDimensions = measurements && Object.keys(measurements).length ? measurements : dimensions;
  const supported = configuration.mode === 'uploaded_glb' || !!dimensions, active = opened && supported;
  useEffect(() => { dimensionRef.current = dimensions; if (dimensions) api.current?.setDimensions(dimensions); }, [dimensions]);
  useEffect(() => {
    if (!active || !host.current) return;
    const controller = new AbortController(), element = host.current;
    // No runtime or model import occurs before explicit activation.
    import('@/lib/viewer-core').then(module => module.mountViewer(element, configuration, dimensionRef.current, controller.signal, copy.canvas, `${id}-help`, failure => { setReason(failure); setPhase('error'); }, { onFrame: setMeasurementFrame, onInteraction: () => setInteracted(true) })).then(viewer => {
      if (controller.signal.aborted) { viewer.dispose(); return; } api.current = viewer; viewer.setInteraction(true); if (dimensionRef.current) viewer.setDimensions(dimensionRef.current); setPhase('ready');
    }).catch(error => { if (!controller.signal.aborted) { setReason(error instanceof Error ? error.message : 'asset'); setPhase('error'); } });
    return () => { controller.abort(); api.current?.dispose(); api.current = null; };
  }, [active, configuration, attempt, copy.canvas, id]);
  function close() { setOpened(false); requestAnimationFrame(() => trigger.current?.focus({ preventScroll: true })); }
  return <div className="product-viewer">
    <div data-viewer-photos hidden={active}>{children}</div>
    {!active && <><button className="button outline viewer-activation" ref={trigger} type="button" data-viewer-activate disabled={!supported} onClick={() => { setPhase('loading'); setReason(''); setMeasurementFrame(null); setOpened(true); requestAnimationFrame(() => closeButton.current?.focus()); }}>{copy.open}</button>{!supported && <p role="status">{copy.unsupportedSize}</p>}</>}
    {active && <div className="viewer-panel" ref={panel} onKeyDown={event => { if (event.key === 'Escape') { event.preventDefault(); void close(); } }}>
      <div className="viewer-heading"><div><p className="eyebrow">3D · LAAMACH MARBRE</p><h2>{copy.studioTitle}</h2></div><button ref={closeButton} type="button" className="viewer-exit" onClick={close}><SiteIcon name="arrow" className="project-view-previous directional" />{copy.photos}</button></div>
      <div className="viewer-stage"><div className="viewer-canvas" ref={host} data-viewer-host />
        {phase === 'ready' && measurementFrame && Object.keys(labelledDimensions || {}).length > 0 && <div className="viewer-dimension-overlay"><svg viewBox={`0 0 ${measurementFrame.width} ${measurementFrame.height}`} role="img" aria-label={copy.measuredDimensions}><DimensionLines frame={{ ...measurementFrame, dimensions: labelledDimensions || {} }} locale={locale} /></svg><span className="viewer-dimension-caption">{copy.measuredDimensions}</span></div>}
        {phase === 'ready' && !interacted && <div className="viewer-gesture-hint" aria-hidden="true"><svg className="gesture-hand" viewBox="0 0 36 36" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><path d="M17 23V9a2 2 0 0 1 4 0v10-4a2 2 0 0 1 4 0v5-3a2 2 0 0 1 4 0v5-2a2 2 0 0 1 4 0v7c0 5-3 7-8 7h-2c-3 0-5-2-7-5l-4-6a2 2 0 0 1 3-3l4 4M6 9l-3 3 3 3m24-6 3 3-3 3M3 12h7m16 0h7" /></svg><span>{copy.gestureHint}</span></div>}
        {phase !== 'ready' && <div className="viewer-fallback"><CatalogueImage media={configuration.poster || cover} placeholder={copy.canvas} /><p role={phase === 'error' ? 'alert' : 'status'}>{phase === 'loading' ? copy.loading : reason === 'webgl' ? copy.webgl : copy.unavailable}</p>{phase === 'error' && <button type="button" className="button bronze" onClick={() => { setPhase('loading'); setAttempt(n => n + 1); }}>{copy.retry}</button>}</div>}</div>
      {configuration.sizes.length > 0 && <label className="viewer-size">{copy.defaultSize}<select value={sizeId || configuration.default_size_id || ''} onChange={event => onSize(event.target.value)}>{configuration.sizes.map(size => <option key={size.id} value={size.id}>{size.label}</option>)}</select></label>}
      <p id={`${id}-help`} className="sr-only">{copy.keyboardHelp}</p>
    </div>}
  </div>;
}
