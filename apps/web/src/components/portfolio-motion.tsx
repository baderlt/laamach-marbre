'use client';

import { useEffect, useRef } from 'react';
import { gsap } from 'gsap';

export function PortfolioMotion({ identity }: { identity: string }) {
  const marker = useRef<HTMLSpanElement>(null);
  useEffect(() => {
    const root = marker.current?.closest('main');
    if (!root) return;
    const media = gsap.matchMedia();
    media.add('(prefers-reduced-motion: no-preference)', () => {
      const context = gsap.context(() => {}, root);
      const observer = new IntersectionObserver(entries => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          context.add(() => gsap.from(entry.target.children, { y: 18, opacity: 0, duration: .55, stagger: .06, ease: 'power2.out', clearProps: 'transform,opacity' }));
          observer.unobserve(entry.target);
        }
      }, { threshold: .05 });
      root.querySelectorAll('[data-motion-group], .project-grid, .related-products .product-grid').forEach(element => observer.observe(element));
      return () => { observer.disconnect(); context.revert(); };
    });
    return () => media.revert();
  }, [identity]);
  return <span ref={marker} hidden />;
}
