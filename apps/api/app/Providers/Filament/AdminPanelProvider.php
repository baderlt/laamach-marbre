<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ContactSettings;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Profile;
use App\Filament\Resources\FinishResource;
use App\Filament\Resources\MediaAssetResource;
use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProjectCategoryResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\SiteContentResource;
use App\Filament\Widgets\CatalogueOverview;
use App\Filament\Widgets\RecentContent;
use App\Http\Middleware\AdminLocale;
use App\Http\Middleware\PreventRequestForgery;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
            ->profile(Profile::class, isSimple: false)
            ->authGuard(config('auth.defaults.guard'))
            ->brandName('LAAMACH MARBRE')
            ->font('Inter', provider: LocalFontProvider::class)
            ->colors(['primary' => array_replace(Color::hex('#1f5545'), [500 => '#1f5545', 600 => '#194a3d', 700 => '#143d33'])])
            ->sidebarCollapsibleOnDesktop()
            ->renderHook('panels::head.end', fn () => view('admin.theme'))
            ->renderHook('panels::topbar.end', fn () => view('admin.language-switch'))
            ->navigationItems([NavigationItem::make(fn () => __('admin.profile'))->group(fn () => __('admin.group_settings'))->icon('heroicon-o-user-circle')->url(fn () => Profile::getUrl())->isActiveWhen(fn () => request()->routeIs('filament.admin.auth.profile'))->sort(100)])
            ->navigationGroups(array_map(fn ($key) => NavigationGroup::make()->label(fn () => __('admin.group_'.$key)), ['website', 'catalogue', 'projects', 'media', 'settings']))
            ->databaseTransactions()
            ->resources([MediaAssetResource::class, ProductCategoryResource::class, ProductResource::class, FinishResource::class, ProjectCategoryResource::class, ProjectResource::class, SiteContentResource::class])
            ->pages([Dashboard::class, ContactSettings::class])->widgets([CatalogueOverview::class, RecentContent::class])
            ->middleware([
                EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
                AuthenticateSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class,
                SubstituteBindings::class, DisableBladeIconComponents::class, DispatchServingFilamentEvent::class,
            ])->middleware([AdminLocale::class], isPersistent: true)->authMiddleware([Authenticate::class]);
    }
}
