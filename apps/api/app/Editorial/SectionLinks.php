<?php

namespace App\Editorial;

use App\Catalogue\PublicCatalogue;
use App\Projects\PublicProjects;

class SectionLinks
{
    public const PAGES = ['home', 'products', 'projects', 'services', 'about', 'contact'];

    public static function options(string $locale): array
    {
        $catalogue = app(PublicCatalogue::class);
        $products = $catalogue->categories($locale)->where(fn ($query) => $query->whereNull('parent_id')->orWhereHas('parent', fn ($parent) => $parent->whereIn('id', $catalogue->categories($locale)->whereNull('parent_id')->select('id'))))->with('translations')->orderBy('sort_order')->orderBy('slug')->get();
        $projects = app(PublicProjects::class)->categories($locale)->with('translations')->orderBy('sort_order')->orderBy('slug')->get();

        return [
            $locale === 'ar' ? 'صفحات الموقع' : 'Pages' => array_combine(self::PAGES, $locale === 'ar' ? ['الرئيسية', 'جميع المنتجات', 'جميع المشاريع', 'خدماتنا', 'من نحن', 'اتصل بنا'] : ['Accueil', 'Tous les produits', 'Tous les projets', 'Nos services', 'À propos', 'Contact']),
            $locale === 'ar' ? 'فئات المنتجات' : 'Catégories de produits' => $products->mapWithKeys(fn ($category) => ['products?'.($category->parent_id ? 'category' : 'parent').'='.$category->slug => $catalogue->copy($category, $locale)['name']])->all(),
            $locale === 'ar' ? 'فئات المشاريع' : 'Catégories de projets' => $projects->mapWithKeys(fn ($category) => ['projects?category='.$category->slug => $catalogue->copy($category, $locale)['name']])->all(),
        ];
    }

    public static function values(string $locale): array
    {
        return array_merge(...array_values(array_map('array_keys', self::options($locale))));
    }
}
