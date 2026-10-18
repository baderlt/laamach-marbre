import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import type { Dimensions, ViewerConfiguration } from './viewer.ts';

export type ViewerApi = { rotate(horizontal: number, vertical?: number): void; zoom(factor: number): void; reset(): void; setDimensions(dimensions: Dimensions): void; setInteraction(enabled: boolean): void; dispose(): void };
function disposeObject(object: THREE.Object3D) {
  const geometry = new Set<THREE.BufferGeometry>(), materials = new Set<THREE.Material>(), textures = new Set<THREE.Texture>();
  object.traverse(node => { const mesh = node as THREE.Mesh; if (!mesh.isMesh) return; geometry.add(mesh.geometry); for (const m of Array.isArray(mesh.material) ? mesh.material : [mesh.material]) materials.add(m); });
  for (const material of materials) for (const value of Object.values(material)) if (value?.isTexture) textures.add(value);
  const images = new Set<{ close?: () => void }>();
  for (const texture of textures) { texture.dispose(); if (texture.image) images.add(texture.image as { close?: () => void }); }
  for (const image of images) image.close?.();
  for (const material of materials) material.dispose(); for (const item of geometry) item.dispose();
}
async function boundedBytes(spec: { url: string; hash: string; bytes?: number }, limit: number, signal: AbortSignal, model = false) {
  const url = new URL(spec.url, location.href);
  if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || !/^[a-f0-9]{64}$/.test(spec.hash)) throw Error('asset');
  const response = await fetch(url, { signal, credentials: 'same-origin', redirect: 'error', cache: url.pathname.startsWith('/admin/') ? 'no-store' : 'default' });
  if (!response.ok || Number(response.headers.get('Content-Length') || 0) > limit || (model && !response.headers.get('Content-Type')?.startsWith('model/gltf-binary'))) throw Error('asset');
  const reader = response.body?.getReader(); if (!reader) throw Error('asset');
  const parts: Uint8Array[] = []; let length = 0;
  try { while (true) { const { done, value } = await reader.read(); if (done) break; length += value.byteLength; if (length > limit) { await reader.cancel(); throw Error('budget'); } parts.push(value); } } finally { reader.releaseLock(); }
  if (spec.bytes && length !== spec.bytes) throw Error('integrity');
  const bytes = new Uint8Array(length); let offset = 0; for (const part of parts) { bytes.set(part, offset); offset += part.length; }
  const hash = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', bytes)), n => n.toString(16).padStart(2, '0')).join(''); if (hash !== spec.hash) throw Error('integrity');
  return bytes;
}

