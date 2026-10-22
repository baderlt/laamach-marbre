<?php

namespace Tests\Feature;

use App\Catalogue\CatalogueWriter;
use App\Catalogue\PublicCatalogue;
use App\Filament\Resources\CatalogueForms;
use App\Filament\Resources\CataloguePages\ManageCategories;
use App\Filament\Resources\CataloguePages\ManageProducts;
use App\Media\MediaLibrary;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CatalogueFixtures as Fixtures;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $catalogue;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $this->root = storage_path('framework/testing/'.getmypid().'/catalogue/'.Str::uuid());
        File::ensureDirectoryExists($this->root);
        foreach (['local', 'local_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/'.$disk, 'throw' => true]));
        }
        $this->catalogue = Fixtures::catalogue($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function data(array $overrides = []): array
    {
        return $overrides + ['slug' => 'temporary-product', 'category_id' => $this->catalogue['child']->id, 'status' => 'published', 'translations' => Fixtures::copy('Temporary product'), 'units' => ['m2'], 'sizes' => [Fixtures::size()]];
    }

    private function product(array $overrides = []): Product
    {
        return app(CatalogueWriter::class)->product(null, $this->data($overrides), $this->admin);
    }

    public function test_grouped_catalogue_keeps_categories_separate_and_excludes_private_records(): void
    {
        $writer = app(CatalogueWriter::class);
        $second = $writer->category(null, ['slug' => 'second-child', 'parent_id' => $this->catalogue['parent']->id, 'active' => true, 'sort_order' => 10, 'translations' => Fixtures::copy('Second category')], $this->admin);
        $first = $this->product();
        $this->product(['slug' => 'second-product', 'category_id' => $second->id]);
        $this->product(['slug' => 'private-product', 'status' => 'draft']);
        $this->product(['slug' => 'missing-arabic', 'translations' => ['fr' => ['name' => 'Français uniquement']]]);
        foreach (['fr' => 3, 'ar' => 2] as $locale => $total) {
            $response = $this->getJson('/api/v1/product-groups?locale='.$locale)->assertOk()->assertJsonPath('meta.total', $total)->assertJsonCount(2, 'groups')->assertJsonCount($total, 'data')->assertJsonPath('groups.0.category.slug', 'temporary-child')->assertJsonPath('groups.1.category.slug', 'second-child');
            $this->assertSame($total - 1, $response->json('groups.0.total'));
            $this->assertSame($first->reference, $response->json('data.0.reference'));
        }
        $second->forceFill(['active' => false])->save();
        $this->getJson('/api/v1/product-groups?locale=ar')->assertJsonCount(1, 'groups')->assertJsonPath('meta.total', 1);
        $this->catalogue['parent']->forceFill(['active' => false])->save();
        $this->getJson('/api/v1/product-groups')->assertJsonCount(0, 'data')->assertJsonCount(0, 'groups');
        $this->getJson('/api/v1/product-groups?locale=en')->assertUnprocessable();
        $this->getJson('/api/v1/product-groups?page=0')->assertUnprocessable();
    }

    public function test_home_carousel_selects_three_public_products_per_parent_not_per_child(): void
    {
        $writer = app(CatalogueWriter::class);
        $otherChild = $writer->category(null, ['slug' => 'other-child', 'parent_id' => $this->catalogue['parent']->id, 'active' => true, 'translations' => Fixtures::copy('Other child')], $this->admin);
        $parent = $writer->category(null, ['slug' => 'other-parent', 'active' => true, 'sort_order' => 10, 'translations' => Fixtures::copy('Other parent')], $this->admin);
        $child = $writer->category(null, ['slug' => 'second-family-child', 'parent_id' => $parent->id, 'active' => true, 'translations' => Fixtures::copy('Second family child')], $this->admin);
        for ($index = 0; $index < 4; $index++) {
            $this->product(['slug' => 'family-a-'.$index, 'category_id' => $index < 2 ? $this->catalogue['child']->id : $otherChild->id, 'sort_order' => $index, 'featured' => $index === 3]);
            $this->product(['slug' => 'family-b-'.$index, 'category_id' => $child->id, 'sort_order' => $index]);
        }
        $this->product(['slug' => 'private-home-product', 'status' => 'draft', 'featured' => true]);
        foreach (['fr', 'ar'] as $locale) {
            $response = $this->getJson('/api/v1/site?include_navigation=1&locale='.$locale)->assertOk()->assertJsonCount(6, 'data.home_products');
            $this->assertSame(['family-a-3', 'family-a-0', 'family-a-1', 'family-b-0', 'family-b-1', 'family-b-2'], array_column($response->json('data.home_products'), 'slug'));
            $this->getJson('/api/v1/site?locale='.$locale)->assertJsonMissingPath('data.home_products');
        }
        $parent->forceFill(['active' => false])->save();
        $this->getJson('/api/v1/site?include_navigation=1')->assertJsonCount(3, 'data.home_products');
    }

    public function test_grouped_catalogue_eager_loads_products_and_media_in_a_bounded_query_count(): void
    {
        $this->product();
        DB::enableQueryLog();
        app(PublicCatalogue::class)->groupedProducts('fr');
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($index = 0; $index < 3; $index++) {
            $child = app(CatalogueWriter::class)->category(null, ['slug' => 'group-'.$index, 'parent_id' => $this->catalogue['parent']->id, 'active' => true, 'translations' => Fixtures::copy('Group '.$index)], $this->admin);
            $this->product(['slug' => 'group-product-'.$index, 'category_id' => $child->id]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = app(PublicCatalogue::class)->groupedProducts('fr');
        $this->assertCount(4, $data['groups']);
        $this->assertSame($one, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_grouped_scroll_pages_are_bounded_and_complete_across_category_boundaries(): void
    {
        config(['catalogue.per_page' => 3]);
        $second = app(CatalogueWriter::class)->category(null, ['slug' => 'second-child', 'parent_id' => $this->catalogue['parent']->id, 'active' => true, 'sort_order' => 10, 'translations' => Fixtures::copy('Second category')], $this->admin);
        for ($index = 0; $index < 5; $index++) {
            $this->product(['slug' => 'first-'.$index, 'sort_order' => $index]);
        }
        for ($index = 0; $index < 3; $index++) {
            $this->product(['slug' => 'second-'.$index, 'category_id' => $second->id, 'sort_order' => $index]);
        }
        foreach (['fr', 'ar'] as $locale) {
            $slugs = [];
            foreach ([1 => 3, 2 => 3, 3 => 2] as $page => $count) {
                $response = $this->getJson('/api/v1/product-groups?locale='.$locale.'&page='.$page)->assertOk()->assertJsonCount($count, 'data')->assertJsonPath('meta.total', 8)->assertJsonPath('meta.last_page', 3);
                $slugs = [...$slugs, ...array_column($response->json('data'), 'slug')];
                if ($page === 2) {
                    $response->assertJsonPath('groups.0.total', 5)->assertJsonPath('groups.1.total', 3);
                }
            }
            $this->assertSame(['first-0', 'first-1', 'first-2', 'first-3', 'first-4', 'second-0', 'second-1', 'second-2'], $slugs);
            $this->getJson('/api/v1/product-groups?locale='.$locale.'&page=4')->assertJsonCount(0, 'data')->assertJsonCount(0, 'groups');
        }
    }

    public function test_parent_child_creation_translation_and_ordering(): void
    {
        $this->assertNull($this->catalogue['parent']->parent_id);
        $this->assertSame($this->catalogue['parent']->id, $this->catalogue['child']->parent_id);
        $this->getJson('/api/v1/product-categories?locale=ar')->assertOk()->assertJsonPath('data.0.name', 'اختبار Temporary parent')->assertJsonPath('data.0.children.0.slug', 'temporary-child');
        $this->assertDatabaseCount('product_category_translations', 4);
    }

    public function test_third_level_self_parent_and_cycles_are_rejected(): void
    {
        $writer = app(CatalogueWriter::class);
        foreach ([[$this->catalogue['parent'], $this->catalogue['child']->id], [$this->catalogue['child'], $this->catalogue['child']->id], [null, $this->catalogue['child']->id]] as [$record, $parent]) {
            try {
                $writer->category($record, ['slug' => $record?->slug ?? 'third', 'parent_id' => $parent, 'translations' => Fixtures::copy('Invalid')], $this->admin);
                $this->fail('Invalid hierarchy accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('product_categories', 2);
            }
        }
    }

    public function test_products_require_child_categories_and_protect_category_structure(): void
    {
        try {
            $this->product(['category_id' => $this->catalogue['parent']->id]);
            $this->fail('Parent product accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 0);
        }
        $this->product();
        $this->expectException(ValidationException::class);
        app(CatalogueWriter::class)->category($this->catalogue['child'], ['slug' => 'temporary-child', 'parent_id' => null, 'translations' => Fixtures::copy('Root')], $this->admin);
    }

    public function test_product_crud_dimensions_translation_and_stable_reference_and_slug(): void
    {
        $product = $this->product();
        $this->assertMatchesRegularExpression('/^LM-[A-Z0-9]{12}$/', $product->reference);
        $detail = $this->getJson('/api/v1/products/'.$product->slug.'?locale=ar')->assertOk()->assertJsonPath('data.name', 'اختبار Temporary product')->assertJsonPath('data.sizes.0.measurement_unit', 'cm')->assertJsonPath('data.sizes.0.width', '7.0000')->assertJsonPath('data.quantity_units', ['m2']);
        foreach (['price', 'stock', 'lead_time', 'viewer', 'whatsapp'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $detail->json('data'));
        }
        $saved = app(CatalogueWriter::class)->product($product, $this->data(['translations' => Fixtures::copy('Renamed'), 'sizes' => []]), $this->admin);
        $this->assertSame($product->reference, $saved->reference);
        $this->assertSame($product->slug, $saved->slug);
        $this->expectException(ValidationException::class);
        app(CatalogueWriter::class)->product($saved, $this->data(['slug' => 'changed-url']), $this->admin);
    }

    public static function invalidProduct(): array
    {
        return [[['custom_size_allowed' => false, 'min_width' => 5]], [['custom_size_allowed' => true]], [['custom_size_allowed' => true, 'custom_measurement_unit' => 'cm', 'min_width' => 40, 'max_width' => 5, 'min_length' => 10, 'max_length' => 60]], [['units' => ['unsupported']]], [['sizes' => [['width' => -1, 'length' => 20, 'measurement_unit' => 'cm', 'active' => true, 'sort_order' => 0, 'translations' => ['fr' => ['label' => 'Invalid']]]]]], [['sizes' => [['width' => 1, 'length' => 20, 'measurement_unit' => 'invented', 'active' => true, 'sort_order' => 0, 'translations' => ['fr' => ['label' => 'Invalid']]]]]], [['cover_media_id' => 'images/unsafe/file.webp']], [['translations' => ['en' => ['name' => 'Disabled locale']]]], [['price' => 100]], [['reference' => 'unsafe']], [['custom_size_allowed' => true, 'custom_measurement_unit' => 'unknown', 'min_width' => 5, 'max_width' => 40, 'min_length' => 10, 'max_length' => 60]]];
    }

    #[DataProvider('invalidProduct')]
    public function test_invalid_sizes_units_custom_bounds_locales_and_mass_assignment_fail(array $overrides): void
    {
        $this->expectException(ValidationException::class);
        $this->product($overrides);
    }

    public function test_custom_size_policy_is_exposed_without_commercial_promises(): void
    {
        $product = $this->product(['custom_size_allowed' => true, 'custom_measurement_unit' => 'mm', 'min_width' => 5, 'max_width' => 40, 'min_length' => 10, 'max_length' => 60, 'units' => ['piece', 'linear_m']]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.custom_size_allowed', true)->assertJsonPath('data.custom_size.measurement_unit', 'mm')->assertJsonPath('data.custom_size.max_width', '40.0000')->assertJsonCount(2, 'data.quantity_units');
    }

    public function test_cross_product_size_injection_is_atomic_and_rejected(): void
    {
        $first = $this->product();
        $second = $this->product(['slug' => 'second']);
        try {
            app(CatalogueWriter::class)->product($second, $this->data(['slug' => 'second', 'sizes' => [Fixtures::size(overrides: ['id' => $first->sizes()->first()->id])]]), $this->admin);
            $this->fail('Foreign size accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('product_sizes', 2);
            $this->assertSame($first->id, $first->sizes()->first()->product_id);
        }
    }

    public function test_drafts_inactive_hierarchy_and_missing_locale_do_not_leak(): void
    {
        $product = $this->product();
        $draft = $this->product(['slug' => 'draft-only', 'status' => 'draft']);
        $this->getJson('/api/v1/products/'.$draft->slug)->assertNotFound();
        $this->getJson('/api/v1/products')->assertJsonCount(1, 'data');
        foreach (['parent', 'child'] as $kind) {
            $category = $this->catalogue[$kind];
            $category->forceFill(['active' => false])->save();
            $this->getJson('/api/v1/products')->assertJsonCount(0, 'data');
            $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
            $category->forceFill(['active' => true])->save();
        }
        $product->translations()->where('locale', 'ar')->delete();
        $this->getJson('/api/v1/products?locale=ar')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products?locale=en')->assertUnprocessable();
    }

    public function test_finish_assignments_filters_pagination_and_inactive_finish_exclusion(): void
    {
        $finish = $this->catalogue['finish'];
        $product = $this->product(['finishes' => [['finish_id' => $finish->id, 'active' => true, 'is_default' => true, 'sort_order' => 0]]]);
        $this->product(['slug' => 'without-finish']);
        $this->getJson('/api/v1/products?finish=temporary-finish&parent=temporary-parent&category=temporary-child')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/products?per_page=999')->assertUnprocessable();
        $this->getJson('/api/v1/products?page=10001')->assertUnprocessable();
        $finish->forceFill(['active' => false])->save();
        $this->getJson('/api/v1/products/'.$product->slug)->assertJsonCount(0, 'data.finishes');
        $this->getJson('/api/v1/products?finish=temporary-finish')->assertJsonCount(0, 'data');
    }

    public function test_media_roles_size_images_publication_and_reference_protection(): void
    {
        $cover = Fixtures::image($this->admin, $this->root, 50);
        $sizeCover = Fixtures::image($this->admin, $this->root, 190);
        $product = $this->product(['cover_media_id' => $cover->id, 'gallery' => [$sizeCover->id], 'sizes' => [Fixtures::size('Dedicated', ['cover_media_id' => $sizeCover->id]), Fixtures::size('Fallback', ['sort_order' => 1])]]);
        $this->assertDatabaseCount('media_references', 3);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.cover.id', $cover->id)->assertJsonPath('data.sizes.0.cover.id', $sizeCover->id)->assertJsonPath('data.sizes.1.cover', null)->assertJsonPath('data.gallery.0.id', $sizeCover->id);
        $this->assertTrue($cover->references()->exists());
        app(MediaLibrary::class)->unpublish($sizeCover, $this->admin);
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        try {
            $this->product(['slug' => 'unsafe-media', 'cover_media_id' => $sizeCover->id]);
            $this->fail('Unpublished media assigned.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 1);
        }
    }

    public function test_safe_deletion_rejects_referenced_category_finish_and_cleans_product_media_refs(): void
    {
        $product = $this->product(['finishes' => [['finish_id' => $this->catalogue['finish']->id, 'active' => true, 'is_default' => false, 'sort_order' => 0]]]);
        foreach ([$this->catalogue['parent'], $this->catalogue['child'], $this->catalogue['finish']] as $record) {
            try {
                app(CatalogueWriter::class)->delete($record, $this->admin);
                $this->fail('Referenced record deleted.');
            } catch (ValidationException) {
                $this->assertTrue($record->fresh()->exists);
            }
        }
        app(CatalogueWriter::class)->delete($product, $this->admin);
        app(CatalogueWriter::class)->delete($this->catalogue['child'], $this->admin);
        app(CatalogueWriter::class)->delete($this->catalogue['parent'], $this->admin);
        app(CatalogueWriter::class)->delete($this->catalogue['finish'], $this->admin);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_categories', 0);
    }

    public function test_admin_authorization_and_livewire_create_and_edit(): void
    {
        $this->get('/admin/products')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/products')->assertForbidden();
        $this->actingAs($this->admin);
        foreach (['products', 'product-categories', 'finishes'] as $resource) {
            $this->get('/admin/'.$resource)->assertOk();
        }
        Livewire::test(ManageCategories::class)->callAction(TestAction::make('create')->table(), data: ['slug' => 'admin-parent', 'active' => true, 'sort_order' => 4, 'parent_id' => null, 'cover_media_id' => null, 'translations' => Fixtures::copy('Admin created')])->assertHasNoActionErrors();
        $this->assertDatabaseHas('product_categories', ['slug' => 'admin-parent', 'sort_order' => 4]);
        $manager = User::factory()->create(['active' => true]);
        $this->expectException(AuthorizationException::class);
        app(CatalogueWriter::class)->product(null, $this->data(), $manager);
    }

    public function test_catalogue_query_count_remains_constant_with_page_size(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->product(['slug' => 'query-'.$i]);
        }
        $counts = [];
        foreach ([1, 4] as $perPage) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->getJson('/api/v1/products?per_page='.$perPage)->assertOk();
            $counts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThanOrEqual(12, $counts[1]);
    }

    public function test_standard_size_activation_order_optional_dimensions_and_technical_facts(): void
    {
        $copy = Fixtures::copy('Measured');
        $copy['fr']['technical_information'] = [['label' => 'Client fact', 'value' => 'Only supplied text']];
        $product = $this->product(['translations' => $copy, 'sizes' => [Fixtures::size('Later', ['sort_order' => 3, 'height' => 2, 'thickness' => 0.5]), Fixtures::size('First', ['sort_order' => 1]), Fixtures::size('Inactive', ['active' => false])]]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertJsonCount(2, 'data.sizes')->assertJsonPath('data.sizes.0.label', 'First')->assertJsonPath('data.sizes.1.height', '2.0000')->assertJsonPath('data.sizes.1.thickness', '0.5000')->assertJsonPath('data.technical_information.0.value', 'Only supplied text');
    }

    public function test_invalid_finish_assignments_and_multiple_defaults_are_rejected(): void
    {
        $finish = $this->catalogue['finish'];
        $second = app(CatalogueWriter::class)->finish(null, ['slug' => 'another-finish', 'active' => true, 'translations' => Fixtures::copy('Second')], $this->admin);
        foreach ([
            [['finish_id' => (string) Str::uuid(), 'active' => true, 'is_default' => false, 'sort_order' => 0]],
            [['finish_id' => $finish->id, 'active' => false, 'is_default' => true, 'sort_order' => 0]],
            [['finish_id' => $finish->id, 'active' => true, 'is_default' => true, 'sort_order' => 0], ['finish_id' => $second->id, 'active' => true, 'is_default' => true, 'sort_order' => 1]],
        ] as $assignment) {
            try {
                $this->product(['finishes' => $assignment]);
                $this->fail('Invalid finish assignment accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('products', 0);
            }
        }
    }

    public function test_unapproved_originals_cannot_be_assigned_and_category_media_can_hide_hierarchy(): void
    {
        $asset = Fixtures::image($this->admin, $this->root, 110);
        $category = $this->catalogue['parent'];
        app(CatalogueWriter::class)->category($category, ['slug' => $category->slug, 'active' => true, 'translations' => Fixtures::copy('Temporary parent'), 'cover_media_id' => $asset->id], $this->admin);
        $this->product();
        $this->getJson('/api/v1/product-categories')->assertJsonPath('data.0.cover.id', $asset->id);
        app(MediaLibrary::class)->unpublish($asset, $this->admin);
        $this->getJson('/api/v1/products')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/product-categories')->assertJsonCount(0, 'data');
        $asset->forceFill(['approval_state' => 'pending'])->save();
        $this->expectException(ValidationException::class);
        $this->product(['slug' => 'unapproved', 'cover_media_id' => $asset->id]);
    }

    public function test_admin_product_edit_preserves_size_identity_and_changes_all_catalogue_fields(): void
    {
        $product = $this->product(['status' => 'draft']);
        $sizeId = $product->sizes()->first()->id;
        $data = CatalogueForms::data($product);
        $data['translations']['fr']['name'] = 'Edited in admin';
        $data['sizes'][0]['width'] = 12;
        $data['units'] = ['m3', 'piece'];
        $this->actingAs($this->admin);
        Livewire::test(ManageProducts::class)->callAction(TestAction::make('edit')->table($product), data: $data)->assertHasNoActionErrors();
        $this->assertSame('Edited in admin', $product->translations()->where('locale', 'fr')->first()->name);
        $this->assertSame($sizeId, $product->sizes()->first()->id);
        $this->assertSame('12.0000', $product->sizes()->first()->width);
        $this->assertCount(2, $product->units()->get());
    }

    public function test_mass_assignment_and_unauthorized_mutations_fail_closed(): void
    {
        try {
            new Product(['slug' => 'unsafe', 'status' => 'published']);
            $this->fail('Guarded attributes were assigned.');
        } catch (MassAssignmentException) {
            $this->assertDatabaseCount('products', 0);
        }
        $inactive = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(CatalogueWriter::class)->category(null, ['slug' => 'unauthorized', 'translations' => Fixtures::copy('Denied')], $inactive);
    }

    public function test_media_eager_loading_keeps_query_count_constant(): void
    {
        $asset = Fixtures::image($this->admin, $this->root, 120);
        for ($i = 0; $i < 3; $i++) {
            $this->product(['slug' => 'with-media-'.$i, 'cover_media_id' => $asset->id]);
        }
        $counts = [];
        foreach ([1, 3] as $perPage) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->getJson('/api/v1/products?per_page='.$perPage)->assertOk()->assertJsonPath('data.0.cover.id', $asset->id);
            $counts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThanOrEqual(12, $counts[1]);
    }

    public function test_registry_changes_and_invalid_direct_cover_cannot_leak_into_public_catalogue(): void
    {
        $product = $this->product();
        config(['catalogue.quantity_units' => ['piece']]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        config(['catalogue.quantity_units' => ['m2', 'piece'], 'catalogue.measurement_units' => ['mm']]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        config(['catalogue.measurement_units' => ['mm', 'cm', 'm']]);
        $asset = Fixtures::image($this->admin, $this->root, 155);
        app(MediaLibrary::class)->unpublish($asset, $this->admin);
        $product->forceFill(['cover_media_id' => $asset->id])->save();
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
    }
}
