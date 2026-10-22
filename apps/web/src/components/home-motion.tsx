'use client';

import { useEffect } from 'react';
import { gsap } from 'gsap';

/** Progressive enhancement: server-rendered cards remain visible without motion or JS. */
export function HomeMotion() {
  useEffect(() => {
    const root = document.querySelector('.homepage');
    if (!root) return;
    const media = gsap.matchMedia();
    media.add('(prefers-reduced-motion: no-preference)', () => {
      const context = gsap.context(() => {}, root);
      const observer = new IntersectionObserver(entries => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          context.add(() => {
            gsap.from(entry.target.children, { y: 24, opacity: 0, duration: .7, stagger: .09, ease: 'power2.out', clearProps: 'transform,opacity' });
          });
          observer.unobserve(entry.target);
        }
      }, { threshold: .08 });
      root.querySelectorAll('.hero-paths, .collection-grid, .activities-grid, .compact-grid, .trust-grid, .home-usage-links').forEach(group => observer.observe(group));
      const cleanups: (() => void)[] = [];
      root.querySelectorAll<HTMLElement>('.hero-path, .collection-card, .home-usage-links > a, .product-card, .activities-grid .editorial-card, .trust-grid .editorial-card').forEach(card => {
        const image = card.querySelector('img, .site-icon');
        if (!image) return;
        const enter = () => context.add(() => {
          gsap.to(image, { scale: 1.045, duration: .65, ease: 'power2.out', overwrite: true });
        });
        const leave = () => context.add(() => {
          gsap.to(image, { scale: 1, duration: .45, ease: 'power2.out', overwrite: true });
        });
        const pointerEnter = (event: PointerEvent) => { if (event.pointerType === 'mouse') enter(); };
        const focusOut = (event: FocusEvent) => { if (!card.contains(event.relatedTarget as Node | null)) leave(); };
        card.addEventListener('pointerenter', pointerEnter);
        card.addEventListener('pointerleave', leave);
        card.addEventListener('focusin', enter);
        card.addEventListener('focusout', focusOut);
        cleanups.push(() => {
          card.removeEventListener('pointerenter', pointerEnter);
          card.removeEventListener('pointerleave', leave);
          card.removeEventListener('focusin', enter);
          card.removeEventListener('focusout', focusOut);
        });
      });
      return () => { observer.disconnect(); cleanups.forEach(cleanup => cleanup()); context.revert(); };
    });
    return () => media.revert();
  }, []);
  return null;
}
