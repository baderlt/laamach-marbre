import { mountViewer, type ViewerApi } from './viewer-core.ts';
import { viewerCopy, viewerDimensions, type ViewerConfiguration } from './viewer.ts';
const element = document.querySelector<HTMLElement>('[data-admin-viewer]');
if (element) {
  const data = JSON.parse(document.getElementById('viewer-data')!.textContent!) as { config: ViewerConfiguration; locale: string };
  const copy = viewerCopy(data.locale), controller = new AbortController(); let api: ViewerApi | null = null;
  let selectedSize = data.config.default_size_id || '';
  const status = document.getElementById('viewer-status')!, host = document.getElementById('viewer-host')!;
  const start = document.createElement('button'); start.textContent = copy.open; element.prepend(start);
  const toolbar = document.createElement('div'); toolbar.className = 'viewer-toolbar'; element.append(toolbar);
  const help = document.createElement('p'); help.id = 'viewer-help'; help.textContent = `${copy.help} ${copy.touchHelp}`; element.append(help);
  const disclosure = document.createElement('p'); disclosure.textContent = data.config.mode === 'uploaded_glb' ? copy.original : copy.indicative; element.append(disclosure);
  function stop() { api?.dispose(); api = null; toolbar.replaceChildren(); start.hidden = false; status.textContent = ''; start.focus(); }
  function controls() {
    toolbar.replaceChildren();
    const actions: [string, () => void][] = [[copy.rotateLeft, () => api?.rotate(-.18)], [copy.rotateRight, () => api?.rotate(.18)], [copy.rotateUp, () => api?.rotate(0, -.12)], [copy.rotateDown, () => api?.rotate(0, .12)], [copy.zoomIn, () => api?.zoom(.85)], [copy.zoomOut, () => api?.zoom(1.15)], [copy.reset, () => api?.reset()]];
    for (const [label, action] of actions) { const button = document.createElement('button'); button.textContent = label; button.type = 'button'; button.addEventListener('click', action); toolbar.append(button); }
    const fullscreen = document.createElement('button'); fullscreen.textContent = copy.fullscreen; fullscreen.disabled = !document.fullscreenEnabled; fullscreen.addEventListener('click', () => { void (document.fullscreenElement ? document.exitFullscreen() : element!.requestFullscreen()).catch(() => {}); }); toolbar.append(fullscreen);
    document.onfullscreenchange = () => { fullscreen.textContent = document.fullscreenElement ? copy.exitFullscreen : copy.fullscreen; };
    let interactive = !matchMedia('(pointer: coarse)').matches && innerWidth >= 768;
    const touch = document.createElement('button'); touch.textContent = interactive ? copy.scroll : copy.touch; touch.setAttribute('aria-pressed', String(interactive)); touch.addEventListener('click', () => { interactive = !interactive; api?.setInteraction(interactive); touch.textContent = interactive ? copy.scroll : copy.touch; touch.setAttribute('aria-pressed', String(interactive)); }); toolbar.append(touch);
    const photos = document.createElement('button'); photos.textContent = copy.photos; photos.addEventListener('click', stop); toolbar.append(photos);
  }
  start.addEventListener('click', async () => {
    start.disabled = true; status.textContent = copy.loading;
    try { api?.dispose(); api = await mountViewer(host, data.config, viewerDimensions(data.config, selectedSize), controller.signal, copy.canvas, help.id, () => { status.textContent = copy.webgl; start.hidden = false; start.textContent = copy.retry; }); status.textContent = ''; start.hidden = true; controls(); toolbar.querySelector('button')?.focus(); }
    catch (error) { status.textContent = error instanceof Error && error.message === 'webgl' ? copy.webgl : copy.unavailable; start.textContent = copy.retry; }
    finally { start.disabled = false; }
  });
  element.addEventListener('keydown', event => { if (event.key === 'Escape' && api) { event.preventDefault(); stop(); } });
  const dimensionText = document.createElement('p'); element.append(dimensionText);
  function describeDimensions() { const dimensions = viewerDimensions(data.config, selectedSize); dimensionText.textContent = dimensions ? `${copy.dimensions}: ${[dimensions.width, dimensions.length, dimensions.depth].map(value => new Intl.NumberFormat(data.locale, { maximumSignificantDigits: 6 }).format(value)).join(' × ')}` : ''; }
  if (data.config.sizes.length) {
    const label = document.createElement('label'); label.textContent = copy.defaultSize; const select = document.createElement('select');
    for (const size of data.config.sizes) { const option = document.createElement('option'); option.value = size.id; option.textContent = size.label; select.append(option); }
    select.value = selectedSize; select.addEventListener('change', () => { selectedSize = select.value; const dimensions = viewerDimensions(data.config, selectedSize); if (dimensions) api?.setDimensions(dimensions); describeDimensions(); }); label.append(select); element.append(label);
  }
  describeDimensions();
  addEventListener('pagehide', () => { controller.abort(); api?.dispose(); }, { once: true });
}