/** Shared by the activated public viewer and same-origin authenticated admin preview. */
export async function mountViewer(host: HTMLElement, config: ViewerConfiguration, dimensions: Dimensions | null, signal: AbortSignal, label: string, helpId: string, onFailure: (reason: string) => void): Promise<ViewerApi> {
  if (signal.aborted) throw new DOMException('Aborted', 'AbortError');
  let renderer: THREE.WebGLRenderer;
  try { renderer = new THREE.WebGLRenderer({ alpha: false, antialias: true, powerPreference: 'low-power' }); } catch { throw Error('webgl'); }
  const canvas = renderer.domElement; canvas.tabIndex = 0; canvas.setAttribute('role', 'img'); canvas.setAttribute('aria-label', label); canvas.setAttribute('aria-describedby', helpId);
  renderer.setPixelRatio(Math.min(devicePixelRatio || 1, innerWidth < 768 ? 1.5 : 2)); renderer.outputColorSpace = THREE.SRGBColorSpace; renderer.toneMapping = THREE.ACESFilmicToneMapping; renderer.toneMappingExposure = 1;
  const scene = new THREE.Scene(); scene.background = new THREE.Color('#f1eee7');
  const camera = new THREE.PerspectiveCamera(40, 1, .001, 1000); const controls = new OrbitControls(camera, canvas);
  controls.enableDamping = false; controls.autoRotate = false; controls.enablePan = false; controls.minPolarAngle = .08; controls.maxPolarAngle = Math.PI - .08;
  if (config.camera.rotation === 'front') { controls.minAzimuthAngle = -Math.PI / 2; controls.maxAzimuthAngle = Math.PI / 2; }
  const mobile = matchMedia('(pointer: coarse)').matches || innerWidth < 768;
  let object: THREE.Object3D | null = null, disposed = false, frame = 0, frames = 0, baseDistance = 1, observer: ResizeObserver | undefined, target: THREE.WebGLRenderTarget | undefined;
  const root = new THREE.Group(); scene.add(root); scene.add(new THREE.HemisphereLight(0xffffff, 0xdedbd3, 2));
  const key = new THREE.DirectionalLight(0xffffff, 3); key.position.set(3, 5, 4); scene.add(key); const fill = new THREE.DirectionalLight(0xffffff, 1); fill.position.set(-3, 2, -4); scene.add(fill);
  const floor = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), new THREE.ShadowMaterial({ opacity: .12 })); floor.rotation.x = -Math.PI / 2; floor.receiveShadow = true; scene.add(floor);
  renderer.shadowMap.enabled = !mobile; renderer.shadowMap.type = THREE.PCFSoftShadowMap; key.castShadow = !mobile; key.shadow.mapSize.set(512, 512);
  host.append(canvas);
  function render() {
    frame = 0; if (disposed) return; renderer.render(scene, camera); frames++;
    host.dataset.viewerReady = 'true'; host.dataset.viewerFrames = String(frames); host.dataset.viewerTriangles = String(renderer.info.render.triangles); host.dataset.viewerCamera = JSON.stringify(camera.position.toArray());
  }
  function invalidate() { if (!disposed && !frame) frame = requestAnimationFrame(render); }
  function frameObject() {
    if (!object || disposed) return;
    root.position.set(0, 0, 0); const box = new THREE.Box3().setFromObject(root); const center = box.getCenter(new THREE.Vector3()); const size = box.getSize(new THREE.Vector3()); root.position.sub(center);
    const width = Math.max(host.clientWidth, 1), height = Math.max(host.clientHeight, 1); renderer.setSize(width, height, false); camera.aspect = width / height;
    const radius = Math.max(size.length() / 2, .000001); baseDistance = radius / Math.sin(THREE.MathUtils.degToRad(20)) * Math.max(1, 1 / camera.aspect) * 1.15;
    camera.near = Math.max(radius / 10000, 1e-8); camera.far = baseDistance * 20; camera.updateProjectionMatrix(); controls.target.set(0, 0, 0);
    const direction = config.camera.angle === 'front' ? new THREE.Vector3(0, .1, 1) : new THREE.Vector3(1, .85, 1); camera.position.copy(direction.normalize().multiplyScalar(baseDistance));
    controls.minDistance = baseDistance * config.camera.min_distance_factor; controls.maxDistance = baseDistance * config.camera.max_distance_factor; controls.update();
    floor.scale.setScalar(Math.max(...size.toArray()) * 5); floor.position.y = -size.y / 2 - radius * .002;
    key.position.set(radius * 3, radius * 5, radius * 4); key.shadow.camera.left = key.shadow.camera.bottom = -radius * 2; key.shadow.camera.right = key.shadow.camera.top = radius * 2; key.shadow.camera.near = radius / 100; key.shadow.camera.far = radius * 15; key.shadow.camera.updateProjectionMatrix();
    host.dataset.viewerDimensions = JSON.stringify(dimensions); invalidate();
  }
  function rotate(horizontal: number, vertical = 0) { if (disposed) return; const value = new THREE.Spherical().setFromVector3(camera.position.clone().sub(controls.target)); value.theta += horizontal; value.phi += vertical; value.makeSafe(); camera.position.copy(new THREE.Vector3().setFromSpherical(value).add(controls.target)); controls.update(); invalidate(); }
  function zoom(factor: number) { if (disposed) return; const value = camera.position.clone().sub(controls.target); value.multiplyScalar(factor); camera.position.copy(controls.target).add(value); controls.update(); invalidate(); }
  function interaction(enabled: boolean) { if (disposed) return; controls.enabled = enabled; canvas.style.touchAction = enabled ? 'none' : 'pan-y'; host.dataset.viewerInteraction = String(enabled); }
  function keyboard(event: KeyboardEvent) {
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', '+', '=', '-', 'Home'].includes(event.key)) return;
    event.preventDefault(); if (event.key === 'ArrowLeft') rotate(-.18); else if (event.key === 'ArrowRight') rotate(.18); else if (event.key === 'ArrowUp') rotate(0, -.12); else if (event.key === 'ArrowDown') rotate(0, .12); else if (event.key === 'Home') frameObject(); else zoom(['+', '='].includes(event.key) ? .85 : 1.15);
  }
  function contextLost(event: Event) { event.preventDefault(); if (!disposed) { onFailure('webgl'); dispose(); } }
  function dispose() {
    if (disposed) return; disposed = true; clearTimeout(timer); cancelAnimationFrame(frame); observer?.disconnect(); controls.removeEventListener('change', invalidate); controls.dispose(); canvas.removeEventListener('keydown', keyboard); canvas.removeEventListener('webglcontextlost', contextLost); signal.removeEventListener('abort', dispose); disposeObject(scene); target?.dispose(); key.shadow.map?.dispose(); renderer.dispose(); renderer.forceContextLoss(); root.clear(); scene.clear(); object = null; canvas.remove(); delete host.dataset.viewerReady;
  }
  signal.addEventListener('abort', dispose, { once: true }); canvas.addEventListener('keydown', keyboard); canvas.addEventListener('webglcontextlost', contextLost); controls.addEventListener('change', invalidate); interaction(!mobile);
  const timer = setTimeout(() => { if (!disposed) { onFailure('asset'); dispose(); } }, 15000);
  try {
    const generator = new THREE.PMREMGenerator(renderer), room = new RoomEnvironment(); target = generator.fromScene(room, .04, .1, 100, { size: 64 }); scene.environment = target.texture; scene.environmentIntensity = .7; room.dispose(); generator.dispose();
    const timeout = AbortSignal.any([signal, AbortSignal.timeout(15000)]);
    if (config.mode === 'uploaded_glb') {
      if (!config.model) throw Error('asset');
      const bytes = await boundedBytes(config.model, 8388608, timeout, true);
      const manager = new THREE.LoadingManager(); manager.setURLModifier(url => { if (!url.startsWith('blob:')) throw Error('external'); return url; });
      const gltf = await new GLTFLoader(manager).parseAsync(bytes.buffer as ArrayBuffer, ''); object = gltf.scene;
    } else {
      if (!dimensions || !config.material) throw Error('dimensions');
      let texture: THREE.Texture | null = null;
      if (config.material.texture) {
        const bytes = await boundedBytes(config.material.texture, 4194304, timeout);
        const bitmap = await createImageBitmap(new Blob([bytes as BlobPart]), { imageOrientation: 'flipY', premultiplyAlpha: 'none' });
        if (bitmap.width > 1600 || bitmap.height > 1600 || signal.aborted) { bitmap.close(); throw Error('texture'); }
        texture = new THREE.Texture(bitmap); texture.flipY = false; texture.colorSpace = THREE.SRGBColorSpace; texture.anisotropy = Math.min(4, renderer.capabilities.getMaxAnisotropy()); texture.needsUpdate = true;
      }
      const material = new THREE.MeshStandardMaterial({ color: texture ? 0xffffff : 0xc5c1b8, map: texture, roughness: config.material.roughness, metalness: 0 });
      object = new THREE.Mesh(new THREE.BoxGeometry(dimensions.width, dimensions.depth, dimensions.length), material);
    }
    if (disposed || timeout.aborted) { disposeObject(object); throw Error('aborted'); }
    object.traverse(node => { if ((node as THREE.Mesh).isMesh) { (node as THREE.Mesh).castShadow = true; (node as THREE.Mesh).receiveShadow = true; } }); root.add(object);
    clearTimeout(timer); frameObject(); observer = new ResizeObserver(frameObject); observer.observe(host); cancelAnimationFrame(frame); render();
    return { rotate, zoom, reset: frameObject, setInteraction: interaction, setDimensions(value) { if (disposed || config.mode !== 'procedural' || !(object instanceof THREE.Mesh)) return; dimensions = value; object.geometry.dispose(); object.geometry = new THREE.BoxGeometry(value.width, value.depth, value.length); frameObject(); }, dispose };
  } catch (error) { dispose(); throw error; }
}
