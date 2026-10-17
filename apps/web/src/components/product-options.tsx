'use client';

import { useState } from 'react';
import { catalogueCopy, selectedImage, type Product } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';

export function ProductOptions({ product, locale }: { product: Product; locale: string }) {
  const copy = catalogueCopy(locale);
  const [sizeId, setSizeId] = useState<string | null>(null);
  const [galleryId, setGalleryId] = useState<string | null>(null);
  const image = product.gallery.find(item => item.id === galleryId) || selectedImage(product, sizeId);
  return <section>
    <div className="product-image" data-product-image><CatalogueImage media={image} placeholder={copy.missingImage} eager /></div>
    {product.gallery.length > 0 && <div className="option-list" aria-label={copy.gallery}><button type="button" aria-pressed={galleryId === null} onClick={() => setGalleryId(null)}>{copy.mainImage}</button>{product.gallery.map((item, index) => <button key={item.id} type="button" aria-pressed={galleryId === item.id} onClick={() => setGalleryId(item.id)}>{copy.image} {index + 1}</button>)}</div>}
    {product.sizes.length > 0 && <fieldset><legend>{copy.sizes}</legend><div className="option-list">{product.sizes.map(size => <button key={size.id} type="button" data-size-id={size.id} aria-pressed={sizeId === size.id} onClick={() => { setSizeId(size.id); setGalleryId(null); }}>{size.label}</button>)}</div><p>{copy.sizeNote}</p></fieldset>}
    {product.custom_size_allowed && <div data-custom-size><p>{copy.custom}</p>{product.custom_size && <p>{copy.bounds}: {copy.width} <bdi>{product.custom_size.min_width}–{product.custom_size.max_width} {product.custom_size.measurement_unit}</bdi>; {copy.length} <bdi>{product.custom_size.min_length}–{product.custom_size.max_length} {product.custom_size.measurement_unit}</bdi></p>}</div>}
    {product.finishes.length > 0 && <label className="field-label">{copy.finish}<select defaultValue={product.finishes.find(item => item.is_default)?.id || product.finishes[0].id}>{product.finishes.map(finish => <option key={finish.id} value={finish.id}>{finish.name}</option>)}</select></label>}
    <p>{copy.units}: {product.quantity_units.map(unit => <bdi key={unit} className="unit-label">{unit}</bdi>)}</p>
  </section>;
}
