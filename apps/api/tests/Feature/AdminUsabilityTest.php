<?php

namespace Tests\Feature;

use App\Catalogue\CatalogueWriter;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\MediaForms;
use App\Filament\Resources\ViewerForms;
use App\Filament\Widgets\CatalogueOverview;
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

class AdminUsabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $this->root = storage_path('framework/testing/'.getmypid().'/admin-uploads/'.Str::uuid());
        File::ensureDirectoryExists($this->root);
        foreach (['local', 'local_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/'.$disk, 'throw' => true]));
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function image(bool $publish = false): MediaAsset
    {
        return MediaAsset::findOrFail(MediaForms::upload(['upload' => UploadedFile::fake()->image('client-stone.png', 120, 80), 'client_supplied' => true, 'approve_and_publish' => $publish]));
    }

    public function test_direct_upload_stays_private_and_can_be_attached_to_a_product_draft(): void
    {
        $image = $this->image();
        $this->assertSame('ready', $image->processing_state);
        $this->assertSame('pending', $image->approval_state);
        $this->assertNull($image->active_revision);
        Storage::disk('local')->assertExists($image->original_key);
        $this->assertFalse($image->variants()->where('state', 'published')->exists());
        $product = app(CatalogueWriter::class)->product(null, ['slug' => 'direct-draft', 'status' => 'draft', 'translations' => CatalogueFixtures::copy('Direct upload'), 'cover_media_id' => $image->id, 'gallery' => [$image->id]], $this->admin);
        $this->assertSame($image->id, $product->cover_media_id);
        $this->assertSame(2, $image->references()->count());
        $this->getJson('/api/v1/products/direct-draft')->assertNotFound();
        $this->expectException(ValidationException::class);
        app(CatalogueWriter::class)->category(null, ['slug' => 'private-category', 'active' => false, 'translations' => CatalogueFixtures::copy('Private category'), 'cover_media_id' => $image->id], $this->admin);
    }

    public function test_explicit_inline_publication_can_attach_an_image_to_a_category_without_media_navigation(): void
    {
        $image = $this->image(true);
        $this->assertSame('approved', $image->approval_state);
        $this->assertNotNull($image->active_revision);
        $category = app(CatalogueWriter::class)->category(null, ['slug' => 'direct-category', 'active' => true, 'translations' => CatalogueFixtures::copy('Direct category'), 'cover_media_id' => $image->id], $this->admin);
        $this->assertSame($image->id, $category->cover_media_id);
        $this->assertSame(1, $image->references()->count());
        Storage::disk('local')->assertExists($image->original_key);
        $this->assertTrue($image->variants()->where('state', 'published')->exists());
    }

    public function test_content_managers_cannot_forge_inline_publication_and_inactive_users_cannot_upload(): void
    {
        foreach ([['active' => true, 'role' => 'content_manager'], ['active' => false, 'role' => 'super_admin']] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            try {
                $this->image(true);
                $this->fail('Unauthorized publication accepted.');
            } catch (AuthorizationException) {
                $this->assertSame(0, MediaAsset::count());
            }
        }
    }

    public function test_untrusted_paths_svg_and_corrupt_images_are_rejected_at_the_upload_field(): void
    {
        foreach ([['stone.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'], ['stone.png', "\x89PNG\r\nnot-an-image"], ['stone.jpg', '<?php echo 1;']] as [$name, $bytes]) {
            try {
                MediaForms::upload(['upload' => UploadedFile::fake()->createWithContent($name, $bytes), 'client_supplied' => true]);
                $this->fail('Invalid file accepted.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('upload', $error->errors());
                $this->assertSame(0, MediaAsset::count());
            }
        }
    }

    public function test_duplicate_upload_does_not_replace_published_media_or_editorial_identity(): void
    {
        $image = $this->image(true);
        $before = $image->attributesToArray();
        $variantCount = $image->variants()->count();
        $duplicate = $this->image();
        $this->assertSame($image->id, $duplicate->id);
        $this->assertSame($before, $duplicate->attributesToArray());
        $this->assertSame($variantCount, $duplicate->variants()->count());
        $this->assertSame(1, MediaAsset::count());
    }

    public function test_glb_inline_publication_remains_an_explicit_super_admin_operation(): void
    {
        $id = ViewerForms::upload(['model_upload' => UploadedFile::fake()->createWithContent('client.glb', ModelFixtures::glb()), 'client_supplied' => true, 'approve_and_publish' => true]);
        $model = MediaAsset::findOrFail($id);
        $this->assertSame('approved', $model->approval_state);
        $this->assertNotNull($model->active_revision);
        $this->assertSame('published', $model->variants()->firstOrFail()->state);
        Storage::disk('local')->assertExists($model->original_key);
    }

    public function test_dashboard_shows_actual_catalogue_navigation_and_creates_a_draft_through_the_writer(): void
    {
        $this->get('/admin')->assertOk()->assertSee('Tableau de bord');
        Livewire::test(Dashboard::class)->callAction(TestAction::make('createProduct'), data: ['slug' => 'dashboard-draft', 'status' => 'draft', 'translations' => CatalogueFixtures::copy('Dashboard draft'), 'units' => []])->assertHasNoActionErrors();
        $this->assertSame('draft', Product::where('slug', 'dashboard-draft')->firstOrFail()->status);
        Livewire::test(CatalogueOverview::class)->assertSee('Produits')->assertSee('1 en brouillon')->assertSee('Fichiers médias')->assertSee('/admin/products')->assertSee('/admin/media-assets');
        app()->setLocale('ar');
        Livewire::test(CatalogueOverview::class)->assertSee('المنتجات')->assertSee('ملفات الوسائط');
    }
}
