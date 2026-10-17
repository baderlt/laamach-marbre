<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionAdmin extends Command
{
    protected $signature = 'laamach:admin {email} {--name=}';

    protected $description = 'Create an active administrator using a hidden password prompt';

    public function handle(): int
    {
        $password = $this->secret('New password (12+ characters, mixed case and numbers)');
        $data = ['email' => $this->argument('email'), 'name' => $this->option('name') ?: $this->ask('Name'), 'password' => $password, 'password_confirmation' => $this->secret('Confirm password')];
        $validator = Validator::make($data, ['email' => 'required|email|max:254|unique:users,email', 'name' => 'required|string|max:150', 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User(collect($data)->only(['name', 'email', 'password'])->all());
        $user->forceFill(['role' => 'super_admin', 'active' => true])->save();
        $this->info('Administrator created. No password is printed or stored in documentation.');

        return self::SUCCESS;
    }
}
