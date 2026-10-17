<?php

namespace App\Providers\Filament;

use App\Filament\Resources\FinishResource;
use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Http\Middleware\PreventRequestForgery;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')->path('admin')->login()
            ->authGuard(config('auth.defaults.guard'))
            ->brandName('LAAMACH MARBRE')
            ->font('Inter', provider: LocalFontProvider::class)
            ->databaseTransactions()
            ->resources([MediaAssetResource::class, ProductCategoryResource::class, ProductResource::class, FinishResource::class])
            ->pages([Dashboard::class])->widgets([AccountWidget::class])
            ->middleware([
                EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
                AuthenticateSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
                SubstituteBindings::class, DisableBladeIconComponents::class, DispatchServingFilamentEvent::class,
            ])->authMiddleware([Authenticate::class]);
    }
}
