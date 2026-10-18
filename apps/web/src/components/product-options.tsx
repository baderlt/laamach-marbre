'use client';

import { trapDialogFocus } from '@/lib/dialog-focus';

import { useRef, useState } from 'react';
import { catalogueCopy, selectedImage, type Product } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { siteCopy } from '@/lib/site';
import { SiteIcon } from './site-icon';
import { RequestDraft } from './request-draft';

export function ProductOptions({ product, locale, technical, related, pendingSpecification = false }: { product: Product; locale: string; technical?: React.ReactNode; related?: React.ReactNode; pendingSpecification?: boolean }) {
  const copy = catalogueCopy(locale);
  const [sizeId, setSizeId] = useState<string | null>(null);
  const [finishId, setFinishId] = useState(product.finishes.find(item => item.is_default)?.id || product.finishes[0]?.id || '');
  const [galleryId, setGalleryId] = useState<string | null>(null);
  const image = product.gallery.find(item => item.id === galleryId) || selectedImage(product, sizeId);
  const design = siteCopy(locale);
  const lightbox = useRef<HTMLDialogElement>(null);
  const moveImage = (step: number) => { const ids = [null, ...product.gallery.map(item => item.id)]; setGalleryId(ids[(ids.indexOf(galleryId) + step + ids.length) % ids.length]); };
  return <section className="product-presentation">
    <div className="product-visual-column"><div className="product-image" data-product-image><CatalogueImage media={image} placeholder={copy.missingImage} alt={`${product.name} — ${copy.mainImage}`} eager /><span className="photo-note">{design.materialCardNote}</span>{image && <button className="gallery-zoom" type="button" aria-label={design.galleryView} onClick={() => lightbox.current?.showModal()}>↗</button>}{product.gallery.length > 0 && <><button className="gallery-previous" type="button" aria-label={copy.previous} onClick={() => moveImage(-1)}>‹</button><button className="gallery-next" type="button" aria-label={copy.next} onClick={() => moveImage(1)}>›</button></>}</div>
    {product.gallery.length > 0 && <div className="option-list gallery-thumbnails" aria-label={copy.gallery}><button type="button" aria-label={copy.mainImage} aria-pressed={galleryId === null} onClick={() => setGalleryId(null)}><CatalogueImage media={product.cover} placeholder={copy.missingImage} thumbnail /><span>{copy.mainImage}</span></button>{product.gallery.map((item, index) => <button key={item.id} type="button" aria-label={`${copy.image} ${index + 1}`} aria-pressed={galleryId === item.id} onClick={() => setGalleryId(item.id)}><CatalogueImage media={item} placeholder={copy.missingImage} thumbnail /><span>{copy.image} {index + 1}</span></button>)}</div>}
    </div>
    <div className="product-facts"><p className="eyebrow">{product.parent_category.name} / {product.category.name}</p><h1>{product.name}</h1><p className="product-reference">{design.reference}: <bdi>{product.reference}</bdi></p>{product.description && <p className="preserve-text product-description">{product.description}</p>}
    {product.sizes.length > 0 && <fieldset><legend>{copy.sizes}</legend><div className="option-list">{product.sizes.map(size => <button key={size.id} type="button" data-size-id={size.id} aria-pressed={sizeId === size.id} onClick={() => { setSizeId(size.id); setGalleryId(null); }}>{size.label}</button>)}</div><p>{copy.sizeNote}</p></fieldset>}
    {!product.sizes.length && <div className="pending-formats"><h2>{copy.sizes}</h2><div>{[copy.width, copy.length, locale === 'ar' ? 'السماكة' : 'Épaisseur'].map(label => <span key={label}>{label}<b>—</b></span>)}</div><p>{design.noSizes}</p></div>}
    {product.custom_size_allowed && <div data-custom-size><p>{copy.custom}</p>{product.custom_size && <p>{copy.bounds}: {copy.width} <bdi>{product.custom_size.min_width}–{product.custom_size.max_width} {product.custom_size.measurement_unit}</bdi>; {copy.length} <bdi>{product.custom_size.min_length}–{product.custom_size.max_length} {product.custom_size.measurement_unit}</bdi></p>}</div>}
    {product.finishes.length > 0 && <label className="field-label">{copy.finish}<select value={finishId} onChange={event => setFinishId(event.target.value)}>{product.finishes.map(finish => <option key={finish.id} value={finish.id}>{finish.name}</option>)}</select></label>}
    {!product.finishes.length && <p className="data-note">{design.noFinishes}</p>}
    <p className="product-units">{copy.units}: {pendingSpecification ? <span>{design.noUnits}</span> : product.quantity_units.map(unit => <bdi key={unit} className="unit-label">{unit}</bdi>)}</p>
    <div className="material-request-actions"><RequestDraft locale={locale} kind="material" identity={{ title: product.name, reference: product.reference }} product={product} pending={pendingSpecification} sizeId={sizeId} finishId={finishId} onSize={value => { setSizeId(value); setGalleryId(null); }} onFinish={setFinishId} /><RequestDraft locale={locale} kind="application" identity={{ title: product.name, reference: product.reference }} /></div>
    <div className="specification-note"><SiteIcon name="ruler" /><p>{design.sizeGuidance}<br />{design.finishGuidance}</p></div>
    {related}
    </div>
    <div className="product-support-column"><div className="reserved-viewer" data-reserved-3d><div className="viewer-caption"><strong>3D</strong><h2>{design.viewerTitle}</h2><p>{design.viewerNote}</p><span>{design.viewerLater}</span></div><div className="stone-study" aria-hidden="true"><i /><i /><i /></div></div>{technical}</div>
    <dialog onKeyDown={trapDialogFocus} ref={lightbox} className="image-lightbox" aria-label={design.galleryView}><button type="button" className="icon-button" onClick={() => lightbox.current?.close()} aria-label={design.close}>×</button><CatalogueImage media={image} placeholder={copy.missingImage} alt={product.name} eager /></dialog>
  </section>;
}
