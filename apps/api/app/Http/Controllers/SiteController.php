<?php

namespace App\Http\Controllers;

use App\Editorial\PublicSite;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteController
{
    public function __invoke(Request $request, PublicSite $site)
    {
        $locale = $request->validate(['locale' => ['sometimes', 'string', Rule::in(array_column(config('localization.locales'), 'code'))]])['locale'] ?? config('localization.default');

        $navigation = $request->validate(['include_navigation' => ['sometimes', 'boolean']])['include_navigation'] ?? false;

        return response()->json(['data' => $site->data($locale, (bool) $navigation)]);
    }
}
