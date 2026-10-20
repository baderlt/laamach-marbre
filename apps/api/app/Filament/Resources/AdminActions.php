<?php

namespace App\Filament\Resources;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class AdminActions
{
    public static function save(Closure $operation): mixed
    {
        try {
            $record = $operation();
            Notification::make()->success()->title(__('admin.saved'))->send();

            return $record;
        } catch (ValidationException $error) {
            // Domain writers validate independently of Filament's nested action path.
            // Keep their rejection and show it even when no matching field is visible.
            $message = collect($error->errors())->flatten()->map(fn ($text) => __($text))->unique()->join(' ');
            Notification::make()->danger()->persistent()->title(__('admin.save_failed'))->body(e(mb_substr($message, 0, 1500)))->send();
            throw $error;
        }
    }
}
