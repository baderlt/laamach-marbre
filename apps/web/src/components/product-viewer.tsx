'use client';
import { useEffect, useId, useRef, useState, type ReactNode } from 'react';
import { CatalogueImage } from './catalogue-image';
import type { Media } from '@/lib/catalogue';
import { viewerCopy, viewerDimensions, type ViewerConfiguration } from '@/lib/viewer';
import type { ViewerApi } from '@/lib/viewer-core';

type Props = { configuration?: ViewerConfiguration | null; locale: string; cover: Media | null; sizeId?: string | null; onSize(value: string): void; children: ReactNode };
export function ProductViewer(props: Props) {
  return props.configuration ? <InteractiveViewer {...props} configuration={props.configuration} /> : props.children;
}
function InteractiveViewer({ configuration, locale, cover, sizeId, onSize, children }: Props & { configuration: ViewerConfiguration }) {
  const copy = viewerCopy(locale), id = useId();
  const [opened, setOpened] = useState(false), [phase, setPhase] = useState<'loading' | 'ready' | 'error'>('loading'), [reason, setReason] = useState(''), [attempt, setAttempt] = useState(0), [fullscreen, setFullscreen] = useState(false), [touch, setTouch] = useState(false);
  const host = useRef<HTMLDivElement>(null), panel = useRef<HTMLDivElement>(null), trigger = useRef<HTMLButtonElement>(null), closeButton = useRef<HTMLButtonElement>(null), api = useRef<ViewerApi | null>(null);
  const dimensions = viewerDimensions(configuration, sizeId), dimensionRef = useRef(dimensions);
  const supported = configuration.mode === 'uploaded_glb' || !!dimensions, active = opened && supported;
  useEffect(() => { dimensionRef.current = dimensions; if (dimensions) api.current?.setDimensions(dimensions); }, [dimensions]);
  useEffect(() => {
    if (!active || !host.current) return;
    const controller = new AbortController(), element = host.current;
    // No runtime or model import occurs before explicit activation.
    import('@/lib/viewer-core').then(module => module.mountViewer(element, configuration, dimensionRef.current, controller.signal, copy.canvas, `${id}-help`, failure => { setReason(failure); setPhase('error'); })).then(viewer => {
      if (controller.signal.aborted) { viewer.dispose(); return; } api.current = viewer; if (dimensionRef.current) viewer.setDimensions(dimensionRef.current); setPhase('ready');
    }).catch(error => { if (!controller.signal.aborted) { setReason(error instanceof Error ? error.message : 'asset'); setPhase('error'); } });
    return () => { controller.abort(); api.current?.dispose(); api.current = null; };
  }, [active, configuration, attempt, copy.canvas, id]);
  useEffect(() => { const changed = () => setFullscreen(document.fullscreenElement === panel.current); document.addEventListener('fullscreenchange', changed); return () => document.removeEventListener('fullscreenchange', changed); }, []);
  function control(action: string) {
    const viewer = api.current;
    if (action === 'rotateLeft') viewer?.rotate(-.18);
    else if (action === 'rotateRight') viewer?.rotate(.18);
    else if (action === 'rotateUp') viewer?.rotate(0, -.12);
    else if (action === 'rotateDown') viewer?.rotate(0, .12);
    else viewer?.zoom(action === 'zoomIn' ? .85 : 1.15);
  }
  async function close() { if (document.fullscreenElement === panel.current) await document.exitFullscreen().catch(() => {}); setOpened(false); setTouch(false); requestAnimationFrame(() => trigger.current?.focus({ preventScroll: true })); }
  return <div className="product-viewer">
    <div data-viewer-photos hidden={active}>{children}</div>
    {!active && <><button className="button outline viewer-activation" ref={trigger} type="button" data-viewer-activate disabled={!supported} onClick={() => { setPhase('loading'); setReason(''); setOpened(true); requestAnimationFrame(() => closeButton.current?.focus()); }}>{copy.open}</button>{!supported && <p role="status">{copy.unsupportedSize}</p>}</>}
    {active && <div className="viewer-panel" ref={panel} onKeyDown={event => { if (event.key === 'Escape') { event.preventDefault(); void close(); } }}>
      <div className="viewer-heading"><h2>{copy.canvas}</h2><button ref={closeButton} type="button" className="button outline" onClick={() => void close()}>{copy.photos}</button></div>
      <div className="viewer-stage"><div className="viewer-canvas" ref={host} data-viewer-host />{phase !== 'ready' && <div className="viewer-fallback"><CatalogueImage media={configuration.poster || cover} placeholder={copy.canvas} /><p role={phase === 'error' ? 'alert' : 'status'}>{phase === 'loading' ? copy.loading : reason === 'webgl' ? copy.webgl : copy.unavailable}</p>{phase === 'error' && <button type="button" className="button bronze" onClick={() => { setPhase('loading'); setAttempt(n => n + 1); }}>{copy.retry}</button>}</div>}</div>
      <div className="viewer-toolbar" role="group" aria-label={copy.canvas}>
        {([['rotateLeft', '←'], ['rotateRight', '→'], ['rotateUp', '↑'], ['rotateDown', '↓'], ['zoomIn', '+'], ['zoomOut', '−']] as const).map(([key, icon]) => <button type="button" key={key} aria-label={copy[key]} title={copy[key]} disabled={phase !== 'ready'} onClick={() => control(key)}><span aria-hidden="true" dir="ltr">{icon}</span></button>)}
        <button type="button" disabled={phase !== 'ready'} onClick={() => api.current?.reset()}>{copy.reset}</button>
        <button type="button" disabled={!document.fullscreenEnabled} onClick={async () => { try { if (fullscreen) await document.exitFullscreen(); else await panel.current?.requestFullscreen(); } catch { /* The browser may deny fullscreen; inline view stays usable. */ } }}>{fullscreen ? copy.exitFullscreen : copy.fullscreen}</button>
      </div>
      <button type="button" className="button outline viewer-touch-toggle" disabled={phase !== 'ready'} aria-pressed={touch} onClick={() => { api.current?.setInteraction(!touch); setTouch(value => !value); }}>{touch ? copy.scroll : copy.touch}</button>
      {configuration.sizes.length > 0 && <label className="viewer-size">{copy.defaultSize}<select value={sizeId || configuration.default_size_id || ''} onChange={event => onSize(event.target.value)}>{configuration.sizes.map(size => <option key={size.id} value={size.id}>{size.label}</option>)}</select></label>}
      {dimensions && <p className="viewer-dimensions">{copy.dimensions}: <bdi dir="ltr">{[dimensions.width, dimensions.length, dimensions.depth].map(value => new Intl.NumberFormat(locale, { maximumSignificantDigits: 6 }).format(value)).join(' × ')}</bdi></p>}
      <p id={`${id}-help`} className="viewer-help">{copy.help}<span className="viewer-touch-help"> {copy.touchHelp}</span></p><p className="viewer-disclosure">{configuration.mode === 'uploaded_glb' ? copy.original : copy.indicative}</p>
    </div>}
  </div>;
}
