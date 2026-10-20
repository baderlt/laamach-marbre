<?php

namespace App\Http\Controllers;

use App\Catalogue\PublicCatalogue;
use App\Editorial\PublicSite;
use App\Projects\PublicProjects;

class SeoController
{
    public function __invoke(PublicCatalogue $catalogue, PublicProjects $projects, PublicSite $site)
    {
        $entries = [];
        foreach (array_column(config('localization.locales'), 'code') as $locale) {
            foreach ($site->records()->get() as $page) {
                if ($page->key !== 'site' && filled($page->translations[$locale]['title'] ?? null)) {
                    $entries[] = ['path' => $page->key === 'home' ? '' : $page->key, 'locale' => $locale];
                }
            }
            foreach (['products' => $catalogue->products($locale), 'projects' => $projects->projects($locale)] as $kind => $query) {
                // Reuse public eligibility without hydrating images, translations or viewer data.
                // Fail closed instead of silently publishing an incomplete oversized sitemap.
                $rows = $query->setEagerLoads([])->limit(10001)->get(['slug']);
                abort_if($rows->count() > 10000, 503);
                foreach ($rows as $row) {
                    $entries[] = ['path' => $kind.'/'.$row->slug, 'locale' => $locale];
                }
            }
        }

        // Fresh, bounded queries intentionally omit lastmod: changing a referenced
        // category/media translation also changes eligibility without touching the owner.
        return response()->json(['data' => $entries]);
    }
}
