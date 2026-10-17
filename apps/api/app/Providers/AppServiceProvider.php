<?php

namespace App\Providers;

use App\Models\User;
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
        $this->app->setLocale(config('localization.default'));
        $this->app->setFallbackLocale(config('localization.fallback'));
        Gate::policy(User::class, UserPolicy::class);
    }
}
