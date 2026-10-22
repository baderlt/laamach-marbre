<?php

namespace Tests\Feature;

use App\Editorial\SectionForm;
use App\Editorial\SiteContentWriter;
use App\Filament\Resources\SiteContentPages\ManageSiteContent;
use App\Media\MediaLibrary;
use App\Models\SiteContent;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CatalogueFixtures;
use Tests\TestCase;

class EditorialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $this->root = storage_path('framework/testing/'.getmypid().'/editorial/'.Str::uuid());
        File::ensureDirectoryExists($this->root);
        foreach (['local', 'local_published'] as $disk) {
            Storage::set($disk, Storage::build(['driver' => 'local', 'root' => $this->root.'/'.$disk, 'throw' => true]));
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function data(array $extra = []): array
    {
        return $extra + ['key' => 'home', 'published' => true, 'translations' => ['fr' => ['title' => 'Entered title', 'body' => 'Entered facts'], 'ar' => ['title' => 'عنوان اختبار']], 'data' => []];
    }

    public function test_site_api_is_empty_without_published_editorial_and_validates_locales(): void
    {
        $this->getJson('/api/v1/site')->assertOk()->assertJsonPath('data.settings', null)->assertJsonPath('data.pages', [])->assertJsonCount(0, 'data.featured_products');
        $this->getJson('/api/v1/site?locale=en')->assertUnprocessable();
        app(SiteContentWriter::class)->save(null, $this->data(['published' => false]), $this->admin);
        $this->getJson('/api/v1/site')->assertJsonMissingPath('data.pages.home');
    }

    public function test_configured_locales_sections_seo_and_contacts_are_admin_entered(): void
    {
        $writer = app(SiteContentWriter::class);
        $writer->save(null, $this->data(), $this->admin);
        $writer->save(null, $this->data(['key' => 'site', 'data' => ['phone' => '+212 600 000 000', 'email' => 'editorial@example.invalid', 'social_links' => [['label' => 'Instagram', 'url' => 'https://www.instagram.com/example/']]]]), $this->admin);
        $this->getJson('/api/v1/site?locale=ar')->assertJsonPath('data.pages.home.title', 'عنوان اختبار')->assertJsonPath('data.settings.phone', '+212 600 000 000');
        $record = SiteContent::findOrFail('home');
        $writer->save($record, $this->data(['translations' => ['fr' => ['title' => 'Changed', 'seo_title' => 'Entered SEO', 'sections' => [['group' => 'activities', 'title' => 'Entered activity', 'icon' => 'stone', 'link' => 'products']]]]]), $this->admin);
        $this->getJson('/api/v1/site')->assertJsonPath('data.pages.home.seo_title', 'Entered SEO')->assertJsonPath('data.pages.home.sections.0.title', 'Entered activity');
        $this->getJson('/api/v1/site?locale=ar')->assertJsonMissingPath('data.pages.home');
    }

    public function test_editorial_media_requires_approved_client_images_and_registers_deletion_protection(): void
    {
        $image = CatalogueFixtures::image($this->admin, $this->root, 40);
        $writer = app(SiteContentWriter::class);
        $writer->save(null, $this->data(['data' => ['hero_media_id' => $image->id]]), $this->admin);
        $this->assertDatabaseHas('media_references', ['owner_type' => 'site_contents', 'owner_key' => 'home', 'media_asset_id' => $image->id]);
        $response = $this->getJson('/api/v1/site')->assertJsonPath('data.pages.home.hero.id', $image->id);
        $this->assertStringNotContainsString('original_key', $response->getContent());
        app(MediaLibrary::class)->unpublish($image, $this->admin);
        $this->getJson('/api/v1/site')->assertJsonMissingPath('data.pages.home');
        $this->expectException(ValidationException::class);
        $writer->save(null, $this->data(['key' => 'about', 'data' => ['hero_media_id' => $image->id]]), $this->admin);
    }

    public function test_unknown_fields_raw_keys_links_and_unsupported_locales_are_rejected(): void
    {
        foreach ([['price' => 1], ['data' => ['hero_media_id' => '../private/key']], ['translations' => ['en' => ['title' => 'Disabled']]], ['data' => ['social_links' => [['label' => 'Unsafe', 'url' => 'javascript:alert(1)']]]], ['data' => ['social_links' => [['label' => 'Unknown', 'url' => 'https://example.invalid']]]]] as $input) {
            try {
                app(SiteContentWriter::class)->save(null, $this->data($input), $this->admin);
                $this->fail('Unsafe editorial input accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('site_contents', 0);
            }
        }
    }

    public function test_editorial_policy_and_filament_create_edit(): void
    {
        $this->get('/admin/site-contents')->assertRedirect('/admin/login');
        $this->actingAs($this->admin)->get('/admin/site-contents')->assertOk();
        Livewire::test(ManageSiteContent::class)->callAction(TestAction::make('create')->table(), data: $this->data(['published' => false]))->assertHasNoActionErrors();
        $record = SiteContent::findOrFail('home');
        Livewire::test(ManageSiteContent::class)->callAction(TestAction::make('edit')->table($record), data: $this->data())->assertHasNoActionErrors();
        $this->assertTrue($record->fresh()->published);
        $this->expectException(AuthorizationException::class);
        app(SiteContentWriter::class)->save(null, $this->data(['key' => 'services']), User::factory()->create(['active' => true]));
    }

    public function test_duplicate_editorial_keys_fail_validation_and_leave_existing_content_intact(): void
    {
        $writer = app(SiteContentWriter::class);
        $writer->save(null, $this->data(), $this->admin);
        try {
            $writer->save(null, $this->data(['translations' => ['fr' => ['title' => 'Overwrite attempt']]]), $this->admin);
            $this->fail('Duplicate key accepted.');
        } catch (ValidationException) {
            $this->assertSame('Entered title', SiteContent::findOrFail('home')->translations['fr']['title']);
        }
        $this->assertDatabaseCount('site_contents', 1);
    }

    public function test_section_cover_replacement_and_removal_updates_both_languages(): void
    {
        $first = CatalogueFixtures::image($this->admin, $this->root, 40);
        $replacement = CatalogueFixtures::image($this->admin, $this->root, 70);
        $translations = $this->data()['translations'];
        foreach (['fr', 'ar'] as $locale) {
            $translations[$locale]['sections'] = [['group' => 'services', 'title' => $locale.' section', 'media_asset_id' => $first->id, 'link' => 'products']];
        }
        $writer = app(SiteContentWriter::class);
        $record = $writer->save(null, $this->data(['translations' => $translations]), $this->admin);
        $form = SectionForm::fill($record->only(['key', 'published', 'translations', 'data']));
        $this->assertSame($first->id, $form['sections'][0]['media_asset_id']);
        $form['sections'][0]['media_asset_id'] = $replacement->id;
        $this->actingAs($this->admin);
        Livewire::test(ManageSiteContent::class)->callAction(TestAction::make('edit')->table($record), data: $form)->assertHasNoActionErrors();
        foreach (['fr', 'ar'] as $locale) {
            $this->getJson('/api/v1/site?locale='.$locale)->assertJsonPath('data.pages.home.sections.0.media.id', $replacement->id)->assertJsonPath('data.pages.home.sections.0.title', $locale.' section');
        }
        $form['sections'][0]['media_asset_id'] = null;
        $writer->save($record, SectionForm::input($form), $this->admin);
        foreach (['fr', 'ar'] as $locale) {
            $this->getJson('/api/v1/site?locale='.$locale)->assertJsonPath('data.pages.home.sections.0.media', null);
        }
        $this->assertDatabaseMissing('media_references', ['owner_type' => 'site_contents', 'owner_key' => 'home']);
    }

    public function test_shared_cover_does_not_create_an_untranslated_optional_section(): void
    {
        $input = SectionForm::input(['translations' => [], 'sections' => [['group' => 'services', 'media_asset_id' => (string) Str::uuid(), 'translations' => ['fr' => ['title' => 'Français']]]]]);
        $this->assertCount(1, $input['translations']['fr']['sections']);
        $this->assertSame([], $input['translations']['ar']['sections']);
    }
}
