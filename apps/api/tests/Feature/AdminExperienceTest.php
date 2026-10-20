<?php

namespace Tests\Feature;

use App\Editorial\SiteContentWriter;
use App\Filament\Pages\ContactSettings;
use App\Filament\Pages\Profile;
use App\Filament\Resources\MediaForms;
use App\Models\MediaAsset;
use App\Models\SiteContent;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminExperienceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $this->actingAs($this->admin);
    }

    public function test_interface_language_is_bounded_and_account_scoped_without_changing_public_defaults(): void
    {
        $other = User::factory()->create(['active' => true]);
        $this->post('/admin/interface-language', ['locale' => 'ar', 'user_id' => $other->id, 'role' => 'super_admin'])->assertRedirect('/admin');
        $this->assertSame('ar', $this->admin->fresh()->admin_locale);
        $this->assertNull($other->fresh()->admin_locale);
        $this->assertSame('content_manager', $other->fresh()->role);
        $this->assertSame('fr', config('localization.default'));
        $this->get('/admin')->assertOk()->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false)->assertSee('ملفي الشخصي');
        $this->postJson('/admin/interface-language', ['locale' => 'en'])->assertUnprocessable();
        $this->assertSame('ar', $this->admin->fresh()->admin_locale);
    }

    public function test_language_changes_require_an_active_authenticated_administrator(): void
    {
        $this->actingAs(User::factory()->create(['active' => false]));
        $this->post('/admin/interface-language', ['locale' => 'ar'])->assertForbidden();
        auth()->logout();
        $this->post('/admin/interface-language', ['locale' => 'ar'])->assertRedirect('/admin/login');
    }

    public function test_profile_rejects_email_changes_without_current_password_and_duplicate_email(): void
    {
        $other = User::factory()->create();
        Livewire::test(Profile::class)->fillForm(['email' => 'changed@example.invalid'])->call('save')->assertHasFormErrors(['currentPassword']);
        Livewire::test(Profile::class)->fillForm(['email' => 'changed@example.invalid', 'currentPassword' => 'incorrect'])->call('save')->assertHasFormErrors(['currentPassword']);
        Livewire::test(Profile::class)->fillForm(['email' => $other->email, 'currentPassword' => 'Test-password-123!'])->call('save')->assertHasFormErrors(['email']);
        $this->assertSame($this->admin->email, $this->admin->fresh()->email);
    }

    public function test_profile_enforces_strong_confirmed_passwords(): void
    {
        Livewire::test(Profile::class)->fillForm(['password' => 'weak', 'passwordConfirmation' => 'weak', 'currentPassword' => 'Test-password-123!'])->call('save')->assertHasFormErrors(['password']);
        Livewire::test(Profile::class)->fillForm(['password' => 'New-profile-password-42!', 'passwordConfirmation' => 'different', 'currentPassword' => 'Test-password-123!'])->call('save')->assertHasFormErrors(['password']);
        $this->assertTrue(Hash::check('Test-password-123!', $this->admin->fresh()->password));
    }

    public function test_own_profile_updates_hash_password_rotate_token_and_ignore_privilege_fields(): void
    {
        $manager = User::factory()->create(['active' => true, 'role' => 'content_manager', 'remember_token' => 'previous-token']);
        $other = User::factory()->create(['active' => true]);
        $this->actingAs($manager);
        Livewire::test(Profile::class)->set('data.role', 'super_admin')->set('data.active', false)->set('data.user_id', $other->id)
            ->fillForm(['name' => 'Own profile', 'email' => 'own-profile@example.invalid', 'password' => 'New-profile-password-42!', 'passwordConfirmation' => 'New-profile-password-42!', 'currentPassword' => 'Test-password-123!'])
            ->call('save')->assertHasNoFormErrors();
        $fresh = $manager->fresh();
        $this->assertSame('own-profile@example.invalid', $fresh->email);
        $this->assertSame('content_manager', $fresh->role);
        $this->assertTrue($fresh->active);
        $this->assertNotSame('previous-token', $fresh->remember_token);
        $this->assertTrue(Hash::check('New-profile-password-42!', $fresh->password));
        $this->assertFalse(Hash::check('Test-password-123!', $fresh->password));
        $this->assertSame($other->email, $other->fresh()->email);
        auth()->logout();
        Livewire::test(Login::class)->fillForm(['email' => $fresh->email, 'password' => 'New-profile-password-42!'])->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_contact_page_and_forged_setting_changes_reject_content_managers(): void
    {
        $record = app(SiteContentWriter::class)->save(null, ['key' => 'site', 'published' => false, 'translations' => [], 'data' => ['phone' => '+212 600 000 001']], $this->admin);
        $manager = User::factory()->create(['active' => true]);
        $this->actingAs($manager);
        $this->get(ContactSettings::getUrl())->assertForbidden();
        Livewire::test(ContactSettings::class)->assertForbidden();
        try {
            app(SiteContentWriter::class)->save($record, ['key' => 'site', 'published' => false, 'translations' => [], 'data' => ['phone' => '+212 600 000 002']], $manager);
            $this->fail('A content manager changed protected contact settings.');
        } catch (AuthorizationException) {
            $this->assertSame('+212 600 000 001', $record->fresh()->data['phone']);
        }
    }

    public function test_sensitive_profile_change_invalidates_only_own_other_database_sessions(): void
    {
        $other = User::factory()->create();
        foreach (['own-other-session' => $this->admin->id, 'unrelated-session' => $other->id] as $id => $userId) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);
        }
        Livewire::test(Profile::class)->fillForm(['email' => 'session-change@example.invalid', 'currentPassword' => 'Test-password-123!'])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'own-other-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-session', 'user_id' => $other->id]);
    }

    public function test_inactive_accounts_cannot_use_the_temporary_upload_endpoint(): void
    {
        $this->actingAs(User::factory()->create(['active' => false]));
        $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5));
        $this->post($url, ['files' => [UploadedFile::fake()->image('blocked.png')]])->assertForbidden();
    }

    public function test_contact_settings_keep_content_languages_independent_and_additional_numbers_out_of_quotations(): void
    {
        Livewire::test(ContactSettings::class)->fillForm([
            'published' => true,
            'translations' => ['fr' => ['address' => 'Adresse QA', 'footer_text' => 'Texte français'], 'ar' => ['address' => 'عنوان تجريبي', 'footer_text' => 'نص عربي']],
            'data' => ['phone' => '+212 600 000 001', 'email' => 'qa@example.invalid', 'map_url' => 'https://maps.google.com/?q=Morocco',
                'whatsapp' => ['number' => '+212 600 000 001', 'enabled' => true, 'material' => true, 'similar' => true, 'application' => true],
                'additional_contacts' => [['label' => 'Contact QA', 'number' => '+212 600 000 002']],
                'social_links' => [['label' => 'TikTok', 'url' => 'https://www.tiktok.com/@qa']],
            ],
        ])->call('save')->assertHasNoFormErrors();
        $record = SiteContent::findOrFail('site');
        $this->assertSame('Texte français', $record->translations['fr']['footer_text']);
        $this->assertSame('نص عربي', $record->translations['ar']['footer_text']);
        foreach (['fr', 'ar'] as $locale) {
            $this->getJson('/api/v1/site?locale='.$locale)->assertOk()->assertJsonPath('data.settings.whatsapp.number', '212600000001')->assertJsonPath('data.settings.additional_contacts.0.url', 'https://wa.me/212600000002');
        }
        $record->forceFill(['published' => false])->save();
        $this->getJson('/api/v1/site?locale=fr')->assertJsonPath('data.settings', null);
    }

    public function test_unsafe_social_urls_and_invalid_additional_numbers_fail_without_changing_settings(): void
    {
        $writer = app(SiteContentWriter::class);
        $record = $writer->save(null, ['key' => 'site', 'published' => false, 'translations' => [], 'data' => []], $this->admin);
        foreach ([['social_links' => [['label' => 'Unsafe', 'url' => 'javascript:alert(1)']]], ['social_links' => [['label' => 'Unsupported', 'url' => 'https://attacker.invalid/profile']]], ['additional_contacts' => [['label' => 'Invalid', 'number' => 'invalid']]], ['phone' => '+']] as $data) {
            try {
                $writer->save($record, ['key' => 'site', 'published' => false, 'translations' => [], 'data' => $data], $this->admin);
                $this->fail('Unsafe contact data accepted.');
            } catch (ValidationException) {
                $this->assertSame([], $record->fresh()->data);
            }
        }
    }

    public function test_other_editorial_forms_preserve_omitted_contact_settings(): void
    {
        $writer = app(SiteContentWriter::class);
        $contacts = ['phone' => '+12025550123', 'email' => 'qa@example.invalid', 'social_links' => [['label' => 'QA', 'url' => 'https://instagram.com/qa']], 'additional_contacts' => [['label' => 'QA', 'number' => '12025550124'], ['label' => 'QA 2', 'number' => '12025550125']]];
        $record = $writer->save(null, ['key' => 'site', 'published' => false, 'translations' => [], 'data' => $contacts], $this->admin);
        $manager = User::factory()->create(['active' => true]);
        $writer->save($record, ['key' => 'site', 'published' => false, 'translations' => ['fr' => ['footer_text' => 'Editorial update']], 'data' => ['social_links' => $contacts['social_links']]], $manager);
        $this->assertEquals($contacts, $record->fresh()->data);
        $this->assertSame('Editorial update', $record->fresh()->translations['fr']['footer_text']);
        try {
            $writer->save($record->fresh(), ['key' => 'site', 'published' => false, 'translations' => [], 'data' => ['additional_contacts' => array_reverse($contacts['additional_contacts'])]], $manager);
            $this->fail('A manager reordered protected contact settings.');
        } catch (AuthorizationException) {
            $this->assertSame('12025550124', $record->fresh()->data['additional_contacts'][0]['number']);
        }
        $writer->save($record->fresh(), ['key' => 'site', 'published' => false, 'translations' => [], 'data' => ['additional_contacts' => []]], $this->admin);
        $this->assertSame([], $record->fresh()->data['additional_contacts']);
        $this->assertSame($contacts['phone'], $record->fresh()->data['phone']);
    }

    public function test_explicit_profile_generation_and_publication_retain_previous_original_and_variants(): void
    {
        $file = UploadedFile::fake()->image('admin-banner.png', 1200, 800);
        $id = MediaForms::upload(['upload' => $file, 'client_supplied' => true, 'approve_and_publish' => true], 'image', ['thumb', 'card']);
        $old = MediaAsset::findOrFail($id);
        $revision = $old->active_revision;
        $original = $old->original_key;
        MediaForms::upload(['upload' => $file, 'client_supplied' => true, 'approve_and_publish' => true], 'image', ['thumb', 'banner']);
        $fresh = $old->fresh();
        $this->assertNotSame($revision, $fresh->active_revision);
        $this->assertSame($original, $fresh->original_key);
        $this->assertTrue($fresh->variants()->where('revision', $revision)->where('state', 'published')->exists());
        $this->assertTrue($fresh->variants()->where('revision', $fresh->active_revision)->where('profile', 'banner')->where('state', 'published')->exists());
        Storage::disk('local')->assertExists($original);
    }
}
