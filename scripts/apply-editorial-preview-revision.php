<?php

use App\Catalogue\CatalogueWriter;
use App\Editorial\SiteContentWriter;
use App\Models\Finish;
use App\Models\Product;
use App\Models\Project;
use App\Models\SiteContent;
use App\Models\User;
use App\Projects\ProjectWriter;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

try {
    $root = dirname(__DIR__);
    require $root.'/apps/api/vendor/autoload.php';
    $app = require $root.'/apps/api/bootstrap/app.php';
    $app->loadEnvironmentFrom('.env.client-preview');
    $app->make(Kernel::class)->bootstrap();
    TestDatabaseGuard::assertPreviewTarget($app->environment(), config('database'));
    if (DB::selectOne('SELECT DATABASE() AS name')->name !== TestDatabaseGuard::DATABASE || ! is_file($root.'/docs/m5-copy-review.md')) {
        throw new RuntimeException('Requires the isolated preview database and the prepared copy review inventory.');
    }
    $state = json_decode(file_get_contents($root.'/.tools/content-preview/state.json'), true, 32, JSON_THROW_ON_ERROR);
    $proposals = json_decode(file_get_contents($root.'/config/product-editorial-review.json'), true, 32, JSON_THROW_ON_ERROR);
    $pages = json_decode(file_get_contents($root.'/config/website-editorial-revision.json'), true, 32, JSON_THROW_ON_ERROR);
    $products = Product::with(['category', 'mediaReferences'])->whereIn('slug', $state['products'])->get()->keyBy('slug');
    if ($products->count() !== 7 || $products->contains(fn ($product) => $product->category?->slug !== 'selection-photo-provisoire')) {
        throw new RuntimeException('Expected the explicitly provisional seven-product dataset.');
    }
    $actor = User::findOrFail($state['operator']);
    if ($actor->email !== 'preview@laamach.invalid' || $actor->active) {
        throw new RuntimeException('Expected the disabled preview audit actor.');
    }
    $actor->active = true; // Policy context only, never saved/enabled as a login.
    $writer = app(CatalogueWriter::class);
    DB::transaction(function () use ($products, $pages, $proposals, $actor, $writer) {
        foreach ($pages as $key => $page) {
            $translations = [];
            foreach (config('localization.locales') as $locale) {
                if (! isset($page[$locale['code']])) {
                    continue;
                }
                $translation = $page[$locale['code']];
                foreach ($translation['sections'] ?? [] as $index => $section) {
                    if (isset($section['image'])) {
                        $section['media_asset_id'] = $products[$section['image']]->cover_media_id;
                        unset($section['image']);
                    }
                    $translation['sections'][$index] = $section;
                }
                $translations[$locale['code']] = $translation;
            }
            $data = [];
            foreach (['hero', 'secondary'] as $role) {
                if (isset($page[$role])) {
                    $data[$role.'_media_id'] = $products[$page[$role]]->cover_media_id;
                }
            }
            app(SiteContentWriter::class)->save(SiteContent::findOrFail($key), ['key' => $key, 'published' => true, 'translations' => $translations, 'data' => $data], $actor);
        }
        foreach ($proposals as $proposal) {
            $product = $products[$proposal['slug']];
            $gallery = $product->mediaReferences->filter(fn ($ref) => str_starts_with($ref->slot, 'gallery:'))->sortBy('slot')->pluck('media_asset_id')->all();
            $translations = array_map(fn ($copy) => $copy + ['colour' => null, 'origin' => null, 'technical_information' => null], $proposal['translations']);
            $writer->product($product, ['slug' => $product->slug, 'category_id' => $product->category_id, 'status' => $product->status, 'featured' => $product->featured, 'sort_order' => $product->sort_order, 'cover_media_id' => $product->cover_media_id, 'gallery' => $gallery, 'units' => ['piece'], 'sizes' => [], 'finishes' => [], 'translations' => $translations], $actor);
        }
        foreach (Finish::with('translations')->whereIn('slug', ['preview-naturelle', 'preview-adoucie'])->get() as $finish) {
            $writer->finish($finish, ['slug' => $finish->slug, 'active' => false, 'sort_order' => $finish->sort_order, 'translations' => $finish->translations->mapWithKeys(fn ($translation) => [$translation->locale => ['name' => $translation->name, 'description' => $translation->description]])->all()], $actor);
        }
        foreach (['facade' => ['Façade', 'واجهة'], 'interieur' => ['Intérieur', 'مساحة داخلية'], 'detail' => ['Détail architectural', 'تفصيل معماري']] as $slug => [$fr, $ar]) {
            $project = Project::where('slug', 'inspiration-'.$slug)->firstOrFail();
            $gallery = $project->gallery->map(fn ($item) => ['media_asset_id' => $item->media_asset_id, 'type' => $item->type, 'poster_media_id' => $item->poster_media_id, 'captions' => $item->captions])->all();
            app(ProjectWriter::class)->save($project, ['slug' => $project->slug, 'status' => $project->status, 'featured' => $project->featured, 'sort_order' => $project->sort_order, 'cover_media_id' => $project->cover_media_id, 'categories' => $project->categories()->pluck('project_categories.id')->all(), 'gallery' => $gallery, 'translations' => ['fr' => ['title' => $fr.' — étude d’ambiance', 'summary' => 'Une composition d’inspiration autour des volumes, des lignes et de la matière.', 'description' => 'Ce visuel illustratif ne représente pas un chantier réalisé par LAAMACH MARBRE. Il propose une piste de réflexion pour votre projet. Aucun lieu, client, surface, date ou matériau effectivement installé n’est affirmé.', 'seo_title' => $fr.' : étude d’ambiance | LAAMACH MARBRE', 'seo_description' => 'Une inspiration architecturale en pierre, présentée comme illustration et non comme réalisation client.'], 'ar' => ['title' => $ar.' — تصور للأجواء', 'summary' => 'تصور ملهم للتفكير في الأحجام والخطوط وحضور المادة.', 'description' => 'لا تمثل هذه الصورة التوضيحية مشروعًا نفذته LAAMACH MARBRE. إنها فكرة للتأمل في مشروعك، دون ادعاء موقع أو عميل أو مساحة أو تاريخ أو مادة تم تركيبها.', 'seo_title' => $ar.' : تصور للأجواء | LAAMACH MARBRE', 'seo_description' => 'فكرة معمارية بالحجر معروضة للتوضيح وليست مشروعًا منفذًا لعميل.']]], $actor);
        }
    });
    echo "Reviewed editorial revision applied to the isolated preview only. Seven product descriptions remain review proposals; illustrative sizes, finish assignments, colour, origin and technical values removed. Application records unchanged.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
