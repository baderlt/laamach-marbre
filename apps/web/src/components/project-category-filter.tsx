'use client';

import { useTransition } from 'react';
import { useRouter } from 'next/navigation';
import { projectCategoryIcon, type ProjectCategory } from '@/lib/projects';
import { SiteIcon } from './site-icon';

export function ProjectCategoryFilter({ categories, selected, locale, total }: { categories: ProjectCategory[]; selected: string[]; locale: string; total: number }) {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const ar = locale === 'ar';
  const change = (slug?: string) => {
    const next = slug ? selected.includes(slug) ? selected.filter(value => value !== slug) : [...selected, slug].slice(0, 8) : [];
    const params = new URLSearchParams();
    next.forEach(value => params.append('categories[]', value));
    startTransition(() => router.push(`/${locale}/projects${params.size ? `?${params}` : ''}`, { scroll: false }));
  };
  return <div className="project-filter" aria-busy={pending}>
    <div className="project-filter-heading"><p>{ar ? 'اختر الأجواء التي تلهم مشروعك' : 'Explorez les savoir-faire qui vous inspirent'}</p><span role="status" aria-live="polite">{pending ? (ar ? 'جارٍ التحديث…' : 'Mise à jour…') : `${total} ${ar ? 'مجموعة' : 'collection' + (total === 1 ? '' : 's')}`}</span></div>
    <div className="project-category-options" role="group" aria-label={ar ? 'تصفية حسب الفئة، اختيار متعدد' : 'Filtrer par catégorie, sélection multiple'} data-motion-group>
      <button type="button" aria-pressed={!selected.length} disabled={pending} onClick={() => change()}><SiteIcon name="collection" /><span>{ar ? 'جميع الأعمال' : 'Toutes les réalisations'}</span></button>
      {categories.map(category => <button type="button" key={category.id} aria-pressed={selected.includes(category.slug)} disabled={pending} onClick={() => change(category.slug)}><SiteIcon name={projectCategoryIcon(category.slug)} /><span>{category.name}</span></button>)}
    </div>
  </div>;
}
