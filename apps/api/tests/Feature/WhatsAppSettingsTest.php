<?php

namespace Tests\Feature;

use App\Editorial\SiteContentWriter;
use App\Editorial\WhatsAppSettings;
use App\Filament\Resources\SiteContentPages\ManageSiteContent;
use App\Models\SiteContent;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    // Fictional test-only number; never seeded into client/business settings.
    private function config(array $changes = []): array
    {
        return $changes + ['number' => '+1 (202) 555-0123', 'enabled' => true, 'material' => true, 'similar' => true, 'application' => true];
    }

    private function save(array $config): SiteContent
    {
        $admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);

        return app(SiteContentWriter::class)->save(null, ['key' => 'site', 'published' => true, 'translations' => [], 'data' => ['whatsapp' => $config]], $admin);
    }

    public function test_number_normalization_and_invalid_formats(): void
    {
        foreach (['+1 (202) 555-0123', '0012025550123', '12025550123'] as $number) {
            $this->assertSame('12025550123', WhatsAppSettings::number($number));
        }
        foreach ([null, '', '2126XXXXXXXX', 'javascript:1', '+00000000', '1', '1234567890123456', '++12025550123', '12025550123?text=test'] as $number) {
            $this->assertNull(WhatsAppSettings::number($number));
        }
    }

    public function test_public_configuration_fails_closed_for_missing_invalid_and_disabled_destinations(): void
    {
        $record = $this->save([]);
        foreach ([[], $this->config(['number' => null]), $this->config(['number' => 'invalid']), $this->config(['enabled' => false])] as $config) {
            $record->forceFill(['data' => ['whatsapp' => $config]])->save();
            $this->getJson('/api/v1/site?locale=ar')->assertJsonPath('data.settings.whatsapp', ['number' => null, 'enabled' => false, 'material' => false, 'similar' => false, 'application' => false]);
        }
        $record->forceFill(['published' => false])->save();
        $this->getJson('/api/v1/site')->assertJsonPath('data.settings', null);
    }

    public function test_super_admin_can_manage_each_flow_in_filament_without_schema_changes(): void
    {
        $admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);
        Livewire::test(ManageSiteContent::class)->callAction(TestAction::make('create')->table(), data: ['key' => 'site', 'published' => true, 'translations' => [], 'data' => ['whatsapp' => $this->config()]])->assertHasNoActionErrors();
        $record = SiteContent::findOrFail('site');
        $this->assertSame('12025550123', $record->data['whatsapp']['number']);
        foreach (['material', 'similar', 'application'] as $flow) {
            $record->forceFill(['data' => ['whatsapp' => $this->config([$flow => false])]])->save();
            $this->getJson('/api/v1/site')->assertJsonPath('data.settings.whatsapp.'.$flow, false)->assertJsonPath('data.settings.whatsapp.enabled', true);
        }
    }

    public function test_invalid_and_unknown_configuration_is_rejected_without_writes(): void
    {
        foreach ([$this->config(['number' => '2126XXXXXXXX']), $this->config(['enabled' => 'yes']), $this->config(['template' => '{{execute}}'])] as $config) {
            try {
                $this->save($config);
                $this->fail('Invalid WhatsApp configuration was accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('site_contents', 0);
            }
        }
    }

    public function test_content_manager_cannot_change_sensitive_settings_but_omission_preserves_them(): void
    {
        $record = $this->save($this->config());
        $editor = User::factory()->create(['active' => true, 'role' => 'content_manager']);
        $writer = app(SiteContentWriter::class);
        $input = ['key' => 'site', 'published' => false, 'translations' => [], 'data' => ['email' => 'qa@example.invalid']];
        $writer->save($record, $input, $editor);
        $this->assertSame('12025550123', $record->fresh()->data['whatsapp']['number']);
        foreach ([$this->config(['number' => '12025550124']), [], $this->config(['enabled' => false])] as $config) {
            try {
                $writer->save($record, array_replace($input, ['data' => ['whatsapp' => $config]]), $editor);
                $this->fail('Content manager changed sensitive settings.');
            } catch (AuthorizationException) {
                $this->assertSame('12025550123', $record->fresh()->data['whatsapp']['number']);
                $this->assertTrue($record->fresh()->data['whatsapp']['enabled']);
            }
        }
    }
}
