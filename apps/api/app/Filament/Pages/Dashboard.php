<?php

namespace App\Filament\Pages;

use App\Catalogue\CatalogueWriter;
use App\Filament\Resources\CatalogueForms;
use App\Filament\Resources\ProductResource;
use App\Filament\Widgets\CatalogueOverview;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;

class Dashboard extends \Filament\Pages\Dashboard
{
    public static function getNavigationLabel(): string
    {
        return __('admin.dashboard');
    }

    public function getTitle(): string
    {
        return __('admin.dashboard');
    }

    public function getSubheading(): ?string
    {
        return __('admin.welcome');
    }

    public function getWidgets(): array
    {
        return [CatalogueOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createProduct')->label(__('admin.new_product'))->icon('heroicon-o-plus')->visible(fn () => Gate::allows('create', Product::class))->schema(CatalogueForms::fields('product'))->action(fn (array $data, CatalogueWriter $writer) => $writer->product(null, CatalogueForms::clean($data), auth()->user())),
            Action::make('createCategory')->label(__('admin.new_category'))->color('gray')->visible(fn () => Gate::allows('create', ProductCategory::class))->schema(CatalogueForms::fields('category'))->action(fn (array $data, CatalogueWriter $writer) => $writer->category(null, CatalogueForms::clean($data), auth()->user())),
            Action::make('catalogue')->label(__('admin.manage_products'))->color('gray')->url(ProductResource::getUrl()),
        ];
    }
}
