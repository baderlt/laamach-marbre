<?php

namespace App\Http\Controllers;

use App\Catalogue\PublicCatalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return response()->json(['data' => $catalogue->categoryTree($locale), 'finishes' => $catalogue->finishes($locale)->with('translations')->orderBy('sort_order')->orderBy('id')->get()->map(fn ($finish) => ['id' => $finish->id, 'slug' => $finish->slug, ...$catalogue->copy($finish, $locale)])->all()]);
    }

    public function index(Request $request, PublicCatalogue $catalogue)
    {
        $locale = $this->locale($request);
        $filters = $request->validate(['parent' => 'sometimes|string|max:120|regex:/^[a-z0-9-]+$/D', 'category' => 'sometimes|string|max:120|regex:/^[a-z0-9-]+$/D', 'finish' => 'sometimes|string|max:120|regex:/^[a-z0-9-]+$/D', 'q' => 'sometimes|string|max:120', 'sort' => ['sometimes', Rule::in(['name'])], 'page' => 'sometimes|integer|between:1,10000', 'per_page' => 'sometimes|integer|between:1,'.config('catalogue.max_per_page')]);
        $query = $catalogue->products($locale);
        if (isset($filters['q'])) {
            $query->whereHas('translations', fn ($copy) => $copy->where('locale', $locale)->where('name', 'like', '%'.addcslashes($filters['q'], '%_\\').'%'));
        }
        if (($filters['sort'] ?? null) === 'name') {
            $query->reorder()->orderBy(DB::table('product_translations')->select('name')->whereColumn('product_id', 'products.id')->where('locale', $locale)->limit(1))->orderBy('products.id');
        }
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

    public function groups(Request $request, PublicCatalogue $catalogue)
    {
        $locale = $this->locale($request);
        $input = $request->validate(['page' => 'sometimes|integer|between:1,10000']);

        return response()->json($catalogue->groupedProducts($locale, (int) ($input['page'] ?? 1)));
    }

    public function show(Request $request, string $slug, PublicCatalogue $catalogue)
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,120}$/D', $slug), 404);
        $locale = $this->locale($request);

        $product = $catalogue->products($locale, true)->where('slug', $slug)->firstOrFail();
        $locales = array_values(array_filter(array_column(config('localization.locales'), 'code'), fn ($code) => $code === $locale || $catalogue->products($code)->where('id', $product->id)->exists()));

        return response()->json(['data' => [...$catalogue->productDto($product, $locale, true), 'available_locales' => $locales]]);
    }
}
