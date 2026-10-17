<?php

namespace App\Http\Controllers;

use App\Catalogue\PublicCatalogue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogueController
{
    private function locale(Request $request): string
    {
        return $request->validate(['locale' => ['sometimes', 'string', Rule::in(array_column(config('localization.locales'), 'code'))]])['locale'] ?? config('localization.default');
    }

    public function categories(Request $request, PublicCatalogue $catalogue)
    {
        $locale = $this->locale($request);
        $parents = $catalogue->categories($locale)->whereNull('parent_id')->with(['translations', ...$catalogue->imageRelations('cover'), 'children' => fn ($children) => $children->whereIn('id', $catalogue->categories($locale)->select('id'))->orderBy('sort_order')->orderBy('id'), 'children.translations', ...$catalogue->imageRelations('children.cover')])->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => $parents->map(fn ($parent) => [...$catalogue->categoryDto($parent, $locale, true), 'children' => $parent->children->map(fn ($child) => $catalogue->categoryDto($child, $locale, true))->all()])->all(), 'finishes' => $catalogue->finishes($locale)->with('translations')->orderBy('sort_order')->orderBy('id')->get()->map(fn ($finish) => ['id' => $finish->id, 'slug' => $finish->slug, ...$catalogue->copy($finish, $locale)])->all()]);
    }

    public function index(Request $request, PublicCatalogue $catalogue)
    {
        $locale = $this->locale($request);
        $filters = $request->validate(['parent' => 'sometimes|string|max:120|regex:/^[a-z0-9-]+$/D', 'category' => 'sometimes|string|max:120|regex:/^[a-z0-9-]+$/D', 'finish' => 'sometimes|string|max:120|regex:/^[a-z0-9-]+$/D', 'page' => 'sometimes|integer|between:1,10000', 'per_page' => 'sometimes|integer|between:1,'.config('catalogue.max_per_page')]);
        $query = $catalogue->products($locale);
        if (isset($filters['parent'])) {
            $query->whereHas('category.parent', fn ($q) => $q->where('slug', $filters['parent']));
        }
        if (isset($filters['category'])) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }
        if (isset($filters['finish'])) {
            $query->whereHas('finishes', fn ($q) => $q->where('slug', $filters['finish'])->where('product_finishes.active', true)->whereIn('finishes.id', $catalogue->finishes($locale)->select('id')));
        }
        $page = $query->paginate($filters['per_page'] ?? config('catalogue.per_page'));

        return response()->json(['data' => $page->getCollection()->map(fn ($product) => $catalogue->productDto($product, $locale))->all(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function show(Request $request, string $slug, PublicCatalogue $catalogue)
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,120}$/D', $slug), 404);
        $locale = $this->locale($request);

        return response()->json(['data' => $catalogue->productDto($catalogue->products($locale, true)->where('slug', $slug)->firstOrFail(), $locale, true)]);
    }
}
