<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLanguageController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->canAccessPanel(Filament::getPanel('admin')), 403);
        $data = $request->validate(['locale' => ['required', Rule::in(['fr', 'ar'])]]);
        // The account identifier and privilege fields never come from the request.
        $user->forceFill(['admin_locale' => $data['locale']])->save();

        return redirect('/admin')->withCookie(cookie('laamach_admin_locale', $data['locale'], 525600, '/admin', secure: app()->environment('production'), httpOnly: true, sameSite: 'lax'));
    }
}
