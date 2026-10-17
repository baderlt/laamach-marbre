<?php

namespace Tests\Feature;

use App\Catalogue\CatalogueWriter;
use App\Media\AssetImporter;
use App\Media\ClientProductAssets;
use App\Media\MediaLibrary;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CatalogueFixtures as F;
use Tests\TestCase;

class ClientProductAssetsTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/'.getmypid().'/client-products/'.Str::uuid());
        File::ensureDirectoryExists($this->root.'/products/explicit-product/gallery');
        foreach (['local', 'local_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/disks/'.$disk, 'throw' => true]));
        }
        $entries = [];
        foreach (['cover.png', 'gallery/01.png'] as $index => $name) {
            $path = $this->root.'/products/explicit-product/'.$name;
            $image = imagecreatetruecolor(120, 80);
            imagefill($image, 0, 0, imagecolorallocate($image, 50 + $index * 60, 100, 130));
            imagepng($image, $path);
            $entries[] = ['source' => 'products/explicit-product/'.$name, 'target_type' => 'product', 'target_key' => 'explicit-product', 'role' => $index ? 'product_gallery' : 'product_cover', 'order' => $index, 'sha256' => hash_file('sha256', $path), 'client_supplied' => true];
        }
        File::put($this->root.'/assets-map.json', json_encode($entries));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_dry_run_is_read_only_then_apply_stages_private_images_and_incomplete_drafts(): void
    {
        $service = app(ClientProductAssets::class);
        $dry = $service->run($this->root, false, true);
        $this->assertSame(2, $dry['accepted']);
        $this->assertSame(0, $dry['ambiguous']);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('products', 0);
        $result = $service->run($this->root, true, true);
        $this->assertSame('created-draft', $result['products'][0]['result']);
        $product = Product::firstOrFail();
        $this->assertNull($product->category_id);
        $this->assertSame('draft', $product->status);
        $this->assertDatabaseCount('product_sizes', 0);
        $this->assertDatabaseCount('product_units', 0);
        $this->assertDatabaseCount('media_references', 2);
        $this->assertDatabaseHas('media_assets', ['client_supplied' => true, 'approval_state' => 'pending', 'active_revision' => null]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        $this->assertSame([], Storage::disk('local_published')->allFiles());
        $again = $service->run($this->root, true, true);
        $this->assertSame('preserved-existing', $again['products'][0]['result']);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('media_assets', 2);
    }

    public function test_checksum_changed_sources_and_ambiguous_inventory_prevent_apply(): void
    {
        file_put_contents($this->root.'/products/explicit-product/extra.png', file_get_contents($this->root.'/products/explicit-product/cover.png'));
        try {
            app(ClientProductAssets::class)->run($this->root, true, true);
            $this->fail('Ambiguous file applied.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('media_assets', 0);
        }
        unlink($this->root.'/products/explicit-product/extra.png');
        $entries = json_decode(file_get_contents($this->root.'/assets-map.json'), true);
        $entries[0]['sha256'] = str_repeat('0', 64);
        file_put_contents($this->root.'/assets-map.json', json_encode($entries));
        $this->expectException(ValidationException::class);
        app(ClientProductAssets::class)->run($this->root, true, true);
    }

    public function test_editorial_changes_and_publication_guards_are_preserved(): void
    {
        app(ClientProductAssets::class)->run($this->root, true, true);
        $product = Product::firstOrFail();
        $product->translations()->where('locale', 'fr')->update(['name' => 'Admin edited']);
        app(ClientProductAssets::class)->run($this->root, true, true);
        $this->assertSame('Admin edited', $product->translations()->first()->name);
        $this->expectException(ValidationException::class);
        app(CatalogueWriter::class)->product($product, ['slug' => $product->slug, 'status' => 'published', 'translations' => F::copy('Cannot publish')], User::factory()->create(['active' => true, 'role' => 'super_admin']));
    }

    public function test_explicit_size_id_mapping_rejects_cross_product_and_requires_approved_media(): void
    {
        $user = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $fixture = F::catalogue($user);
        $product = app(CatalogueWriter::class)->product(null, ['slug' => 'explicit-product', 'status' => 'draft', 'category_id' => $fixture['child']->id, 'units' => ['m2'], 'sizes' => [F::size()], 'translations' => F::copy('Explicit')], $user);
        $size = $product->sizes()->firstOrFail();
        $entries = json_decode(file_get_contents($this->root.'/assets-map.json'), true);
        $entries[1]['role'] = 'product_size';
        $entries[1]['size_id'] = $size->id;
        file_put_contents($this->root.'/assets-map.json', json_encode($entries));
        $this->assertSame(2, app(AssetImporter::class)->run($this->root)['accepted']);
        $result = app(ClientProductAssets::class)->run($this->root, true, true);
        $this->assertSame('awaiting-approved-published-size-media', $result['products'][1]['result']);
        $image = MediaAsset::where('original_filename', '01.png')->firstOrFail();
        $library = app(MediaLibrary::class);
        $library->approve($image, $user);
        $library->publish($image, $image->variants()->first()->revision, $user);
        $this->assertSame('assigned-confirmed-size', app(CatalogueWriter::class)->stageConfirmedSizeImage($product->slug, $size->id, $image->id));
        $this->assertSame($image->id, $size->fresh()->cover_media_id);
        $entries[1]['target_key'] = 'other-product';
        file_put_contents($this->root.'/assets-map.json', json_encode($entries));
        $this->expectException(ValidationException::class);
        app(AssetImporter::class)->run($this->root);
    }

    public function test_search_sort_and_draft_images_cannot_leak_through_public_catalogue(): void
    {
        $user = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $fixture = F::catalogue($user);
        foreach (['zebra', 'alpha'] as $slug) {
            app(CatalogueWriter::class)->product(null, ['slug' => $slug, 'status' => 'published', 'category_id' => $fixture['child']->id, 'units' => ['piece'], 'translations' => F::copy($slug)], $user);
        }
        app(ClientProductAssets::class)->run($this->root, true, true);
        $this->getJson('/api/v1/products?sort=name')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.slug', 'alpha');
        $this->getJson('/api/v1/products?q=zebra')->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'zebra');
        $this->getJson('/api/v1/products?q=Explicit')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products?q=%25')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products?sort=bestselling')->assertUnprocessable();
        foreach (MediaAsset::all() as $asset) {
            $this->get('/media/'.$asset->original_key)->assertNotFound();
        }
    }
}
