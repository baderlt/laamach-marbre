<?php

namespace Tests\Feature;

use App\Catalogue\CatalogueWriter;
use App\Editorial\SiteContentWriter;
use App\Models\SiteContent;
use App\Models\User;
use App\Projects\ProjectWriter;
use App\Support\SecurityConfiguration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\CatalogueFixtures;
use Tests\Support\ProjectFixtures;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sitemap_and_alternates_follow_actual_publication_and_locale_eligibility(): void
    {
        $actor = User::factory()->create(['active' => true, 'role' => 'super_admin']);
        $family = CatalogueFixtures::catalogue($actor);
        $writer = app(CatalogueWriter::class);
        $data = ['slug' => 'seo-fixture', 'category_id' => $family['child']->id, 'status' => 'published', 'translations' => ['fr' => ['name' => 'SEO fixture']], 'units' => ['m2'], 'sizes' => []];
        $product = $writer->product(null, $data, $actor);
        $category = ProjectFixtures::category($actor, 'seo-usage');
        $project = app(ProjectWriter::class)->save(null, ['slug' => 'seo-project', 'status' => 'published', 'translations' => ProjectFixtures::copy(), 'categories' => [$category->id], 'gallery' => []], $actor);
        $home = SiteContent::find('home');
        app(SiteContentWriter::class)->save($home, ['key' => 'home', 'published' => true, 'data' => [], 'translations' => ['fr' => ['title' => 'Accueil SEO', 'sections' => []]]], $actor);
        $navigation = $this->getJson('/api/v1/site?locale=fr&include_navigation=1')->assertOk()->json('data.navigation');
        $this->assertEquals($this->getJson('/api/v1/product-categories?locale=fr')->json('data'), $navigation['products']);
        $this->assertEquals($this->getJson('/api/v1/project-categories?locale=fr')->json('data'), $navigation['projects']);
        $rows = $this->getJson('/api/v1/seo')->assertOk()->assertDontSee('object_key')->assertDontSee('private/')->json('data');
        $this->assertContains(['path' => 'products/seo-fixture', 'locale' => 'fr'], $rows);
        $this->assertNotContains(['path' => 'products/seo-fixture', 'locale' => 'ar'], $rows);
        $this->assertContains(['path' => 'projects/seo-project', 'locale' => 'ar'], $rows);
        $this->assertContains(['path' => '', 'locale' => 'fr'], $rows);
        $this->assertNotContains(['path' => '', 'locale' => 'ar'], $rows);
        $this->getJson('/api/v1/products/seo-fixture?locale=fr')->assertJsonPath('data.available_locales', ['fr']);
        $this->getJson('/api/v1/products/seo-fixture?locale=ar')->assertNotFound();
        $family['parent']->forceFill(['active' => false])->save();
        $project->forceFill(['status' => 'draft'])->save();
        $rows = $this->getJson('/api/v1/seo')->assertOk()->json('data');
        $this->assertNotContains(['path' => 'products/'.$product->slug, 'locale' => 'fr'], $rows);
        $this->assertNotContains(['path' => 'projects/'.$project->slug, 'locale' => 'fr'], $rows);
    }

    public function test_http_boundaries_headers_cors_and_body_limit(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeaderMissing('Strict-Transport-Security');
        $this->withHeaders(['Origin' => 'http://127.0.0.1:3105'])->getJson('/api/v1/health')->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:3105');
        $this->withHeaders(['Origin' => 'https://untrusted.invalid'])->getJson('/api/v1/health')->assertHeaderMissing('Access-Control-Allow-Origin');
        $this->call('GET', '/api/v1/health', server: ['CONTENT_LENGTH' => (string) (61 * 1024 * 1024), 'HTTP_ACCEPT' => 'application/json'])->assertStatus(413);
        $this->flushHeaders();
        $this->getJson('http://untrusted.invalid/api/v1/health')->assertStatus(400)->assertDontSee('stack');
    }

    public function test_untrusted_forwarded_headers_cannot_change_host_scheme_or_client(): void
    {
        $this->withHeaders(['X-Forwarded-Host' => 'untrusted.invalid', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.99'])->getJson('/api/v1/health')->assertOk();
        $request = Request::create('http://127.0.0.1/api/v1/health', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_HOST' => 'untrusted.invalid', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        $this->assertSame('127.0.0.1', $request->getHost());
        $this->assertFalse($request->isSecure());
        $this->assertSame('127.0.0.1', $request->ip());
    }

    public function test_invalid_origin_and_proxy_configuration_fails_closed(): void
    {
        foreach (['*', 'https://a.invalid/path', 'https://u:p@a.invalid', "https://a.invalid\n"] as $origin) {
            $config = clone config();
            $config->set('security.allowed_origins', [$origin]);
            try {
                SecurityConfiguration::validate($config);
                $this->fail('Unsafe origin accepted.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $config = clone config();
        $config->set('security.trusted_proxies', ['*']);
        $this->expectException(\InvalidArgumentException::class);
        SecurityConfiguration::validate($config);
    }

    public function test_public_api_throttle_is_effective_without_storing_request_bodies(): void
    {
        RateLimiter::for('public-api', fn () => Limit::perMinute(2)->by('m8-test'));
        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/health')->assertStatus(429)->assertHeader('Retry-After');
    }
}
