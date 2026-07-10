<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Aplikasi>
 */
class AplikasiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $namaLayanan = fake()->words(3, true);
        $namaSingkat = strtoupper(substr($namaLayanan, 0, 3));
        
        return [
            'nama_layanan' => $namaLayanan,
            'nama_singkat' => $namaSingkat,
            'nama_aplikasi' => 'App ' . fake()->word(),
            'jenis_layanan_aplikasi' => fake()->randomElement(['publik', 'internal', 'pendukung']),
            'kode_unitOrganisasi' => fake()->bothify('ORG###'),
            'tipe_akuisisi' => fake()->randomElement(['Custom-Made', 'Off-The-Shelf']),
            'status' => fake()->randomElement(['diajukan', 'terverifikasi', 'pengembangan', 'deployed_staging', 'tidak_layak']),
        ];
    }

    /**
     * Indicate that the aplikasi is public.
     */
    public function publik(): static
    {
        return $this->state(fn (array $attributes) => [
            'jenis_layanan_aplikasi' => 'publik',
        ]);
    }

    /**
     * Indicate that the aplikasi is internal.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'jenis_layanan_aplikasi' => 'internal',
        ]);
    }

    /**
     * Indicate that the aplikasi is in production.
     */
    public function production(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'deployed_production',
        ]);
    }
}
