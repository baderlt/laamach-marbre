<?php

namespace App\Filament\Resources;

trait AdminResourceLabels
{
    private static function adminKey(): string
    {
        return match (class_basename(static::getModel())) {
            'Product' => 'products', 'ProductCategory' => 'categories', 'Finish' => 'finishes',
            'Project' => 'projects', 'ProjectCategory' => 'project_categories', 'MediaAsset' => 'media', default => 'pages',
        };
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.'.static::adminKey());
    }

    public static function getPluralModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public static function getModelLabel(): string
    {
        return __('admin.singular_'.static::adminKey());
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.group_'.match (static::adminKey()) {
            'products', 'categories', 'finishes' => 'catalogue', 'projects', 'project_categories' => 'projects', 'media' => 'media', default => 'website',
        });
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return match (static::adminKey()) {
            'products' => 'heroicon-o-cube', 'categories', 'project_categories' => 'heroicon-o-squares-2x2',
            'finishes' => 'heroicon-o-swatch', 'projects' => 'heroicon-o-building-office', 'media' => 'heroicon-o-photo', default => 'heroicon-o-document-text',
        };
    }

    public static function getNavigationSort(): ?int
    {
        return array_search(static::adminKey(), ['pages', 'products', 'categories', 'finishes', 'projects', 'project_categories', 'media'], true) + 1;
    }
}
