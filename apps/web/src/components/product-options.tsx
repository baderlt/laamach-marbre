'use client';

import { trapDialogFocus } from '@/lib/dialog-focus';

import { useRef, useState } from 'react';
import { catalogueCopy, selectedImage, type Product } from '@/lib/catalogue';
import { CatalogueImage } from './catalogue-image';
import { siteCopy, type SiteSettings } from '@/lib/site';
import { unitLabel } from '@/lib/whatsapp';
import { SiteIcon } from './site-icon';
import { ProductViewer } from './product-viewer';
import { ProductDimensionExample } from './viewer-dimensions';
import { RequestDraft } from './request-draft';
import { DirectWhatsApp } from './direct-whatsapp';
import { productMeasurements } from '@/lib/viewer-measurements';

export function ProductOptions({ product, locale, technical, related, pendingSpecification = false, settings, canonical }: { product: Product; locale: string; technical?: React.ReactNode; related?: React.ReactNode; pendingSpecification?: boolean; settings: SiteSettings | null; canonical: string }) {
  const copy = catalogueCopy(locale);
  const [sizeId, setSizeId] = useState<string | null>(null);
  const [finishId, setFinishId] = useState(product.finishes.find(item => item.is_default)?.id || product.finishes[0]?.id || '');
  const [galleryId, setGalleryId] = useState<string | null>(null);
  const image = product.gallery.find(item => item.id === galleryId) || selectedImage(product, sizeId);
  const measurements = productMeasurements(product.sizes.find(size => size.id === sizeId) || product.sizes[0]);
  const design = siteCopy(locale);
  const lightbox = useRef<HTMLDialogElement>(null);
  const ar = locale === 'ar';
  const benefits = [
    { icon: 'pin', text: product.origin || product.category.name },
    { icon: 'leaf', text: ar ? 'ملامس وتدرجات طبيعية' : 'Textures et nuances naturelles' },
    { icon: 'ruler', text: ar ? 'مقاسات حسب مشروعك' : 'Formats selon votre projet' },
    { icon: 'craft', text: ar ? 'نصيحة فريقنا' : 'Le conseil de notre équipe' },
  ];
  const moveImage = (step: number) => { const ids = [null, ...product.gallery.map(item => item.id)]; setGalleryId(ids[(ids.indexOf(galleryId) + step + ids.length) % ids.length]); };
  return <section className="product-presentation" data-size-selected={Boolean(sizeId)}>
    <div className="product-visual-column" data-motion-group><ProductViewer measurements={measurements} configuration={product.viewer_3d} locale={locale} cover={product.cover} sizeId={sizeId} onSize={value => { setSizeId(value); setGalleryId(null); }}><div className="product-image" data-product-image><CatalogueImage media={image} placeholder={copy.missingImage} alt={`${product.name} — ${copy.mainImage}`} eager />{product.origin && <span className="photo-note"><SiteIcon name="leaf" />{product.origin}</span>}{image && <button className="gallery-zoom" type="button" aria-label={design.galleryView} onClick={() => lightbox.current?.showModal()}><SiteIcon name="expand" /></button>}{product.gallery.length > 0 && <><button className="gallery-previous" type="button" aria-label={copy.previous} onClick={() => moveImage(-1)}>‹</button><button className="gallery-next" type="button" aria-label={copy.next} onClick={() => moveImage(1)}>›</button></>}</div>
    {product.gallery.length > 0 && <div className="option-list gallery-thumbnails" aria-label={copy.gallery}><button type="button" aria-label={copy.mainImage} aria-pressed={galleryId === null} onClick={() => setGalleryId(null)}><CatalogueImage media={product.cover} placeholder={copy.missingImage} thumbnail /></button>{product.gallery.map((item, index) => <button key={item.id} type="button" aria-label={`${copy.image} ${index + 1}`} aria-pressed={galleryId === item.id} onClick={() => setGalleryId(item.id)}><CatalogueImage media={item} placeholder={copy.missingImage} thumbnail /></button>)}</div>}
    </ProductViewer><ProductDimensionExample locale={locale} measurements={measurements} /></div>
    <div className="product-facts"><p className="eyebrow">{product.category.name}</p><h1>{product.name}</h1><p className="product-reference">{design.reference}: <bdi>{product.reference}</bdi></p>{product.description && <p className="preserve-text product-description">{product.description}</p>}
    <ul className="material-benefits" data-motion-group>{benefits.map(benefit => <li key={benefit.icon}><span><SiteIcon name={benefit.icon} /></span><p>{benefit.text}</p></li>)}</ul>
    {product.sizes.length > 0 && <fieldset><legend>{ar ? 'الأبعاد المتاحة' : 'Dimensions disponibles'}</legend><div className="option-list">{product.sizes.map(size => <button key={size.id} type="button" data-size-id={size.id} aria-pressed={sizeId === size.id} onClick={() => { setSizeId(size.id); setGalleryId(null); }}>{size.label}</button>)}</div><p>{copy.sizeNote}</p></fieldset>}
    {!product.sizes.length && <div className="formats-on-request"><SiteIcon name="masonry" /><div><h2>{locale === 'ar' ? 'مقاسات حسب مشروعك' : 'Des formats pour votre projet'}</h2><p>{locale === 'ar' ? 'شاركنا الأبعاد والكميات المطلوبة عبر واتساب، لنحدد معك المقاسات المناسبة والتفاصيل.' : 'Partagez vos dimensions et quantités sur WhatsApp pour définir ensemble les formats et les détails de votre demande.'}</p></div></div>}
    {product.custom_size_allowed && <div data-custom-size><p>{copy.custom}</p>{product.custom_size && <p>{copy.bounds}: {copy.width} <bdi>{product.custom_size.min_width}–{product.custom_size.max_width} {product.custom_size.measurement_unit}</bdi>; {copy.length} <bdi>{product.custom_size.min_length}–{product.custom_size.max_length} {product.custom_size.measurement_unit}</bdi></p>}</div>}
    {product.finishes.length > 0 && <label className="field-label">{copy.finish}<select value={finishId} onChange={event => setFinishId(event.target.value)}>{product.finishes.map(finish => <option key={finish.id} value={finish.id}>{finish.name}</option>)}</select></label>}
    {!product.finishes.length && <p className="data-note">{locale === 'ar' ? 'ناقش الملمس والمظهر المطلوبين مع فريقنا لاختيار التشطيب المناسب.' : 'Échangez avec notre équipe sur la texture et le rendu souhaités pour choisir votre finition.'}</p>}
    <p className="product-units">{copy.units}: {pendingSpecification ? <span>{design.noUnits}</span> : product.quantity_units.map(unit => <bdi key={unit} className="unit-label">{unitLabel(unit, locale)}</bdi>)}</p>
    <DirectWhatsApp settings={settings} locale={locale} kind="material" title={product.name} reference={product.reference} canonical={canonical} />
    <div className="material-request-actions">{product.sizes.length > 0 && product.finishes.length > 0 && <RequestDraft settings={settings} canonical={canonical} slug={product.slug} locale={locale} kind="material" identity={{ title: product.name, reference: product.reference }} product={product} pending={pendingSpecification} sizeId={sizeId} finishId={finishId} onSize={value => { setSizeId(value); setGalleryId(null); }} onFinish={setFinishId} />}<RequestDraft settings={settings} canonical={canonical} slug={product.slug} locale={locale} kind="application" identity={{ title: product.name, reference: product.reference }} /></div>
    <div className="specification-note"><SiteIcon name="ruler" /><p>{design.sizeGuidance}<br />{design.finishGuidance}</p></div>
    </div>
    <div className="product-support-column" data-motion-group>{technical}{related}</div>
    <dialog onKeyDown={trapDialogFocus} ref={lightbox} className="image-lightbox" aria-label={design.galleryView}><button type="button" className="icon-button" onClick={() => lightbox.current?.close()} aria-label={design.close}>×</button><CatalogueImage media={image} placeholder={copy.missingImage} alt={product.name} eager /></dialog>
  </section>;
}
