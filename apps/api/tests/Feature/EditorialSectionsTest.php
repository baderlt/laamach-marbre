<?php

namespace Tests\Feature;

use App\Catalogue\CatalogueWriter;
use App\Editorial\SectionForm;
use App\Editorial\SectionLinks;
use App\Editorial\SiteContentWriter;
use App\Filament\Resources\SiteContentPages\ManageSiteContent;
use App\Models\SiteContent;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CatalogueFixtures;
use Tests\Support\ProjectFixtures;
use Tests\TestCase;

class EditorialSectionsTest extends TestCase
{
    // The populated local preview must never be migrated, truncated or reseeded by these tests.
    use DatabaseTransactions;

    private function actor(): User
    {
        return User::factory()->create(['active' => true, 'role' => 'super_admin']);
    }

    private function input(): array
    {
        return ['key' => 'home', 'published' => true, 'data' => [], 'translations' => [
            'fr' => ['title' => 'Accueil', 'body' => 'Introduction FR', 'sections' => [
                ['group' => 'services', 'title' => 'Façades', 'body' => 'Texte FR', 'icon' => 'building', 'media_asset_id' => null, 'link' => 'projects'],
                ['group' => 'trust', 'title' => 'Conseil', 'link' => 'contact'],
            ]],
            'ar' => ['title' => 'الرئيسية', 'body' => 'مقدمة', 'sections' => [
                ['group' => 'services', 'title' => 'الواجهات', 'body' => 'نص عربي', 'icon' => 'stone', 'media_asset_id' => null, 'link' => 'products'],
                ['group' => 'trust', 'title' => 'استشارة', 'link' => 'contact'],
            ]],
        ]];
    }

    public function test_legacy_translations_roundtrip_in_one_repeater_without_losing_locale_metadata(): void
    {
        $input = $this->input();
        $form = SectionForm::fill($input);
        $this->assertCount(2, $form['sections']);
        $this->assertArrayNotHasKey('sections', $form['translations']['fr']);
        $this->assertSame('Façades', $form['sections'][0]['translations']['fr']['title']);
        $this->assertSame('الواجهات', $form['sections'][0]['translations']['ar']['title']);
        $this->assertEquals($input, SectionForm::input($form));

        $form['sections'] = array_reverse($form['sections']);
        $saved = SectionForm::input($form);
        $this->assertSame(['Conseil', 'Façades'], array_column($saved['translations']['fr']['sections'], 'title'));
        $this->assertSame(['استشارة', 'الواجهات'], array_column($saved['translations']['ar']['sections'], 'title'));
        array_pop($form['sections']);
        $saved = SectionForm::input($form);
        $this->assertCount(1, $saved['translations']['fr']['sections']);
        $this->assertCount(1, $saved['translations']['ar']['sections']);
    }

    public function test_unmatched_sections_are_preserved_and_an_empty_optional_translation_is_skipped(): void
    {
        $input = $this->input();
        $input['translations']['ar']['sections'][] = ['group' => 'story', 'title' => 'قصة'];
        $form = SectionForm::fill($input);
        $this->assertCount(3, $form['sections']);
        $this->assertEquals($input, SectionForm::input($form));
    }

    public function test_real_home_content_and_media_references_survive_an_unchanged_save(): void
    {
        $record = SiteContent::findOrFail('home');
        $before = $record->translations;
        $refs = $record->mediaReferences()->orderBy('slot')->get(['slot', 'media_asset_id'])->toArray();
        $input = collect($record->attributesToArray())->except(['created_at', 'updated_at'])->all();
        app(SiteContentWriter::class)->save($record, SectionForm::input(SectionForm::fill($input)), $this->actor());
        $this->assertEquals($before, $record->fresh()->translations);
        $this->assertEquals($refs, $record->mediaReferences()->orderBy('slot')->get(['slot', 'media_asset_id'])->toArray());
    }

    public function test_filament_saves_both_languages_and_public_category_destinations_from_one_card(): void
    {
        $actor = $this->actor();
        $suffix = strtolower(Str::random(8));
        $writer = app(CatalogueWriter::class);
        $parent = $writer->category(null, ['slug' => 'section-parent-'.$suffix, 'active' => true, 'translations' => CatalogueFixtures::copy('Section family')], $actor);
        $child = $writer->category(null, ['slug' => 'section-child-'.$suffix, 'parent_id' => $parent->id, 'active' => true, 'translations' => CatalogueFixtures::copy('Section material')], $actor);
        $usage = ProjectFixtures::category($actor, 'section-usage-'.$suffix);
        $input = $this->input();
        $input['translations']['fr']['sections'][0]['link'] = 'products?category='.$child->slug;
        $input['translations']['ar']['sections'][0]['link'] = 'projects?category='.$usage->slug;
        $this->assertContains('products?parent='.$parent->slug, SectionLinks::values('fr'));
        $record = SiteContent::findOrFail('home');
        $this->actingAs($actor);
        Livewire::test(ManageSiteContent::class)->callAction(TestAction::make('edit')->table($record), data: SectionForm::fill($input))->assertHasNoActionErrors();
        $saved = $record->fresh()->translations;
        $this->assertCount(2, $saved['fr']['sections']);
        $this->assertCount(2, $saved['ar']['sections']);
        $this->assertSame('Façades', $saved['fr']['sections'][0]['title']);
        $this->assertSame('الواجهات', $saved['ar']['sections'][0]['title']);
        foreach (['fr', 'ar'] as $locale) {
            $this->getJson('/api/v1/site?locale='.$locale)->assertOk()->assertJsonPath('data.pages.home.sections.0.link', $input['translations'][$locale]['sections'][0]['link']);
        }
    }

    public function test_unsafe_missing_and_inactive_targets_are_rejected_without_changing_content(): void
    {
        $actor = $this->actor();
        $category = app(CatalogueWriter::class)->category(null, ['slug' => 'section-inactive-'.strtolower(Str::random(8)), 'active' => false, 'translations' => CatalogueFixtures::copy('Inactive')], $actor);
        $record = SiteContent::findOrFail('home');
        $before = $record->translations;
        foreach (['javascript:alert(1)', '//example.com', 'https://example.com', 'products?category=missing', 'products?category='.$category->slug, 'products?category=test&redirect=evil', '../admin', 'projects?parent=test'] as $link) {
            $input = $this->input();
            $input['translations']['fr']['sections'][0]['link'] = $link;
            try {
                app(SiteContentWriter::class)->save($record, $input, $actor);
                $this->fail('An unsupported section destination was accepted.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('input.translations.fr.sections.0.link', $error->errors());
                $this->assertEquals($before, $record->fresh()->translations);
            }
        }
    }
}
