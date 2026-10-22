<?php

// Populated local preview only. Never seed the application database with examples.
use App\Catalogue\CatalogueWriter;
use App\Editorial\SiteContentWriter;
use App\Media\ClientProductAssets;
use App\Media\MediaLibrary;
use App\Models\MediaAsset;
use App\Models\MediaImport;
use App\Models\Product;
use App\Models\User;
use App\Projects\ProjectWriter;
use App\Support\TestDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

try {
    $root = dirname(__DIR__);
    require $root.'/apps/api/vendor/autoload.php';
    $app = require $root.'/apps/api/bootstrap/app.php';
    $app->loadEnvironmentFrom('.env.client-preview');
    $app->make(Kernel::class)->bootstrap();
    TestDatabaseGuard::assertPreviewTarget($app->environment(), config('database'));
    if (DB::selectOne('SELECT DATABASE() AS name')->name !== TestDatabaseGuard::DATABASE
        || config('media.private_disk') !== 'local' || config('media.published_disk') !== 'local_published'
        || config('app.url') !== 'http://127.0.0.1:8101') {
        throw new RuntimeException('Preview requires the isolated local test database, local media and loopback port 8101.');
    }
    foreach (['users', 'products', 'projects', 'product_categories', 'project_categories', 'site_contents', 'media_assets', 'media_imports'] as $table) {
        if (DB::table($table)->exists()) {
            throw new RuntimeException('Preview preparation requires an empty test dataset; existing content will not be overwritten.');
        }
    }
    $inventory = json_decode(File::get($root.'/produits/organisation-inventory.json'), true, 32, JSON_THROW_ON_ERROR);
    $copy = json_decode(File::get($root.'/config/website-preview-content.json'), true, 32, JSON_THROW_ON_ERROR);
    $stage = $root.'/.tools/content-preview/assets';
    $manifest = [];
    foreach ($inventory['files'] as $file) {
        if (! in_array($file['role'], ['cover', 'gallery'], true)) {
            continue;
        }
        $source = $root.'/produits/'.$file['destination'];
        if (! is_file($source) || hash_file('sha256', $source) !== $file['sha256']) {
            throw new RuntimeException('A reviewed client source is missing or changed.');
        }
        $relative = preg_replace('#^categoris-hjar/#', 'products/', $file['destination']);
        $parts = explode('/', $relative);
        $destination = $stage.'/'.$relative;
        File::ensureDirectoryExists(dirname($destination));
        if (! is_file($destination)) {
            File::copy($source, $destination);
        }
        $manifest[] = ['source' => $relative, 'target_type' => 'product', 'target_key' => $parts[1], 'role' => 'product_'.$file['role'], 'order' => $file['role'] === 'cover' ? 0 : (int) pathinfo($relative, PATHINFO_FILENAME), 'client_supplied' => true, 'sha256' => $file['sha256']];
    }
    File::put($stage.'/assets-map.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $assets = app(ClientProductAssets::class);
    $dry = $assets->run($stage, false, true);
    echo json_encode(['mode' => 'dry-run', 'accepted' => $dry['accepted'], 'skipped' => $dry['skipped'], 'failed' => $dry['failed'], 'duplicates' => $dry['duplicates'], 'ambiguous' => $dry['ambiguous']], JSON_THROW_ON_ERROR)."\n";
    if (! in_array('--apply', $argv, true)) {
        exit;
    }
    if ($dry['accepted'] !== 17 || $dry['failed'] || $dry['ambiguous'] || $dry['duplicates']) {
        throw new RuntimeException('Expected exactly 17 unambiguous, checksum-verified client images.');
    }
    $result = $assets->run($stage, true, true);
    if ($result['failed']) {
        throw new RuntimeException('Private import failed; no demo publication attempted.');
    }
    $operator = User::factory()->create(['name' => 'Local preview operator', 'email' => 'preview@laamach.invalid', 'password' => bin2hex(random_bytes(32)), 'role' => 'super_admin', 'active' => true]);
    $library = app(MediaLibrary::class);
    $covers = [];
    $galleries = [];
    foreach ($manifest as $entry) {
        $asset = MediaAsset::where('checksum', $entry['sha256'])->firstOrFail();
        $revision = $library->process($asset, $entry['role'] === 'product_cover' ? ['thumb', 'card', 'gallery', 'banner'] : ['thumb', 'gallery']);
        $library->approve($asset, $operator);
        $library->publish($asset, $revision, $operator);
        if ($entry['role'] === 'product_cover') {
            $covers[$entry['target_key']] = $asset->id;
        } else {
            $galleries[$entry['target_key']][$entry['order']] = $asset->id;
        }
    }
    $writer = app(CatalogueWriter::class);
    $names = fn (string $fr, string $ar): array => ['fr' => ['name' => $fr], 'ar' => ['name' => $ar]];
    $parent = $writer->category(null, ['slug' => 'collection-provisoire', 'active' => true, 'cover_media_id' => $covers['taza-beige'], 'translations' => $names('Collection client · provisoire', 'مجموعة العميل · مؤقتة')], $operator);
    $child = $writer->category(null, ['slug' => 'selection-photo-provisoire', 'parent_id' => $parent->id, 'active' => true, 'cover_media_id' => $covers['hajar-jbal-taza'], 'translations' => $names('Sélection photo · provisoire', 'مجموعة الصور · مؤقتة')], $operator);
    foreach ($inventory['products'] as $position => $item) {
        $slug = $item['slug'];
        $name = ucwords(str_replace('-', ' ', $slug));
        $gallery = $galleries[$slug] ?? [];
        ksort($gallery);
        $description = 'Fiche provisoire à partir des photographies client. Le nom et le regroupement sont conservés tels que fournis. Comparez les vues pour découvrir le relief et les nuances visibles. Les dimensions, la finition, l’origine, les usages et la disponibilité restent à confirmer. L’unité « pièce » est uniquement un exemple de présentation, pas une unité de vente validée.';
        $arabic = 'بطاقة مؤقتة انطلاقًا من صور العميل. احتفظنا بالاسم وتجميع الصور كما وردا. قارن الصور لاكتشاف الملمس والتدرجات الظاهرة. تبقى المقاسات والتشطيب والمنشأ والاستخدامات والتوفر في انتظار التأكيد. وحدة «قطعة» مثال للعرض فقط وليست وحدة بيع معتمدة.';
        $writer->product(Product::where('slug', $slug)->firstOrFail(), ['slug' => $slug, 'category_id' => $child->id, 'status' => 'published', 'featured' => true, 'sort_order' => $position, 'cover_media_id' => $covers[$slug], 'gallery' => array_values($gallery), 'units' => ['piece'], 'translations' => ['fr' => ['name' => $name, 'description' => $description, 'seo_title' => $name.' — aperçu provisoire'], 'ar' => ['name' => $name, 'description' => $arabic, 'seo_title' => $name.' — معاينة مؤقتة']]], $operator);
    }
    $editorial = app(SiteContentWriter::class);
    foreach ($copy as $key => $page) {
        $translations = [];
        foreach (config('localization.locales') as $locale) {
            if (! isset($page[$locale['code']])) {
                continue;
            }
            $translation = $page[$locale['code']];
            foreach ($translation['sections'] ?? [] as $index => $section) {
                if (isset($section['image'])) {
                    $section['media_asset_id'] = $covers[$section['image']];
                    unset($section['image']);
                }
                $translation['sections'][$index] = $section;
            }
            $translations[$locale['code']] = $translation;
        }
        $data = [];
        foreach (['hero', 'secondary'] as $role) {
            if (isset($page[$role])) {
                $data[$role.'_media_id'] = $covers[$page[$role]];
            }
        }
        $editorial->save(null, ['key' => $key, 'published' => true, 'translations' => $translations, 'data' => $data], $operator);
    }
    $projectWriter = app(ProjectWriter::class);
    $common = $projectWriter->save(null, ['slug' => 'exemples-provisoires', 'active' => true, 'translations' => $names('Exemples · non réalisés', 'أمثلة · غير منفذة')], $operator, true);
    foreach ([['facade', 'Façade', 'واجهة', 'Rythmer une façade avec la matière, la lumière et les lignes du bâtiment.', 'التفكير في إيقاع الواجهة من خلال المادة والضوء وخطوط المبنى.'], ['interieur', 'Intérieur', 'مساحة داخلية', 'Imaginer un intérieur où les nuances dialoguent avec les volumes et la lumière.', 'تخيّل مساحة داخلية تتناغم فيها التدرجات مع الأحجام والضوء.'], ['detail', 'Détail architectural', 'تفصيل معماري', 'Explorer la place de la matière dans un soubassement, une transition ou un élément décoratif.', 'استكشاف حضور المادة في قاعدة جدار أو انتقال بين المساحات أو عنصر زخرفي.']] as $position => [$slug, $fr, $ar, $summary, $summaryAr]) {
        $category = $projectWriter->save(null, ['slug' => 'exemple-'.$slug, 'active' => true, 'translations' => $names($fr.' · inspiration', $ar.' · إلهام')], $operator, true);
        $illustrationSlug = ['hajar-jbal-taza', 'taza-beige', 'noir-azilal'][$position];
        $illustration = ['cover_media_id' => $covers[$illustrationSlug], 'gallery' => [['media_asset_id' => $galleries[$illustrationSlug][1], 'type' => 'image', 'captions' => ['fr' => 'Photographie de matière client : illustration uniquement, pas une réalisation.', 'ar' => 'صورة لمادة من العميل: توضيح فقط وليست صورة لمشروع منفذ.']]]];
        $projectWriter->save(null, $illustration + ['slug' => 'inspiration-'.$slug, 'status' => 'published', 'featured' => true, 'sort_order' => $position, 'categories' => [$common->id, $category->id], 'translations' => ['fr' => ['title' => $fr.' — exemple de présentation', 'summary' => $summary, 'description' => 'Exemple de contenu pour visualiser le futur portfolio. '.$summary.' Les visuels sont des photographies de matière fournies par le client, utilisées uniquement comme illustrations. Aucun chantier réalisé, lieu, client, surface ou matériau effectivement posé n’est revendiqué. Les photos de projets et les détails validés remplaceront cet exemple.'], 'ar' => ['title' => $ar.' — مثال توضيحي', 'summary' => $summaryAr, 'description' => 'مثال للمحتوى يوضح فكرة ملف المشاريع المستقبلي. '.$summaryAr.' الصور لمواد قدمها العميل وتستخدم للتوضيح فقط. لا يمثل هذا المثال مشروعًا منفذًا أو عميلًا أو موقعًا أو مساحة أو مادة تم تركيبها. تستبدل به صور المشاريع والتفاصيل المعتمدة لاحقًا.']]], $operator);
    }
    // Retain the audit actor without creating usable preview login credentials.
    $operator->forceFill(['active' => false])->save();
    File::put($root.'/.tools/content-preview/state.json', json_encode(['purpose' => 'Provisional local content preview; not approved business data.', 'products' => Product::orderBy('sort_order')->pluck('slug')->all(), 'assets' => MediaAsset::pluck('id')->all(), 'imports' => MediaImport::count(), 'operator' => $operator->id], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "Prepared 7 provisional products, 17 real client images, 7 bilingual editorial records and 3 clearly labelled project examples in the test database only.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
