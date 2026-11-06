<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class BootstrapSystemAdmin extends Command
{
    protected $signature = 'laos:bootstrap-admin';

    protected $description = 'Create the first LAOS System Administrator interactively';

    public function handle(): int
    {
        if (User::whereHas('roles', fn ($query) => $query->where('name', 'system_admin'))->exists()) {
            $this->error('Admin Sistem sudah ada. Perintah bootstrap hanya dapat digunakan sekali.');

            return self::FAILURE;
        }

        $role = Role::where('name', 'system_admin')->first();
        if (! $role) {
            $this->error('Role belum tersedia. Jalankan php artisan db:seed terlebih dahulu.');

            return self::FAILURE;
        }

        $name = $this->ask('Nama Admin Sistem');
        $email = mb_strtolower((string) $this->ask('Email'));
        $password = $this->secret('Kata sandi (minimal 12 karakter)');
        $passwordConfirmation = $this->secret('Ulangi kata sandi');
        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $passwordConfirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12', 'confirmed'],
            ],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id);

        $this->info('Admin Sistem berhasil dibuat.');

        return self::SUCCESS;
    }
}
