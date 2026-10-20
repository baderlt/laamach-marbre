<?php

namespace Tests\Feature;

use App\Catalogue\CatalogueWriter;
use App\Catalogue\ProductViewer;
use App\Filament\Resources\CatalogueForms;
use App\Filament\Resources\CataloguePages\ManageProducts;
use App\Filament\Resources\ViewerForms;
use App\Media\MediaLibrary;
use App\Media\MediaStorage;
use App\Media\ModelLibrary;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CatalogueFixtures;
use Tests\Support\ModelFixtures;
use Tests\TestCase;

class ProductViewerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $root;

    private array $catalogue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $this->root = storage_path('framework/testing/'.getmypid().'/viewer/'.Str::uuid());
        File::ensureDirectoryExists($this->root);
        foreach (['local', 'local_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/'.$disk, 'throw' => true]));
        }
        $this->catalogue = CatalogueFixtures::catalogue($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function model(float $tone = .65): MediaAsset
    {
        [$j,$bin] = ModelFixtures::document(true);
        $j['materials'][0]['pbrMetallicRoughness']['baseColorFactor'][0] = $tone;
        $path = $this->root.'/'.Str::uuid().'.glb';
        File::put($path, ModelFixtures::pack($j, $bin));

        return app(ModelLibrary::class)->ingest($path, 'products/synthetic-model.glb', true);
    }

    private function product(array $changes = []): Product
    {
        return app(CatalogueWriter::class)->product(null, $changes + ['slug' => 'synthetic-viewer-'.strtolower(Str::random(8)), 'status' => 'published', 'category_id' => $this->catalogue['child']->id, 'translations' => CatalogueFixtures::copy('Synthetic viewer product'), 'units' => ['piece']], $this->admin);
    }

    private function configure(Product $product, bool $enabled, ?array $config, ?User $user = null): Product
    {
        $data = CatalogueForms::clean(CatalogueForms::data($product->fresh()));

        return app(CatalogueWriter::class)->product($product, array_replace($data, ['viewer_3d_enabled' => $enabled, 'viewer_3d_config' => $config]), $user ?? $this->admin)->fresh();
    }

    private function publish(MediaAsset $asset): MediaAsset
    {
        $library = app(MediaLibrary::class);
        $library->approve($asset, $this->admin);
        $library->publish($asset, $asset->variants()->firstOrFail()->revision, $this->admin);

        return $asset->fresh();
    }

    public function test_uploaded_models_are_private_validated_immutable_and_authentication_protected(): void
    {
        $model = $this->model();
        $variant = $model->variants()->firstOrFail();
        $this->assertSame('ready', $model->processing_state);
        $this->assertSame('private', $variant->state);
        $this->assertSame('model/gltf-binary', $model->mime);
        Storage::disk('local')->assertExists($model->original_key);
        Storage::disk('local_published')->assertMissing($variant->object_key);
        $this->assertStringNotContainsString('synthetic-private-metadata', app(MediaStorage::class)->variantBytes($variant));
        $this->getJson('/admin/viewer-model/'.$variant->id)->assertUnauthorized();
        $response = $this->actingAs($this->admin)->get('/admin/viewer-model/'.$variant->id)->assertOk()->assertHeader('Content-Type', 'model/gltf-binary');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($variant->checksum, hash('sha256', $response->getContent()));
        $this->assertSame($variant->revision, app(ModelLibrary::class)->process($model));
        $this->assertSame(1, $model->variants()->count());
    }

    public function test_public_disable_retains_configuration_and_private_model_references(): void
    {
        $model = $this->model();
        $product = $this->configure($this->product(), false, ['mode' => 'uploaded_glb', 'model_media_id' => $model->id]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.viewer_3d', null);
        $this->assertDatabaseHas('media_references', ['owner_type' => 'product_viewers', 'owner_key' => $product->id, 'media_asset_id' => $model->id]);
        $this->actingAs($this->admin)->get('/admin/product-viewer/'.$product->id)->assertOk()->assertSee('viewer-model')->assertDontSee('original_key')->assertDontSee('r2_private');
        $this->expectException(AuthorizationException::class);
        app(MediaLibrary::class)->delete($model, $this->admin);
    }

    public function test_published_model_is_detail_only_and_unpublication_keeps_photos_available(): void
    {
        $model = $this->publish($this->model());
        $variant = $model->variants()->firstOrFail();
        $product = $this->configure($this->product(), true, ['mode' => 'uploaded_glb', 'model_media_id' => $model->id, 'model_variant_id' => $variant->id]);
        $response = $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.viewer_3d.mode', 'uploaded_glb')->assertJsonPath('data.viewer_3d.model.hash', $variant->checksum);
        $this->assertStringNotContainsString('originals/', $response->getContent());
        $this->assertStringNotContainsString('original_key', $response->getContent());
        $this->getJson('/api/v1/products')->assertJsonMissingPath('data.0.viewer_3d')->assertJsonMissingPath('data.0.viewer_3d_config');
        $this->withHeaders(['Origin' => 'http://127.0.0.1:3105'])->get('/media/'.$variant->object_key)->assertOk()->assertHeader('Content-Type', 'model/gltf-binary')->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:3105');
        $this->withHeaders(['Origin' => 'https://untrusted.invalid'])->get('/media/'.$variant->object_key)->assertOk()->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->flushHeaders();
        $this->configure($product, false, $product->viewer_3d_config);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.viewer_3d', null);
        $this->assertSame($model->id, $product->fresh()->viewer_3d_config['model_media_id']);
        $product = $this->configure($product, true, $product->viewer_3d_config);
        app(MediaLibrary::class)->unpublish($model, $this->admin);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.viewer_3d', null);
        $this->configure($product, false, $product->viewer_3d_config); // Disabling remains possible after eligibility is lost.
    }

    public function test_private_unapproved_incorrect_and_unauthorized_assignments_fail_closed(): void
    {
        $private = $this->model();
        $product = $this->product();
        $image = CatalogueFixtures::image($this->admin, $this->root, 80);
        foreach ([['mode' => 'uploaded_glb', 'model_media_id' => $private->id], ['mode' => 'uploaded_glb', 'model_media_id' => $image->id], ['mode' => 'uploaded_glb', 'model_media_id' => Str::uuid()], ['mode' => 'procedural', 'javascript' => 'alert(1)']] as $config) {
            try {
                $this->configure($product, true, $config);
                $this->fail('Invalid configuration published.');
            } catch (ValidationException) {
                $this->assertFalse($product->fresh()->viewer_3d_enabled);
            }
        }
        $manager = User::factory()->create(['active' => true, 'role' => 'content_manager']);
        try {
            app(MediaLibrary::class)->approve($private, $manager);
            $this->fail('Manager approved public model use.');
        } catch (AuthorizationException) {
            $this->assertSame('pending', $private->fresh()->approval_state);
        }
        $this->expectException(AuthorizationException::class);
        $this->configure($product, true, ['mode' => 'uploaded_glb', 'model_media_id' => $private->id], $manager);
    }

    public function test_replacement_and_removal_do_not_overwrite_or_delete_original_models(): void
    {
        $first = $this->publish($this->model(.3));
        $second = $this->publish($this->model(.8));
        $variant = $first->variants()->firstOrFail();
        $old = app(MediaStorage::class)->variantBytes($variant);
        $product = $this->configure($this->product(), true, ['mode' => 'uploaded_glb', 'model_media_id' => $first->id]);
        $product = $this->configure($product, true, ['mode' => 'uploaded_glb', 'model_media_id' => $second->id]);
        $this->assertNotSame($variant->object_key, $second->variants()->firstOrFail()->object_key);
        $this->assertSame($old, app(MediaStorage::class)->variantBytes($variant));
        $this->assertDatabaseMissing('media_references', ['owner_type' => 'product_viewers', 'owner_key' => $product->id, 'media_asset_id' => $first->id]);
        $this->configure($product, false, ['mode' => 'uploaded_glb', 'model_media_id' => null]);
        Storage::disk('local')->assertExists($second->original_key);
    }

    public function test_procedural_fixed_dimensions_require_verification_explicit_units_and_supported_geometry(): void
    {
        $config = ['mode' => 'procedural', 'geometry' => 'block', 'dimension_source' => 'fixed', 'dimensions_verified' => true, 'width' => 1500, 'length' => 500, 'depth' => 20, 'measurement_unit' => 'mm'];
        $product = $this->configure($this->product(), true, $config);
        $this->getJson('/api/v1/products/'.$product->slug)->assertJsonPath('data.viewer_3d.dimensions_m', ['width' => 1.5, 'length' => .5, 'depth' => .02]);
        foreach ([['geometry' => 'sphere'], ['measurement_unit' => null], ['dimensions_verified' => false], ['depth' => null], ['width' => -1], ['length' => 'INF'], ['depth' => 200000]] as $change) {
            try {
                $this->configure($product, true, array_replace($config, $change));
                $this->fail('Unsupported geometry accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(['width' => 1.0, 'length' => 2.0, 'depth' => .03], app(ProductViewer::class)->dimensions(100, 200, 3, 'cm'));
    }

    public function test_procedural_sizes_use_only_complete_active_assigned_dimensions(): void
    {
        $product = $this->product(['sizes' => [CatalogueFixtures::size('20', ['width' => 70, 'length' => 200, 'thickness' => 20, 'measurement_unit' => 'mm']), CatalogueFixtures::size('Incomplete', ['sort_order' => 1])]]);
        $size = $product->sizes()->firstOrFail();
        $config = ['mode' => 'procedural', 'geometry' => 'slab', 'dimension_source' => 'product_sizes', 'default_size_id' => $size->id];
        $product = $this->configure($product, true, $config);
        $response = $this->getJson('/api/v1/products/'.$product->slug)->assertJsonCount(1, 'data.viewer_3d.sizes')->assertJsonPath('data.viewer_3d.dimensions_m', ['width' => .07, 'length' => .2, 'depth' => .02]);
        $this->assertStringNotContainsString('20 cm', $response->getContent());
        try {
            $this->configure($product, true, array_replace($config, ['default_size_id' => Str::uuid()]));
            $this->fail('Unassigned size accepted.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $size->forceFill(['thickness' => null])->save();
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.viewer_3d', null);
    }

    public function test_filament_controls_save_reload_and_validate_real_uploaded_content(): void
    {
        $this->actingAs($this->admin);
        $asset = MediaAsset::findOrFail(ViewerForms::upload(['model_upload' => UploadedFile::fake()->createWithContent('synthetic-admin.glb', ModelFixtures::glb()), 'client_supplied' => true]));
        $this->assertSame('model', $asset->type);
        $this->assertSame('pending', $asset->approval_state);
        $product = $this->product(['status' => 'draft']);
        $data = CatalogueForms::clean(CatalogueForms::data($product));
        $data['viewer_3d_enabled'] = true;
        $data['viewer_3d_config'] = ['mode' => 'uploaded_glb', 'model_media_id' => $asset->id];
        Livewire::test(ManageProducts::class)->callAction(TestAction::make('edit')->table($product), data: $data)->assertHasNoActionErrors();
        $this->assertTrue($product->fresh()->viewer_3d_enabled);
        $data = CatalogueForms::clean(CatalogueForms::data($product->fresh()));
        $data['viewer_3d_enabled'] = false;
        Livewire::test(ManageProducts::class)->callAction(TestAction::make('edit')->table($product), data: $data)->assertHasNoActionErrors();
        $this->assertFalse($product->fresh()->viewer_3d_enabled);
        $this->assertSame($asset->id, $product->fresh()->viewer_3d_config['model_media_id']);
        $this->expectException(ValidationException::class);
        ViewerForms::upload(['model_upload' => UploadedFile::fake()->createWithContent('fake.glb', 'not a GLB')]);
    }
}
