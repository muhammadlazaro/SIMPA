<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::withTrashed()
            ->whereIn('email', [
                'backend.developer@example.com',
                'frontend.developer@example.com',
            ])
            ->get()
            ->each(function (User $user): void {
                $user->tokens()->delete();
                $user->forceDelete();
            });

        // Key by email so updates stay deterministic even if legacy data had duplicate roles.
        User::updateOrCreate(
            ['email' => 'admin.sistem@example.com'],
            [
                'name' => 'Admin Sistem',
                'password' => Hash::make('password123'),
                'role' => 'admin_sistem',
            ]
        );

        User::updateOrCreate(
            ['email' => 'pengelola.aplikasi@example.com'],
            [
                'name' => 'Pengelola Aplikasi',
                'password' => Hash::make('password123'),
                'role' => 'pengelola_aplikasi',
            ]
        );

        User::updateOrCreate(
            ['email' => 'analis.desain@example.com'],
            [
                'name' => 'Analis Desain',
                'password' => Hash::make('password123'),
                'role' => 'analis_desain',
            ]
        );

        User::updateOrCreate(
            ['email' => 'tim.implementasi.aplikasi@example.com'],
            [
                'name' => 'Tim Implementasi Aplikasi',
                'password' => Hash::make('password123'),
                'role' => 'tim_implementasi_aplikasi',
            ]
        );

        User::updateOrCreate(
            ['email' => 'unit.kerja@example.com'],
            [
                'name' => 'Unit Kerja',
                'password' => Hash::make('password123'),
                'role' => 'unit_kerja',
            ]
        );

        User::updateOrCreate(
            ['email' => 'devops.developer@example.com'],
            [
                'name' => 'DevOps Developer',
                'password' => Hash::make('password123'),
                'role' => 'devops_developer',
            ]
        );

        User::updateOrCreate(
            ['email' => 'tim.uji.keamanan@example.com'],
            [
                'name' => 'Tim Uji Keamanan',
                'password' => Hash::make('password123'),
                'role' => 'tim_uji_keamanan',
            ]
        );
    }
}
