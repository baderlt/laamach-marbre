'use client';

import { useEffect, useRef, useState, type ReactNode } from 'react';
import { SiteIcon } from './site-icon';

export function HomeProductCarousel({ children, locale, count, title }: { children: ReactNode; locale: string; count: number; title: string }) {
  const rail = useRef<HTMLDivElement>(null);
  const [position, setPosition] = useState({ index: 0, end: true, overflow: false });
  const drag = useRef<{ x: number; left: number; moved: boolean } | null>(null);
  const settleFrame = useRef<number | null>(null);
  const lastWheel = useRef(-Infinity);
  const [dragging, setDragging] = useState(false);
  const ar = locale === 'ar';
  const stride = (node: HTMLElement) => {
    const card = node.querySelector<HTMLElement>('.product-card');
    const grid = node.querySelector('.product-grid');
    return card ? card.getBoundingClientRect().width + (grid ? parseFloat(getComputedStyle(grid).columnGap) || 0 : 0) : node.clientWidth;
  };
  const move = (direction: number) => {
    const node = rail.current;
    if (!node) return;
    if (settleFrame.current !== null) cancelAnimationFrame(settleFrame.current);
    node.scrollBy({ left: direction * (ar ? -1 : 1) * stride(node), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  };

  useEffect(() => {
    const node = rail.current;
    if (!node) return;
    const update = () => {
      const maximum = node.scrollWidth - node.clientWidth;
      const offset = Math.abs(node.scrollLeft);
      setPosition({ index: Math.round(offset / stride(node)), end: offset >= maximum - 2, overflow: maximum > 2 });
    };
    const observer = new ResizeObserver(update);
    observer.observe(node);
    node.addEventListener('scroll', update, { passive: true });
    const onWheel = (event: WheelEvent) => {
      if (event.ctrlKey || event.shiftKey || Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;
      const maximum = node.scrollWidth - node.clientWidth;
      const offset = Math.abs(node.scrollLeft);
      const direction = Math.sign(event.deltaY);
      if (maximum <= 2 || (direction > 0 && offset >= maximum - 2) || (direction < 0 && offset <= 2)) return;
      event.preventDefault();
      if (performance.now() - lastWheel.current < 420) return;
      lastWheel.current = performance.now();
      if (settleFrame.current !== null) cancelAnimationFrame(settleFrame.current);
      node.scrollBy({ left: direction * (ar ? -1 : 1) * stride(node), behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    };
    node.addEventListener('wheel', onWheel, { passive: false });
    return () => { observer.disconnect(); node.removeEventListener('scroll', update); node.removeEventListener('wheel', onWheel); if (settleFrame.current !== null) cancelAnimationFrame(settleFrame.current); };
  }, [ar]);
  const finishDrag = (node: HTMLDivElement, pointerId: number, cancelled = false) => {
    if (!drag.current) return;
    const moved = drag.current.moved;
    setDragging(false);
    if (node.hasPointerCapture(pointerId)) node.releasePointerCapture(pointerId);
    if (moved && !cancelled) {
      const distance = stride(node);
      const target = Math.round(Math.abs(node.scrollLeft) / distance) * distance * (ar ? -1 : 1);
      settleFrame.current = requestAnimationFrame(() => { settleFrame.current = null; node.scrollTo({ left: target, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' }); });
    }
    if (!moved || cancelled) drag.current = null;
  };

  return <div className="home-products-carousel" role="region" aria-roledescription={ar ? 'عارض شرائح' : 'carrousel'} aria-label={title} data-count={count}>
    {count > 1 && <div className="home-carousel-controls"><div className="home-carousel-navigation">
      <button type="button" onClick={() => move(-1)} disabled={!position.overflow || position.index === 0} aria-label={ar ? 'المواد السابقة' : 'Matières précédentes'}><SiteIcon name="arrow" className="directional carousel-back" /></button>
      <span className="home-carousel-position" aria-live="polite"><bdi>{String(position.index + 1).padStart(2, '0')} / {String(count).padStart(2, '0')}</bdi></span>
      <button type="button" onClick={() => move(1)} disabled={!position.overflow || position.end} aria-label={ar ? 'المواد التالية' : 'Matières suivantes'}><SiteIcon name="arrow" className="directional" /></button>
    </div></div>}
    <div ref={rail} className="home-product-track" data-dragging={dragging} tabIndex={0} aria-label={ar ? 'اسحب أو استخدم الأسهم لاكتشاف المواد' : 'Glissez ou utilisez les flèches pour découvrir les matières'} onPointerDown={event => {
      if (event.pointerType !== 'mouse' || event.button !== 0) { drag.current = null; return; }
      if (settleFrame.current !== null) cancelAnimationFrame(settleFrame.current);
      drag.current = { x: event.clientX, left: event.currentTarget.scrollLeft, moved: false };
    }} onPointerMove={event => {
      if (!drag.current || event.pointerType !== 'mouse' || !(event.buttons & 1)) return;
      const distance = event.clientX - drag.current.x;
      if (!drag.current.moved && Math.abs(distance) < 6) return;
      if (!drag.current.moved) { drag.current.moved = true; setDragging(true); event.currentTarget.setPointerCapture(event.pointerId); }
      event.preventDefault();
      event.currentTarget.scrollTo({ left: drag.current.left - distance, behavior: 'instant' });
    }} onPointerUp={event => { if (event.pointerType === 'mouse') finishDrag(event.currentTarget, event.pointerId); }} onPointerCancel={event => finishDrag(event.currentTarget, event.pointerId, true)} onDragStart={event => event.preventDefault()} onClickCapture={event => {
      if (drag.current?.moved) { event.preventDefault(); event.stopPropagation(); drag.current = null; }
    }} onKeyDown={event => {
      if (event.target !== event.currentTarget || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
      event.preventDefault();
      move(event.key === (ar ? 'ArrowLeft' : 'ArrowRight') ? 1 : -1);
    }}>{children}</div>
  </div>;
}
