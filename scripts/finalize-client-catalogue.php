<?php

use App\Catalogue\CatalogueWriter;
use App\Editorial\SiteContentWriter;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\SiteContent;
use App\Models\User;
use App\Projects\ProjectWriter;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

try {
    $root = dirname(__DIR__);
    require $root.'/apps/api/vendor/autoload.php';
    $app = require $root.'/apps/api/bootstrap/app.php';
    $app->loadEnvironmentFrom('.env.client-preview');
    $app->make(Kernel::class)->bootstrap();
    TestDatabaseGuard::assertPreviewTarget($app->environment(), config('database'));
    $directory = $root.'/.tools/catalogue-final';
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    if (is_file($directory.'/before.json')) {
        throw new RuntimeException('Already attempted: review the saved snapshot before another run.');
    }
    $config = json_decode(file_get_contents($root.'/config/client-product-content.json'), true, 512, JSON_THROW_ON_ERROR);
    $actor = User::where('active', true)->where('role', 'super_admin')->firstOrFail();
    $snapshot = [];
    foreach (DB::select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        if (! in_array($table, ['users', 'sessions', 'password_reset_tokens', 'cache', 'cache_locks'])) {
            $snapshot[$table] = DB::table($table)->get()->map(fn ($record) => (array) $record)->all();
        }
    }
    file_put_contents($directory.'/before.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    DB::transaction(function () use ($config, $actor) {
        $catalogue = app(CatalogueWriter::class);
        $projects = app(ProjectWriter::class);
        foreach (['exemples-provisoires', 'exemple-facade', 'exemple-interieur', 'exemple-detail'] as $slug) {
            $category = ProjectCategory::with('translations')->where('slug', $slug)->first();
            if ($category) {
                $projects->save($category, ['slug' => $slug, 'active' => false, 'sort_order' => $category->sort_order, 'cover_media_id' => $category->cover_media_id, 'translations' => $category->translations->mapWithKeys(fn ($copy) => [$copy->locale => collect($copy)->only(['name', 'description', 'seo_title', 'seo_description'])->all()])->all()], $actor, true);
            }
        }
        foreach (['collection-provisoire' => ['Nos matières', 'موادنا'], 'selection-photo-provisoire' => ['Pierre naturelle marocaine', 'الحجر الطبيعي المغربي']] as $slug => [$fr, $ar]) {
            $category = ProductCategory::where('slug', $slug)->firstOrFail();
            $catalogue->category($category, ['slug' => $slug, 'active' => true, 'sort_order' => $category->sort_order, 'parent_id' => $category->parent_id, 'cover_media_id' => $category->cover_media_id, 'translations' => ['fr' => ['name' => $fr, 'description' => 'Découvrez la sélection de pierre naturelle marocaine de LAAMACH MARBRE.'], 'ar' => ['name' => $ar, 'description' => 'اكتشف تشكيلة الحجر الطبيعي المغربي لدى LAAMACH MARBRE.']]], $actor);
        }
        foreach ($config['products'] as $copy) {
            $product = Product::with(['mediaReferences', 'units'])->where('slug', $copy['slug'])->firstOrFail();
            if ($product->sizes()->exists() || $product->finishes()->exists()) {
                throw new RuntimeException('Preserve newly edited specifications: review required for '.$product->slug);
            }
            $gallery = $product->mediaReferences->filter(fn ($ref) => str_starts_with($ref->slot, 'gallery:'))->sortBy('slot')->pluck('media_asset_id')->all();
            $catalogue->product($product, ['slug' => $product->slug, 'category_id' => $product->category_id, 'status' => $product->status, 'featured' => $product->featured, 'sort_order' => $product->sort_order, 'cover_media_id' => $product->cover_media_id, 'gallery' => $gallery, 'units' => $product->units->pluck('unit')->all(), 'sizes' => [], 'finishes' => [], 'translations' => $copy['translations']], $actor);
        }
        // Editorial cross-links, presented as suggestions rather than proof of installed materials.
        foreach ($config['projectProducts'] as $slug => $productSlugs) {
            $project = Project::with(['translations', 'gallery', 'categories'])->where('slug', $slug)->firstOrFail();
            $translations = $project->translations->mapWithKeys(fn ($copy) => [$copy->locale => collect($copy)->only(['title', 'summary', 'description', 'seo_title', 'seo_description', 'area_display'])->all()])->all();
            foreach ($translations as $locale => &$copy) {
                $copy['summary'] = $locale === 'ar' ? 'أعمال بالحجر الطبيعي تجمع بين العناية بالتفاصيل وحضور المادة في المكان.' : 'Des réalisations en pierre naturelle, entre soin du détail et caractère architectural.';
            }
            unset($copy);
            $projects->save($project, ['slug' => $slug, 'status' => $project->status, 'featured' => $project->featured, 'sort_order' => $project->sort_order, 'cover_media_id' => $project->cover_media_id, 'categories' => $project->categories->pluck('id')->all(), 'gallery' => $project->gallery->map(fn ($item) => collect($item)->only(['media_asset_id', 'type', 'poster_media_id', 'captions'])->all())->all(), 'products' => Product::whereIn('slug', $productSlugs)->get()->values()->map(fn ($product, $index) => ['product_id' => $product->id, 'sort_order' => $index])->all(), 'translations' => $translations], $actor);
        }
        foreach (['site', 'products'] as $key) {
            $record = SiteContent::findOrFail($key);
            $data = $record->data;
            $translations = $record->translations;
            if ($key === 'site') {
                $data['phone'] = $config['whatsapp'];
                $data['whatsapp'] = ['number' => $config['whatsapp'], 'enabled' => true, 'material' => true, 'application' => true, 'similar' => true];
            } else {
                $translations['fr']['body'] = 'Pierres grises, noires et beiges : découvrez les nuances de notre sélection marocaine. Parcourez les photographies, explorez les idées de réalisations et échangez directement avec notre équipe pour préparer votre projet.';
                $translations['ar']['body'] = 'أحجار رمادية وسوداء وبيج: اكتشف تدرجات تشكيلتنا المغربية. تصفح الصور واستلهم من الأعمال وتواصل مباشرة مع فريقنا لتحضير مشروعك.';
            }
            app(SiteContentWriter::class)->save($record, ['key' => $key, 'published' => $record->published, 'translations' => $translations, 'data' => $data], $actor);
        }
    });
    file_put_contents($directory.'/applied.json', json_encode(['products' => 7, 'obsolete_categories_disabled' => 4, 'project_suggestion_links' => 11, 'whatsapp' => $config['whatsapp']], JSON_THROW_ON_ERROR));
    echo "Seven products enriched; obsolete categories disabled; editorial project links and admin-editable WhatsApp enabled.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error instanceof ValidationException ? implode(' ', $error->validator->errors()->all()).PHP_EOL : $error->getMessage().PHP_EOL);
    exit(1);
}
