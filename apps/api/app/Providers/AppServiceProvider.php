<?php

namespace App\Providers;

use App\Media\MediaConfiguration;
use App\Models\Finish;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Policies\CataloguePolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\UserPolicy;
use App\Support\FoundationConfiguration;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A first Composer install/key:generate must be possible before a key exists.
        FoundationConfiguration::validate($this->app['config'], requireKey: ! $this->app->runningInConsole());
        MediaConfiguration::validate($this->app['config']);
        $this->app->setLocale(config('localization.default'));
        $this->app->setFallbackLocale(config('localization.fallback'));
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);
        foreach ([ProductCategory::class, Product::class, Finish::class] as $model) {
            Gate::policy($model, CataloguePolicy::class);
        }
    }
}
