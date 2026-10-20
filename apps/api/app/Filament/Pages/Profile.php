<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use SensitiveParameter;

class Profile extends EditProfile
{
    public static function getLabel(): string
    {
        return __('admin.profile');
    }

    protected function getNameFormComponent(): Component
    {
        return parent::getNameFormComponent()->maxLength(150);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()->rule(Password::min(12)->mixedCase()->numbers()->symbols())
            ->helperText(__('admin.password_help'));
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        abort_unless($record->is($this->getUser()), 403);
        $data = Arr::only($data, ['name', 'email', 'password']);
        $sensitive = isset($data['password']) || ($data['email'] ?? $record->email) !== $record->email;
        $record = parent::handleRecordUpdate($record, $data);
        if ($sensitive) {
            $record->forceFill(['remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $record->getKey())
                ->when(request()->hasSession(), fn ($query) => $query->where('id', '!=', request()->session()->getId()))->delete();
            if (request()->hasSession()) {
                request()->session()->regenerate(true);
            }
        }
        $this->data['currentPassword'] = null;

        return $record;
    }
}
