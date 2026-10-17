<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_checks_the_database_without_exposing_configuration(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['application' => 'laamach-marbre', 'version' => 'v1', 'status' => 'ok', 'database' => 'connected'])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_database_outage_returns_a_generic_service_error(): void
    {
        $connection = DB::connection();
        DB::shouldReceive('connection')->andReturn($connection);
        DB::shouldReceive('select')->once()->with('SELECT 1')->andThrow(new \RuntimeException('secret connection details'));
        $this->getJson('/api/v1/health')->assertStatus(503)->assertExactJson(['application' => 'laamach-marbre', 'version' => 'v1', 'status' => 'unavailable']);
    }

    public function test_only_infrastructure_tables_exist(): void
    {
        $tables = array_column(DB::select('SHOW TABLES'), 'Tables_in_laamach_marbre_test');
        sort($tables);
        $expected = ['cache', 'cache_locks', 'media_assets', 'media_imports', 'media_references', 'media_translations', 'media_variants', 'migrations', 'sessions', 'users', 'product_categories', 'product_category_translations', 'products', 'product_translations', 'product_sizes', 'product_size_translations', 'product_units', 'finishes', 'finish_translations', 'product_finishes'];
        sort($expected);
        $expected = [...$expected, 'project_categories', 'project_category_translations', 'projects', 'project_translations', 'project_category_project', 'project_products', 'project_gallery_items', 'site_contents'];
        sort($expected);
        $this->assertSame($expected, $tables);
    }

    public function test_admin_is_private_and_login_is_available_without_registration(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('LAAMACH MARBRE')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertCookieMissing('XSRF-TOKEN');
        $this->get('/admin/register')->assertNotFound();
    }

    public function test_an_active_administrator_can_authenticate_and_open_the_panel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'Test-password-123!', 'remember' => true])->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertStringStartsWith('remember_laamach_marbre_testing_admin_', auth()->guard()->getRecallerName());
        $this->assertContains(auth()->guard()->getRecallerName(), array_map(fn ($cookie) => $cookie->getName(), app('cookie')->getQueuedCookies()));
        $this->get('/admin')->assertOk();
    }

    public function test_inactive_users_and_wrong_passwords_cannot_authenticate(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::factory()->create();
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'Test-password-123!'])->call('authenticate')->assertHasFormErrors();
        $this->assertGuest();
        $user->forceFill(['active' => true])->save();
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'incorrect'])->call('authenticate')->assertHasFormErrors();
        $this->assertGuest();
    }

    public function test_administrator_fields_are_not_mass_assignable_and_policies_are_scoped(): void
    {
        $user = new User(['name' => 'Test', 'email' => 'test@example.invalid', 'password' => 'Test-password-123!', 'role' => 'super_admin', 'active' => true]);
        $this->assertNull($user->role);
        $this->assertNull($user->active);
        $manager = User::factory()->create(['active' => true]);
        $admin = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $this->assertFalse(Gate::forUser($manager)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }

    public function test_storage_is_private_local_and_r2_is_optional_locally(): void
    {
        $this->assertSame('local', config('filesystems.default'));
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertSame([], config('filesystems.links'));
        foreach (['r2_private', 'r2_published'] as $disk) {
            $this->assertSame('s3', config("filesystems.disks.$disk.driver"));
        }
        Storage::disk('local')->put('foundation-check.txt', 'isolated');
        try {
            $this->assertSame('isolated', Storage::disk('local')->get('foundation-check.txt'));
        } finally {
            Storage::disk('local')->delete('foundation-check.txt');
        }
    }

    public function test_configured_locales_and_isolation_are_used(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('laamach_marbre:testing:', config('cache.prefix'));
        $this->assertSame('laamach_marbre_testing_session', config('session.cookie'));
        $this->assertSame(config('localization.default'), app()->getLocale());
        $this->assertSame(['fr', 'ar'], array_column(config('localization.locales'), 'code'));
    }
}
