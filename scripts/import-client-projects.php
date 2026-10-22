<?php

use App\Editorial\SiteContentWriter;
use App\Media\AssetImporter;
use App\Media\MediaLibrary;
use App\Models\MediaAsset;
use App\Models\MediaImport;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\SiteContent;
use App\Models\User;
use App\Projects\ProjectWriter;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Explicit local integration only. Never reset fixtures or mutate the business database.
try {
    $root = dirname(__DIR__);
    require $root.'/apps/api/vendor/autoload.php';
    $app = require $root.'/apps/api/bootstrap/app.php';
    $app->loadEnvironmentFrom('.env.client-preview');
    $app->make(Kernel::class)->bootstrap();
    TestDatabaseGuard::assertPreviewTarget($app->environment(), config('database'));
    if (DB::selectOne('SELECT DATABASE() AS name')->name !== TestDatabaseGuard::DATABASE) {
        throw new RuntimeException('Requires the existing isolated local preview database.');
    }
    $folder = $root.'/.tools/client-projects';
    $plan = json_decode(file_get_contents($root.'/config/client-projects-map.json'), true, 512, JSON_THROW_ON_ERROR);
    $company = json_decode(file_get_contents($root.'/config/client-company-content.json'), true, 512, JSON_THROW_ON_ERROR);
    $apply = in_array('--apply', $argv, true);
    $resume = in_array('--resume', $argv, true);
    if (is_file($folder.'/applied.json')) {
        throw new RuntimeException('Already applied. Refusing to overwrite subsequent editorial changes.');
    }
    $result = app(AssetImporter::class)->run($folder.'/import');
    file_put_contents($folder.'/dry-run.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(collect($result)->except('files')->all(), JSON_THROW_ON_ERROR).PHP_EOL;
    $unsafeSkips = collect($result['files'])->contains(fn ($row) => $row['status'] === 'skipped' && ! str_starts_with($row['reason'], 'Already imported;'));
    if ($result['failed'] || $unsafeSkips || ($result['skipped'] && ! $resume)) {
        throw new RuntimeException('Resolve all dry-run failures/skips before apply; inspect the local report.');
    }
    if (! $apply) {
        exit(0);
    }
    $actor = User::where('role', 'super_admin')->where('active', true)->firstOrFail();
    if (is_file($folder.'/database-before.json') && ! $resume) {
        throw new RuntimeException('Snapshot exists; inspect any interrupted apply before proceeding.');
    }
    if ($resume && (! is_file($folder.'/database-before.json') || ! is_file($folder.'/import-result.json') || Project::whereIn('slug', array_column($plan['projects'], 'slug'))->exists())) {
        throw new RuntimeException('Resume requires an existing snapshot, interrupted private import and no published collection records.');
    }
    $snapshot = [];
    foreach ($resume ? [] : DB::select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        if (in_array($table, ['users', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks'])) {
            continue;
        }
        $snapshot[$table] = DB::table($table)->get()->map(fn ($record) => (array) $record)->all();
    }
    if (! $resume) {
        file_put_contents($folder.'/database-before.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
    $result = app(AssetImporter::class)->run($folder.'/import', true, true);
    file_put_contents($folder.'/import-result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    if ($result['failed']) {
        throw new RuntimeException('Private import incomplete; no project publication attempted.');
    }
    $library = app(MediaLibrary::class);
    $assets = [];
    foreach ($plan['assets'] as $mapping) {
        $ledger = MediaImport::where('checksum', $mapping['sha256'])->where('source', $mapping['source'])->firstOrFail();
        $asset = MediaAsset::findOrFail($ledger->media_asset_id);
        $revision = $library->process($asset, config('media.roles.'.$mapping['role'].'.profiles'));
        $library->approve($asset, $actor);
        $library->publish($asset->fresh(), $revision, $actor);
        $assets[$mapping['source']] = $asset->id;
    }
    $created = [];
    DB::transaction(function () use ($plan, $company, $assets, $actor, &$created) {
        $writer = app(ProjectWriter::class);
        foreach ($plan['projects'] as $index => $project) {
            if (Project::where('slug', $project['slug'])->exists() || ProjectCategory::where('slug', $project['slug'])->exists()) {
                throw new RuntimeException('Existing slug requires editorial review: '.$project['slug']);
            }
            $translations = [];
            $categoryTranslations = [];
            foreach ($project['title'] as $locale => $title) {
                $summary = $locale === 'ar' ? 'مجموعة من أعمال LAAMACH MARBRE، موثقة بصور مقدمة من العميل.' : 'Une sélection de réalisations LAAMACH MARBRE, documentée par les images fournies par le client.';
                $description = $locale === 'ar' ? 'تعرض هذه المجموعة صوراً لأعمال ومواقع مختلفة ضمن هذا النوع من الاستخدام. لا تمثل جميع الصور مشروعاً واحداً. يمكنكم استكشاف التفاصيل ومشاركة الصور التي تلهم مشروعكم معنا.' : 'Cette galerie réunit des vues de plusieurs réalisations et sites autour de cet usage. Toutes les images ne représentent pas un chantier unique. Explorez les détails et partagez avec nous les vues qui inspirent votre projet.';
                $translations[$locale] = ['title' => $title, 'summary' => $summary, 'description' => $description, 'seo_title' => $title.' | LAAMACH MARBRE', 'seo_description' => $title.' — '.$summary];
                $categoryTranslations[$locale] = ['name' => $title, 'description' => $summary];
            }
            $cover = collect($project['entries'])->firstWhere('role', 'project_cover')['source'];
            $category = $writer->save(null, ['slug' => $project['slug'], 'active' => true, 'sort_order' => $index, 'cover_media_id' => $assets[$cover], 'translations' => $categoryTranslations], $actor, true);
            $gallery = [];
            foreach ($project['entries'] as $entry) {
                if ($entry['role'] === 'project_cover') {
                    continue;
                }
                $gallery[] = ['media_asset_id' => $assets[$entry['source']], 'type' => $entry['role'] === 'project_video' ? 'video' : 'image', 'poster_media_id' => isset($entry['poster']) ? $assets[$entry['poster']] : null, 'captions' => ['fr' => $project['title']['fr'], 'ar' => $project['title']['ar']]];
            }
            $record = $writer->save(null, ['slug' => $project['slug'], 'status' => 'published', 'featured' => true, 'sort_order' => $index, 'cover_media_id' => $assets[$cover], 'categories' => [$category->id], 'gallery' => $gallery, 'translations' => $translations], $actor);
            $created[] = ['slug' => $record->slug, 'gallery' => count($gallery)];
        }
        // Retain the three earlier illustrative records privately; real client work replaces them.
        foreach (Project::with(['translations', 'gallery', 'categories'])->whereIn('slug', ['inspiration-facade', 'inspiration-interieur', 'inspiration-detail'])->get() as $record) {
            $writer->save($record, ['slug' => $record->slug, 'status' => 'draft', 'featured' => false, 'sort_order' => $record->sort_order, 'cover_media_id' => $record->cover_media_id, 'categories' => $record->categories->pluck('id')->all(), 'gallery' => $record->gallery->map(fn ($item) => collect($item)->only(['media_asset_id', 'type', 'poster_media_id', 'captions'])->all())->all(), 'translations' => $record->translations->mapWithKeys(fn ($copy) => [$copy->locale => collect($copy)->only(['title', 'summary', 'description', 'seo_title', 'seo_description', 'area_display'])->all()])->all()], $actor);
        }
        foreach (SiteContent::all() as $record) {
            $translations = $record->translations;
            $data = $record->data;
            foreach (['fr', 'ar'] as $locale) {
                $copy = $translations[$locale] ?? [];
                $copy = array_replace($copy, $company['seo'][$record->key][$locale] ?? []);
                if ($record->key === 'site') {
                    $copy['footer_text'] = $company['company'][$locale];
                }
                if (in_array($record->key, ['home', 'about', 'services'])) {
                    $copy['body'] = $company['company'][$locale];
                }
                if ($record->key === 'home') {
                    $copy['sections'] = [...array_values(array_filter($copy['sections'] ?? [], fn ($section) => $section['group'] !== 'trust')), ...$company['trust'][$locale]];
                    // Use actual project covers for usage illustrations and the architectural activity.
                    foreach ($copy['sections'] as &$section) {
                        if ($section['group'] === 'services' || ($section['group'] === 'activities' && $section['icon'] === 'building')) {
                            $first = $plan['projects'][$section['title'] === 'Sols & espaces' || $section['title'] === 'الأرضيات والمساحات' ? 0 : 4];
                            $section['media_asset_id'] = $assets[collect($first['entries'])->firstWhere('role', 'project_cover')['source']];
                        }
                    }
                    unset($section);
                }
                if ($record->key === 'about') {
                    $copy['sections'] = [...array_values(array_filter($copy['sections'] ?? [], fn ($section) => $section['group'] !== 'trust')), ...$company['trust'][$locale]];
                    foreach ($copy['sections'] as &$section) {
                        if ($section['group'] === 'story') {
                            $section['body'] = $company['company'][$locale]."\n\n".($locale === 'ar' ? 'أكثر من 10 سنوات من الخبرة، وأكثر من 150 عميلاً راضياً وحجر مغربي 100٪.' : 'Plus de 10 ans d’expérience, plus de 150 clients satisfaits et une pierre 100 % marocaine.');
                        }
                    }
                    unset($section);
                }
                if ($record->key === 'projects') {
                    $copy['body'] = $company['seo']['projects'][$locale]['seo_description'];
                }
                $translations[$locale] = $copy;
            }
            if (in_array($record->key, ['projects', 'about', 'services'])) {
                $data['hero_media_id'] = $assets[collect($plan['projects'][4]['entries'])->firstWhere('role', 'project_cover')['source']];
            }
            app(SiteContentWriter::class)->save($record, ['key' => $record->key, 'published' => $record->published, 'translations' => $translations, 'data' => $data], $actor);
        }
    });
    file_put_contents($folder.'/applied.json', json_encode(['projects' => $created, 'assets' => count($assets), 'database' => TestDatabaseGuard::DATABASE], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo 'Applied '.count($created).' real project collections and '.count($assets).' approved media assets; bilingual editorial updated.'.PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error instanceof ValidationException ? implode(' ', $error->validator->errors()->all()).PHP_EOL : $error->getMessage().PHP_EOL);
    exit(1);
}
