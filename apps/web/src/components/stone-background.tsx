'use client';

import { useEffect, useRef, type CSSProperties } from 'react';
import { usePathname } from 'next/navigation';
import media from '../../public/stone-background/manifest.json';

/** One decorative layer per locale layout. Content and all controls stay above it. */
export function StoneBackground() {
  const root = useRef<HTMLDivElement>(null);
  const pathname = usePathname();

  useEffect(() => {
    const element = root.current;
    if (!element) return;
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    const pointer = matchMedia('(hover: hover) and (pointer: fine)');
    const data = matchMedia('(prefers-reduced-data: reduce)');
    const connection = (navigator as Navigator & { connection?: EventTarget & { saveData?: boolean; effectiveType?: string } }).connection;
    const memory = (navigator as Navigator & { deviceMemory?: number }).deviceMemory;
    let frame = 0;
    let previousTime = 0;
    let width = innerWidth, height = innerHeight;
    let x = width * .54, y = height * .44, targetX = x, targetY = y;
    let visible = false;
    let permitted = false;
    const surfaces = document.querySelectorAll('[data-stone-surface]');
    const visibleSurfaces = new Set<Element>();
    const stop = () => { cancelAnimationFrame(frame); frame = 0; previousTime = 0; };
    const reset = () => {
      stop();
      element.dataset.active = 'false';
      element.style.removeProperty('--stone-x');
      element.style.removeProperty('--stone-y');
      element.style.removeProperty('--stone-tx');
      element.style.removeProperty('--stone-ty');
    };
    const tick = (now: number) => {
      frame = 0;
      if (!permitted || !visible || document.hidden) return;
      // Time-based easing gives the same gentle response on 60 Hz and 120 Hz displays.
      const ease = 1 - Math.exp(-Math.min(previousTime ? now - previousTime : 16, 48) / 120);
      previousTime = now;
      x += (targetX - x) * ease; y += (targetY - y) * ease;
      element.style.setProperty('--stone-x', `${x.toFixed(2)}px`);
      element.style.setProperty('--stone-y', `${y.toFixed(2)}px`);
      element.style.setProperty('--stone-tx', `${((x / width - .5) * -10).toFixed(2)}px`);
      element.style.setProperty('--stone-ty', `${((y / height - .5) * -8).toFixed(2)}px`);
      if (Math.abs(targetX - x) + Math.abs(targetY - y) > .15) frame = requestAnimationFrame(tick);
      else previousTime = 0;
    };
    const move = (event: PointerEvent) => {
      if (!permitted || !visible || document.hidden || event.pointerType !== 'mouse' || event.buttons) return;
      // Do not move the atmosphere while manipulating a viewer, gallery or form.
      if ((event.target as Element).closest('dialog, input, textarea, select, .product-viewer, .project-gallery, canvas, video')) return;
      targetX = event.clientX; targetY = event.clientY;
      element.dataset.active = 'true';
      if (!frame) frame = requestAnimationFrame(tick);
    };
    const preferences = () => {
      const economical = data.matches || connection?.saveData || /(^|-)2g$/.test(connection?.effectiveType || '') || (memory !== undefined && memory <= 2);
      permitted = pointer.matches && !motion.matches && !economical;
      element.dataset.economical = String(Boolean(economical));
      element.dataset.interactive = String(permitted);
      if (!permitted) reset();
    };
    const resize = () => {
      width = element.clientWidth; height = element.clientHeight;
      element.style.setProperty('--stone-width', `${width}px`);
      element.style.setProperty('--stone-height', `${height}px`);
      targetX = x = width * .54; targetY = y = height * .44; reset();
    };
    const visibility = () => { if (document.hidden) reset(); };
    const observer = new IntersectionObserver(entries => {
      for (const entry of entries) {
        if (entry.isIntersecting) visibleSurfaces.add(entry.target); else visibleSurfaces.delete(entry.target);
      }
      visible = visibleSurfaces.size > 0;
      if (!visible) reset();
    });
    surfaces.forEach(surface => observer.observe(surface));
    resize(); preferences();
    window.addEventListener('pointermove', move, { passive: true });
    window.addEventListener('resize', resize, { passive: true });
    window.addEventListener('blur', reset);
    document.documentElement.addEventListener('pointerleave', reset);
    document.addEventListener('visibilitychange', visibility);
    for (const query of [motion, pointer, data]) query.addEventListener('change', preferences);
    connection?.addEventListener('change', preferences);
    return () => {
      reset(); observer.disconnect();
      window.removeEventListener('pointermove', move); window.removeEventListener('resize', resize);
      window.removeEventListener('blur', reset); document.documentElement.removeEventListener('pointerleave', reset);
      document.removeEventListener('visibilitychange', visibility);
      for (const query of [motion, pointer, data]) query.removeEventListener('change', preferences);
      connection?.removeEventListener('change', preferences);
    };
  }, [pathname]);

  const variants = media.variants;
  return <><link rel="preload" as="image" href={variants.mobile.url} media="(max-width: 767px)" fetchPriority="high" />
    <link rel="preload" as="image" href={variants.tablet.url} media="(min-width: 768px) and (max-width: 1199px)" fetchPriority="high" />
    <link rel="preload" as="image" href={variants.desktop.url} media="(min-width: 1200px)" fetchPriority="high" />
    <div ref={root} className="stone-background" aria-hidden="true" style={{
    '--stone-base-mobile': `url("${variants.mobile.url}")`,
    '--stone-base-tablet': `url("${variants.tablet.url}")`,
    '--stone-base-desktop': `url("${variants.desktop.url}")`,
    '--stone-detail-desktop': `url("${variants.desktopDetail.url}")`,
    '--stone-detail-tablet': `url("${variants.tabletDetail.url}")`,
  } as CSSProperties}>
    <div className="stone-base" />
    <div className="stone-focus"><div className="stone-detail" /></div><div className="stone-light" />
  </div></>;
}
