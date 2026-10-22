<?php

namespace App\Providers;

use App\Media\MediaConfiguration;
use App\Models\Finish;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\SiteContent;
use App\Models\User;
use App\Policies\CataloguePolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\SiteContentPolicy;
use App\Policies\UserPolicy;
use App\Support\FoundationConfiguration;
use App\Support\SecurityConfiguration;
use App\Support\TestDatabaseGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A first Composer install/key:generate must be possible before a key exists.
        FoundationConfiguration::validate($this->app['config'], requireKey: ! $this->app->runningInConsole());
        if ($this->app->runningInConsole() && $this->app->environment('testing')) {
            Event::listen(CommandStarting::class, static function (CommandStarting $event): void {
                if (in_array($event->command, ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe', 'db:seed', 'schema:load'], true)) {
                    TestDatabaseGuard::assertSafe(app()->environment(), config('database'));
                }
            });
        }
        MediaConfiguration::validate($this->app['config']);
        SecurityConfiguration::validate($this->app['config']);
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(config('security.public_requests_per_minute'))->by($request->ip()));
        $this->app->setLocale(config('localization.default'));
        $this->app->setFallbackLocale(config('localization.fallback'));
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);
        Gate::policy(SiteContent::class, SiteContentPolicy::class);
        foreach ([ProductCategory::class, Product::class, Finish::class, Project::class, ProjectCategory::class] as $model) {
            Gate::policy($model, CataloguePolicy::class);
        }
    }
}
