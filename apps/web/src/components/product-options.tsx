'use client';

import { useState } from 'react';
import { catalogueCopy, selectedImage, type Product } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { siteCopy } from '@/lib/site';
import { SiteIcon } from './site-icon';

export function ProductOptions({ product, locale }: { product: Product; locale: string }) {
  const copy = catalogueCopy(locale);
  const [sizeId, setSizeId] = useState<string | null>(null);
  const [galleryId, setGalleryId] = useState<string | null>(null);
  const image = product.gallery.find(item => item.id === galleryId) || selectedImage(product, sizeId);
  const design = siteCopy(locale);
  return <section className="product-presentation">
    <div className="product-visual-column"><div className="product-image" data-product-image><CatalogueImage media={image} placeholder={copy.missingImage} eager /></div>
    {product.gallery.length > 0 && <div className="option-list gallery-thumbnails" aria-label={copy.gallery}><button type="button" aria-label={copy.mainImage} aria-pressed={galleryId === null} onClick={() => setGalleryId(null)}><CatalogueImage media={product.cover} placeholder={copy.missingImage} thumbnail /><span>{copy.mainImage}</span></button>{product.gallery.map((item, index) => <button key={item.id} type="button" aria-label={`${copy.image} ${index + 1}`} aria-pressed={galleryId === item.id} onClick={() => setGalleryId(item.id)}><CatalogueImage media={item} placeholder={copy.missingImage} thumbnail /><span>{copy.image} {index + 1}</span></button>)}</div>}
    <div className="reserved-viewer" data-reserved-3d><SiteIcon name="stone" /><div><h2>{design.viewerTitle}</h2><p>{design.viewerNote}</p></div><span aria-hidden="true">3D</span></div></div>
    <div className="product-facts"><p className="eyebrow">{product.parent_category.name} / {product.category.name}</p><h1>{product.name}</h1><p className="product-reference">{design.reference}: <bdi>{product.reference}</bdi></p>{product.description && <p className="preserve-text product-description">{product.description}</p>}
    {product.sizes.length > 0 && <fieldset><legend>{copy.sizes}</legend><div className="option-list">{product.sizes.map(size => <button key={size.id} type="button" data-size-id={size.id} aria-pressed={sizeId === size.id} onClick={() => { setSizeId(size.id); setGalleryId(null); }}>{size.label}</button>)}</div><p>{copy.sizeNote}</p></fieldset>}
    {!product.sizes.length && <p className="data-note">{design.noSizes}</p>}
    {product.custom_size_allowed && <div data-custom-size><p>{copy.custom}</p>{product.custom_size && <p>{copy.bounds}: {copy.width} <bdi>{product.custom_size.min_width}–{product.custom_size.max_width} {product.custom_size.measurement_unit}</bdi>; {copy.length} <bdi>{product.custom_size.min_length}–{product.custom_size.max_length} {product.custom_size.measurement_unit}</bdi></p>}</div>}
    {product.finishes.length > 0 && <label className="field-label">{copy.finish}<select defaultValue={product.finishes.find(item => item.is_default)?.id || product.finishes[0].id}>{product.finishes.map(finish => <option key={finish.id} value={finish.id}>{finish.name}</option>)}</select></label>}
    {!product.finishes.length && <p className="data-note">{design.noFinishes}</p>}
    <p className="product-units">{copy.units}: {product.quantity_units.map(unit => <bdi key={unit} className="unit-label">{unit}</bdi>)}</p>
    <a className="button bronze" href={`/${locale}/contact`}>{design.contactAction}<SiteIcon name="arrow" className="directional" /></a>
    </div>
  </section>;
}
