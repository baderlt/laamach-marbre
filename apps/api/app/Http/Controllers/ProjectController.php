<?php

namespace App\Http\Controllers;

use App\Catalogue\PublicCatalogue;
use App\Projects\PublicProjects;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController
{
    private function locale(Request $request): string
    {
        return $request->validate(['locale' => ['sometimes', Rule::in(array_column(config('localization.locales'), 'code'))]])['locale'] ?? config('localization.default');
    }

    public function categories(Request $request, PublicProjects $projects)
    {
        $locale = $this->locale($request);
        $catalogue = app(PublicCatalogue::class);

        return response()->json(['data' => $projects->categories($locale)->with(['translations', ...$catalogue->imageRelations('cover')])->orderBy('sort_order')->orderBy('id')->get()->map(fn ($category) => ['id' => $category->id, 'slug' => $category->slug, ...$projects->copy($category, $locale), 'cover' => $catalogue->image($category->cover, $locale)])->all()]);
    }

    public function index(Request $request, PublicProjects $projects)
    {
        $locale = $this->locale($request);
        $rules = ['category' => 'sometimes|string|max:120|regex:/^[a-z0-9-]+$/D', 'categories' => 'sometimes|array|min:1|max:8', 'categories.*' => 'required|string|distinct|max:120|regex:/^[a-z0-9-]+$/D', 'page' => 'sometimes|integer|between:1,10000', 'per_page' => 'sometimes|integer|between:1,'.config('projects.max_per_page')];
        $filters = $request->validate($rules);
        $categories = array_unique([...(isset($filters['category']) ? [$filters['category']] : []), ...($filters['categories'] ?? [])]);
        abort_if(count($categories) > 8, 422);
        $query = $projects->projects($locale);
        if ($categories) {
            $query->whereHas('categories', fn ($q) => $q->whereIn('project_categories.id', $projects->categories($locale)->whereIn('slug', $categories)->select('id')));
        }
        $page = $query->paginate($filters['per_page'] ?? config('projects.per_page'));

        return response()->json(['data' => $page->getCollection()->map(fn ($project) => $projects->card($project, $locale))->all(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function show(Request $request, string $slug, PublicProjects $projects)
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,120}$/D', $slug), 404);
        $locale = $this->locale($request);

        return response()->json(['data' => $projects->detail($projects->projects($locale, true)->where('slug', $slug)->firstOrFail(), $locale)]);
    }
}
