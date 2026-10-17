<?php

namespace Tests\Feature;

use App\Catalogue\CatalogueWriter;
use App\Filament\Resources\CataloguePages\ManageProjectCategories;
use App\Filament\Resources\CataloguePages\ManageProjects;
use App\Filament\Resources\ProjectForms;
use App\Media\MediaLibrary;
use App\Models\Project;
use App\Models\User;
use App\Projects\ProjectWriter;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CatalogueFixtures as C;
use Tests\Support\ProjectFixtures as F;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $root;

    private $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $this->root = storage_path('framework/testing/'.getmypid().'/projects/'.Str::uuid());
        File::ensureDirectoryExists($this->root);
        foreach (['local', 'local_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/'.$disk, 'throw' => true]));
        }
        $this->category = F::category($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function data(array $extra = []): array
    {
        return $extra + ['slug' => 'synthetic-project', 'status' => 'published', 'categories' => [$this->category->id], 'translations' => F::copy()];
    }

    private function project(array $extra = []): Project
    {
        return app(ProjectWriter::class)->save(null, $this->data($extra), $this->admin);
    }

    public function test_category_crud_translations_order_cover_and_safe_deletion(): void
    {
        $image = C::image($this->admin, $this->root, 30);
        $first = F::category($this->admin, 'first', ['sort_order' => 0, 'cover_media_id' => $image->id]);
        app(ProjectWriter::class)->save($this->category, ['slug' => $this->category->slug, 'active' => true, 'sort_order' => 5, 'translations' => C::copy('Usage')], $this->admin, true);
        $this->getJson('/api/v1/project-categories?locale=ar')->assertOk()->assertJsonPath('data.0.slug', 'first')->assertJsonPath('data.0.cover.id', $image->id)->assertJsonPath('data.1.name', 'اختبار Usage');
        $this->project();
        try {
            app(ProjectWriter::class)->delete($this->category, $this->admin);
            $this->fail('Referenced category deleted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('project_categories', 2);
        }
        app(ProjectWriter::class)->delete($first, $this->admin);
        $this->assertDatabaseCount('media_references', 0);
        $this->assertDatabaseCount('project_category_translations', 2);
    }

    public function test_project_crud_optional_facts_reference_first_publication_and_slug_stability(): void
    {
        $project = $this->project(['year' => 2024, 'location' => 'Synthetic location', 'area' => 14.5, 'area_unit' => 'm2']);
        $this->assertMatchesRegularExpression('/^LP-[A-Z0-9]{12}$/', $project->reference);
        $this->getJson('/api/v1/projects/'.$project->slug.'?locale=ar')->assertOk()->assertJsonPath('data.title', 'اختبار Synthetic project')->assertJsonPath('data.year', 2024)->assertJsonPath('data.location', 'Synthetic location')->assertJsonPath('data.area', '14.50')->assertJsonPath('data.area_unit', 'm2');
        $stamp = $project->published_at->toISOString();
        $saved = app(ProjectWriter::class)->save($project, $this->data(['status' => 'draft', 'translations' => F::copy('Renamed')]), $this->admin);
        $this->getJson('/api/v1/projects/'.$project->slug)->assertNotFound();
        $saved = app(ProjectWriter::class)->save($saved, $this->data(), $this->admin);
        $this->assertSame($stamp, $saved->published_at->toISOString());
        $this->assertSame($project->reference, $saved->reference);
        foreach (['slug' => 'changed', 'reference' => 'LP-ALTERED'] as $key => $value) {
            try {
                app(ProjectWriter::class)->save($saved, $this->data([$key => $value]), $this->admin);
                $this->fail('Identity changed.');
            } catch (ValidationException) {
                $this->assertSame($project->slug, $saved->fresh()->slug);
            }
        }
        app(ProjectWriter::class)->delete($saved, $this->admin);
        $this->assertDatabaseCount('project_category_project', 0);
        $this->assertDatabaseCount('project_translations', 0);
    }

    public function test_multicategory_or_filter_and_pagination_do_not_duplicate_projects(): void
    {
        $second = F::category($this->admin, 'second');
        $this->project(['categories' => [$this->category->id, $second->id]]);
        $this->project(['slug' => 'second-project', 'categories' => [$second->id]]);
        $this->getJson('/api/v1/projects?categories[]=synthetic-usage&categories[]=second&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/v1/projects?category=synthetic-usage')->assertJsonCount(1, 'data')->assertJsonCount(2, 'data.0.categories');
        $this->getJson('/api/v1/projects?category=second')->assertJsonCount(2, 'data');
        $this->assertDatabaseCount('project_category_project', 3);
    }

    public function test_one_inactive_category_keeps_project_public_all_inactive_hides_it(): void
    {
        $second = F::category($this->admin, 'second');
        $project = $this->project(['categories' => [$this->category->id, $second->id]]);
        $this->category->forceFill(['active' => false])->save();
        $this->getJson('/api/v1/projects/'.$project->slug)->assertOk()->assertJsonCount(1, 'data.categories')->assertJsonPath('data.categories.0.slug', 'second');
        $this->getJson('/api/v1/projects?category=synthetic-usage')->assertJsonCount(0, 'data');
        $second->forceFill(['active' => false])->save();
        $this->getJson('/api/v1/projects/'.$project->slug)->assertNotFound();
        $this->getJson('/api/v1/projects')->assertJsonCount(0, 'data');
    }

    public static function invalid(): array
    {
        return [[['categories' => []]], [['categories' => ['bad-id']]], [['cover_media_id' => '../private/key']], [['translations' => ['en' => ['title' => 'Disabled']]]], [['location' => str_repeat('x', 201)]], [['year' => 1]], [['area' => 12]], [['area_unit' => 'm2']], [['reference' => 'LP-INJECTED']], [['published_at' => '2020-01-01']], [['price' => 100]], [['gallery' => [['media_asset_id' => 'raw-object-key', 'type' => 'video']]]]];
    }

    #[DataProvider('invalid')]
    public function test_invalid_assignments_facts_locales_and_mass_assignment_are_atomic(array $extra): void
    {
        try {
            $this->project($extra);
            $this->fail('Invalid input accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('projects', 0);
            $this->assertDatabaseCount('project_category_project', 0);
        }
    }

    public function test_inactive_missing_and_duplicate_category_assignments_are_rejected(): void
    {
        foreach ([[$this->category->id, $this->category->id], [(string) Str::uuid()]] as $ids) {
            try {
                $this->project(['categories' => $ids]);
                $this->fail('Invalid relation accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('projects', 0);
            }
        }
        $this->category->forceFill(['active' => false])->save();
        $this->expectException(ValidationException::class);
        $this->project(['status' => 'draft']);
    }

    public function test_requested_locale_drafts_and_invalid_filter_bounds_do_not_leak(): void
    {
        $project = $this->project(['translations' => ['fr' => ['title' => 'French only']]]);
        $this->project(['slug' => 'draft', 'status' => 'draft']);
        $this->getJson('/api/v1/projects?locale=ar')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/projects/'.$project->slug.'?locale=ar')->assertNotFound();
        $this->getJson('/api/v1/projects/draft')->assertNotFound();
        foreach (['locale=en', 'per_page=999', 'page=10001', 'categories[]=second&categories[]=second', 'categories=invalid', 'category=../unsafe'] as $query) {
            $this->getJson('/api/v1/projects?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/v1/project-categories?locale=en')->assertUnprocessable();
    }

    public function test_confirmed_materials_bidirectional_eligibility_order_bounds_and_delete_protection(): void
    {
        $catalogue = C::catalogue($this->admin);
        $writer = app(CatalogueWriter::class);
        $products = [];
        foreach (['first', 'second', 'draft'] as $slug) {
            $products[] = $writer->product(null, ['slug' => $slug, 'category_id' => $catalogue['child']->id, 'status' => $slug === 'draft' ? 'draft' : 'published', 'translations' => C::copy($slug), 'units' => ['m2'], 'sizes' => [C::size()]], $this->admin);
        }
        $relations = array_map(fn ($product, $index) => ['product_id' => $product->id, 'sort_order' => 3 - $index], $products, array_keys($products));
        $project = $this->project(['products' => $relations]);
        $this->project(['slug' => 'draft-project', 'status' => 'draft', 'products' => $relations]);
        $this->getJson('/api/v1/projects/'.$project->slug)->assertJsonCount(2, 'data.products')->assertJsonPath('data.products.0.slug', 'second');
        $this->getJson('/api/v1/products/first')->assertJsonCount(1, 'data.projects')->assertJsonPath('data.projects.0.slug', $project->slug);
        try {
            $writer->delete($products[0], $this->admin);
            $this->fail('Confirmed material deleted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 3);
        }
        try {
            $this->project(['slug' => 'duplicate-material', 'products' => [$relations[0], $relations[0]]]);
            $this->fail('Duplicate material accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('projects', 2);
        }
        for ($i = 0; $i < 7; $i++) {
            $this->project(['slug' => 'related-'.$i, 'products' => [$relations[0]]]);
        }
        $this->getJson('/api/v1/products/first')->assertJsonCount(6, 'data.projects');
        config(['projects.max_products' => 1]);
        $this->getJson('/api/v1/projects/'.$project->slug)->assertJsonCount(1, 'data.products');
    }

    public function test_photo_video_poster_order_captions_and_private_reference_exclusion(): void
    {
        $image = C::image($this->admin, $this->root, 70);
        $video = F::video($this->admin, $this->root);
        $gallery = [['type' => 'video', 'media_asset_id' => $video->id, 'poster_media_id' => $image->id, 'captions' => ['fr' => 'Synthetic video', 'ar' => 'فيديو اختبار']], ['type' => 'image', 'media_asset_id' => $image->id, 'captions' => []]];
        $project = $this->project(['cover_media_id' => $image->id, 'gallery' => $gallery]);
        $this->assertDatabaseCount('media_references', 4);
        $response = $this->getJson('/api/v1/projects/'.$project->slug.'?locale=ar')->assertOk()->assertJsonPath('data.gallery.0.type', 'video')->assertJsonPath('data.gallery.0.caption', 'فيديو اختبار')->assertJsonPath('data.gallery.0.poster.id', $image->id)->assertJsonPath('data.gallery.1.type', 'image');
        $this->assertStringContainsString('/media/videos/', $response->json('data.gallery.0.media.variants.0.url'));
        foreach (['original_key', 'original_disk', 'source_path', 'object_key', 'approval_state'] as $secret) {
            $this->assertStringNotContainsString('"'.$secret.'"', $response->getContent());
        }
        $this->getJson('/api/v1/projects')->assertJsonMissingPath('data.0.gallery');
        $this->actingAs($this->admin);
        $form = ProjectForms::data($project->fresh());
        $form['gallery'] = array_reverse($form['gallery']);
        Livewire::test(ManageProjects::class)->callAction(TestAction::make('edit')->table($project), data: $form)->assertHasNoActionErrors();
        $this->getJson('/api/v1/projects/'.$project->slug)->assertJsonPath('data.gallery.0.type', 'image')->assertJsonPath('data.gallery.1.type', 'video');
        app(MediaLibrary::class)->unpublish($video, $this->admin);
        $this->getJson('/api/v1/projects/'.$project->slug)->assertNotFound();
        $this->expectException(ValidationException::class);
        $this->project(['slug' => 'private-video', 'gallery' => $gallery]);
    }

    public function test_video_without_poster_and_wrong_media_type_are_handled(): void
    {
        $video = F::video($this->admin, $this->root);
        $project = $this->project(['gallery' => [['type' => 'video', 'media_asset_id' => $video->id]]]);
        $this->getJson('/api/v1/projects/'.$project->slug)->assertOk()->assertJsonPath('data.gallery.0.poster', null);
        foreach (['cover_media_id' => $video->id, 'gallery' => [['type' => 'image', 'media_asset_id' => $video->id]]] as $key => $value) {
            try {
                $this->project(['slug' => 'wrong-role', $key => $value]);
                $this->fail('Wrong media role accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('projects', 1);
            }
        }
    }

    public function test_project_media_query_count_stays_constant_and_relations_do_not_recurse(): void
    {
        $image = C::image($this->admin, $this->root, 180);
        for ($i = 0; $i < 4; $i++) {
            $this->project(['slug' => 'query-'.$i, 'cover_media_id' => $image->id]);
        }
        $counts = [];
        foreach ([1, 4] as $limit) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->getJson('/api/v1/projects?per_page='.$limit)->assertOk();
            $counts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        $this->assertSame($counts[0], $counts[1]);
        $this->assertLessThanOrEqual(12, $counts[1]);
    }

    public function test_filament_category_and_project_create_edit_delete_and_authorization(): void
    {
        $this->get('/admin/projects')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/projects')->assertForbidden();
        $this->actingAs($this->admin);
        foreach (['projects', 'project-categories'] as $route) {
            $this->get('/admin/'.$route)->assertOk();
        }
        Livewire::test(ManageProjectCategories::class)->callAction(TestAction::make('create')->table(), data: ['slug' => 'admin-usage', 'active' => true, 'sort_order' => 2, 'translations' => C::copy('Admin usage')])->assertHasNoActionErrors();
        Livewire::test(ManageProjects::class)->callAction(TestAction::make('create')->table(), data: $this->data(['status' => 'draft']))->assertHasNoActionErrors();
        $project = Project::where('slug', 'synthetic-project')->firstOrFail();
        $data = ProjectForms::data($project);
        $data['status'] = 'published';
        $data['location'] = 'Admin entered';
        Livewire::test(ManageProjects::class)->callAction(TestAction::make('edit')->table($project), data: $data)->assertHasNoActionErrors();
        $this->assertSame('Admin entered', $project->fresh()->location);
        Livewire::test(ManageProjects::class)->callAction(TestAction::make('delete')->table($project))->assertHasNoActionErrors();
        $this->assertDatabaseCount('projects', 0);
        $this->expectException(AuthorizationException::class);
        app(ProjectWriter::class)->save(null, $this->data(), User::factory()->create(['active' => true]));
    }
}
