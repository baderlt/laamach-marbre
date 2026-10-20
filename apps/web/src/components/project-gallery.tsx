'use client';

import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import type { Project } from '@/lib/projects';
import { projectsCopy } from '@/lib/projects';
import { catalogueCopy } from '@/lib/catalogue';
import { trapDialogFocus } from '@/lib/dialog-focus';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';
import { gsap } from 'gsap';

export function ProjectGallery({ project, locale }: { project: Project; locale: string }) {
  const [selected, setSelected] = useState(-1);
  const [expanded, setExpanded] = useState(false);
  const stage = useRef<HTMLDivElement>(null);
  const player = useRef<HTMLVideoElement>(null);
  const modalPlayer = useRef<HTMLVideoElement>(null);
  const dialog = useRef<HTMLDialogElement>(null);
  const copy = projectsCopy(locale);
  const arabic = locale === 'ar';
  const item = project.gallery[selected];
  const video = item?.type === 'video' ? item.media.variants.find(variant => variant.format === 'mp4') : null;
  const poster = item?.poster?.variants.find(variant => variant.format === 'webp');
  const alt = item?.caption || project.title;
  const pause = () => { player.current?.pause(); modalPlayer.current?.pause(); };
  const select = (index: number) => { pause(); setSelected(index); };
  const move = (direction: number) => select(((selected + 1 + direction + project.gallery.length + 1) % (project.gallery.length + 1)) - 1);
  const keyboard = (event: KeyboardEvent<HTMLElement>) => {
    if (event.target instanceof HTMLVideoElement) return; // Preserve native video seek/volume keys.
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      event.preventDefault();
      move((event.key === 'ArrowRight' ? 1 : -1) * (arabic ? -1 : 1));
    }
  };
  useEffect(() => {
    const hide = () => { if (document.hidden) { player.current?.pause(); modalPlayer.current?.pause(); } };
    document.addEventListener('visibilitychange', hide);
    return () => document.removeEventListener('visibilitychange', hide);
  }, []);
  useEffect(() => {
    if (project.gallery[selected]?.type === 'video') return;
    const image = stage.current?.querySelector('img');
    if (!image) return;
    const media = gsap.matchMedia();
    media.add('(prefers-reduced-motion: no-preference)', () => {
      const tween = gsap.from(image, { opacity: .45, scale: 1.012, duration: .4, ease: 'power2.out', clearProps: 'transform,opacity' });
      return () => tween.revert();
    });
    return () => media.revert();
  }, [selected, project.gallery]);
  const picture = <CatalogueImage media={item?.type === 'image' ? item.media : project.cover} alt={alt} placeholder={catalogueCopy(locale).missingImage} eager />;
  const controls = <div className="project-gallery-toolbar">
    <button type="button" className="icon-button" onClick={() => move(-1)} aria-label={arabic ? 'السابق' : 'Vue précédente'}><SiteIcon name="arrow" className="project-view-previous directional" /></button>
    <span role="status"><bdi dir="ltr">{selected + 2} / {project.gallery.length + 1}</bdi> · {item?.type === 'video' ? copy.video : copy.photo}</span>
    <button type="button" className="icon-button" onClick={() => move(1)} aria-label={arabic ? 'التالي' : 'Vue suivante'}><SiteIcon name="arrow" className="directional" /></button>
  </div>;
  return <section aria-label={copy.gallery} className="project-gallery" onKeyDown={keyboard}>
    <div className="project-gallery-stage" ref={stage}>
      <div className="product-image">
        {item?.type === 'video' ? video ? <video ref={player} key={item.media.id} controls playsInline preload="none" poster={poster?.url} aria-label={alt}><source src={video.url} type="video/mp4" />{copy.missingVideo}</video> : <p>{copy.missingVideo}</p> : picture}
      </div>
      <button type="button" className="gallery-expand button outline" onClick={() => { pause(); setExpanded(true); dialog.current?.showModal(); }}><SiteIcon name="search" />{arabic ? 'عرض بملء الشاشة' : 'Agrandir la vue'}</button>
    </div>
    {controls}
    {item?.caption && <p className="preserve-text gallery-caption">{item.caption}</p>}
    {!!project.gallery.length && <div className="option-list project-gallery-choices" data-motion-group role="group" aria-label={arabic ? 'اختيار صورة أو فيديو' : 'Choisir une photo ou une vidéo'}>
      <button type="button" aria-pressed={selected === -1} aria-label={copy.cover} onClick={() => select(-1)}><CatalogueImage media={project.cover} alt="" placeholder={catalogueCopy(locale).missingImage} thumbnail /></button>
      {project.gallery.map((entry, index) => <button key={entry.media.id} type="button" aria-pressed={selected === index} aria-label={`${entry.type === 'video' ? copy.video : copy.photo} ${index + 1}`} onClick={() => select(index)}>
        {entry.type === 'image' ? <CatalogueImage media={entry.media} alt="" placeholder={catalogueCopy(locale).missingImage} thumbnail /> : entry.poster ? <CatalogueImage media={entry.poster} alt="" placeholder={copy.missingVideo} thumbnail /> : <div className="video-placeholder" aria-hidden="true">▷</div>}
        {entry.type === 'video' && <span className="gallery-video-mark" aria-hidden="true">▷</span>}
      </button>)}
    </div>}
    <dialog ref={dialog} className="project-lightbox" aria-label={copy.gallery} onKeyDown={trapDialogFocus} onClose={() => { pause(); setExpanded(false); }} onCancel={pause} onClick={event => { if (event.target === event.currentTarget) dialog.current?.close(); }}>
      <div className="lightbox-heading"><span>{project.title}</span><button type="button" className="icon-button" autoFocus onClick={() => dialog.current?.close()} aria-label={arabic ? 'إغلاق' : 'Fermer'}><SiteIcon name="close" /></button></div>
      <div className="lightbox-content">{expanded && (item?.type === 'video' && video ? <video ref={modalPlayer} key={item.media.id} controls playsInline preload="none" poster={poster?.url} aria-label={alt}><source src={video.url} type="video/mp4" /></video> : picture)}</div>
      {controls}
    </dialog>
  </section>;
}
