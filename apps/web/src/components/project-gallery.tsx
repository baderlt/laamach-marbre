'use client';

import { useEffect, useRef, useState } from 'react';
import type { Project } from '@/lib/projects';
import { projectsCopy } from '@/lib/projects';
import { catalogueCopy } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { SiteIcon } from './site-icon';

export function ProjectGallery({ project, locale }: { project: Project; locale: string }) {
  const [selected, setSelected] = useState(-1);
  const player = useRef<HTMLVideoElement>(null);
  const copy = projectsCopy(locale);
  const item = project.gallery[selected];
  const video = item?.type === 'video' ? item.media.variants.find(variant => variant.format === 'mp4') : null;
  const poster = item?.poster?.variants.find(variant => variant.format === 'webp');
  const select = (index: number) => { player.current?.pause(); setSelected(index); };
  useEffect(() => {
    const pause = () => { if (document.hidden) player.current?.pause(); };
    document.addEventListener('visibilitychange', pause);
    return () => document.removeEventListener('visibilitychange', pause);
  }, []);
  // Changing selection unmounts the previous video and stops playback.
  return <section aria-label={copy.gallery} className="project-gallery">
    <div className="product-image" aria-live="polite">
      {item?.type === 'video' ? video ? <video ref={player} key={item.media.id} controls playsInline preload="none" poster={poster?.url} aria-label={item.caption || copy.missingVideo}><source src={video.url} type="video/mp4" />{copy.missingVideo}</video> : <p>{copy.missingVideo}</p> : <CatalogueImage media={item?.type === 'image' ? item.media : project.cover} alt={item?.caption || project.title} placeholder={catalogueCopy(locale).missingImage} eager />}
    </div>
    {item?.caption && <p className="preserve-text gallery-caption">{item.caption}</p>}
    {!!project.gallery.length && <div className="option-list project-gallery-choices"><button type="button" aria-pressed={selected === -1} aria-label={copy.cover} onClick={() => select(-1)}><CatalogueImage media={project.cover} alt={project.title} placeholder={catalogueCopy(locale).missingImage} thumbnail /><span>{copy.cover}</span></button>{project.gallery.map((entry, index) => <button key={entry.media.id} type="button" aria-pressed={selected === index} aria-label={`${entry.type === 'video' ? copy.video : copy.photo} ${index + 1}`} onClick={() => select(index)}>{entry.type === 'image' ? <CatalogueImage media={entry.media} alt={entry.caption || `${copy.photo} ${index + 1}`} placeholder={catalogueCopy(locale).missingImage} thumbnail /> : entry.poster ? <CatalogueImage media={entry.poster} alt={entry.caption || `${copy.video} ${index + 1}`} placeholder={copy.missingVideo} thumbnail /> : <div className="video-placeholder" aria-hidden="true">▷</div>}<span>{entry.type === 'video' ? copy.video : copy.photo} {index + 1}</span></button>)}</div>}
    <div className="gallery-architecture-placeholders" aria-hidden="true">{Array.from({ length: 4 }, (_, index) => <div className={`architectural-study study-${index % 3}`} key={index}><i /><i /><SiteIcon name="building" /></div>)}</div>
  </section>;
}
