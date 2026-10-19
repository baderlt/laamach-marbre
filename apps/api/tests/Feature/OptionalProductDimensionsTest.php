<?php

namespace Tests\Feature;

use App\Catalogue\CatalogueWriter;
use App\Filament\Resources\CatalogueForms;
use App\Filament\Resources\CataloguePages\ManageProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CatalogueFixtures;
use Tests\TestCase;

class OptionalProductDimensionsTest extends TestCase
{
    use DatabaseTransactions;

    private function input(array $sizes): array
    {
        return ['slug' => 'dimensions-'.Str::lower(Str::random(10)), 'translations' => CatalogueFixtures::copy('Dimensions'), 'sizes' => $sizes];
    }

    private function dimensions(array $axes): array
    {
        return $axes + ['measurement_unit' => 'cm', 'active' => true, 'sort_order' => 0];
    }

    public function test_thickness_only_and_plan_dimensions_generate_bilingual_labels_without_invented_axes(): void
    {
        $actor = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $input = $this->input([
            $this->dimensions(['thickness' => '3']),
            $this->dimensions(['width' => '7', 'length' => '25']),
            $this->dimensions(['width' => '8', 'length' => '30', 'thickness' => '2.5']),
        ]);
        $product = app(CatalogueWriter::class)->product(null, $input, $actor);
        $sizes = $product->sizes()->orderBy('created_at')->get()->keyBy(fn ($size) => (string) $size->length);
        $this->assertCount(3, $sizes);
        $thickness = $sizes[''];
        $this->assertNull($thickness->width);
        $this->assertNull($thickness->length);
        $this->assertSame('3.0000', $thickness->thickness);
        $this->assertSame('Épaisseur : 3 cm', $thickness->translations()->where('locale', 'fr')->value('label'));
        $this->assertSame('السماكة: 3 سم', $thickness->translations()->where('locale', 'ar')->value('label'));
        $this->assertNull($sizes['25.0000']->thickness);
        $this->assertSame('25 × 7 cm', $sizes['25.0000']->translations()->where('locale', 'fr')->value('label'));
        $this->assertSame('25 × 7 سم', $sizes['25.0000']->translations()->where('locale', 'ar')->value('label'));
        $this->assertSame('30 × 8 cm · Épaisseur : 2.5 cm', $sizes['30.0000']->translations()->where('locale', 'fr')->value('label'));
    }

    public function test_dimensions_are_optional_and_existing_labels_and_measurements_survive_an_update(): void
    {
        $actor = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $writer = app(CatalogueWriter::class);
        $empty = $writer->product(null, $this->input([]), $actor);
        $this->assertSame(0, $empty->sizes()->count());
        $input = $this->input([CatalogueFixtures::size('Custom label', ['thickness' => '2.75'])]);
        $product = $writer->product(null, $input, $actor);
        $size = $product->sizes()->firstOrFail();
        $input['sizes'][0]['id'] = $size->id;
        $writer->product($product, $input, $actor);
        $this->assertSame($size->id, $product->sizes()->sole()->id);
        $this->assertSame('2.7500', $size->fresh()->thickness);
        $this->assertSame('Custom label', $size->translations()->where('locale', 'fr')->value('label'));
    }

    public function test_invalid_or_incomplete_dimensions_cannot_create_products(): void
    {
        $actor = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        foreach ([[], ['width' => 7], ['length' => 25, 'thickness' => 3], ['height' => 10], ['thickness' => 0], ['thickness' => -1], ['thickness' => 3, 'measurement_unit' => 'bad']] as $axes) {
            $input = $this->input([$this->dimensions($axes)]);
            try {
                app(CatalogueWriter::class)->product(null, $input, $actor);
                $this->fail('Invalid dimensions were accepted.');
            } catch (ValidationException $error) {
                $this->assertStringStartsWith('sizes.', array_key_first($error->errors()));
            }
            $this->assertFalse(Product::where('slug', $input['slug'])->exists());
        }
    }

    public function test_partial_dimensions_keep_size_ownership_validation(): void
    {
        $actor = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $writer = app(CatalogueWriter::class);
        $first = $writer->product(null, $this->input([$this->dimensions(['thickness' => 3])]), $actor);
        $foreignId = $first->sizes()->sole()->id;
        $second = $writer->product(null, $this->input([]), $actor);
        try {
            $writer->product($second, ['slug' => $second->slug, 'translations' => CatalogueFixtures::copy('Dimensions'), 'sizes' => [$this->dimensions(['id' => $foreignId, 'thickness' => 4])]], $actor);
            $this->fail('A dimension was moved across products.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('sizes', $error->errors());
        }
        $this->assertSame('3.0000', $first->sizes()->sole()->thickness);
        $this->assertSame(0, $second->sizes()->count());
    }

    public function test_admin_form_saves_thickness_only_with_empty_optional_labels(): void
    {
        $actor = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $product = app(CatalogueWriter::class)->product(null, $this->input([]), $actor);
        $data = CatalogueForms::data($product);
        $data['sizes'] = [$this->dimensions(['thickness' => 4, 'width' => null, 'length' => null, 'translations' => ['fr' => ['label' => ''], 'ar' => ['label' => '']]])];
        $this->actingAs($actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageProducts::class)->callAction(TestAction::make('edit')->table($product), data: $data)->assertHasNoActionErrors();
        $size = $product->sizes()->sole();
        $this->assertNull($size->width);
        $this->assertNull($size->length);
        $this->assertSame('4.0000', $size->thickness);
        $this->assertSame('Épaisseur : 4 cm', $size->translations()->where('locale', 'fr')->value('label'));
    }
}
