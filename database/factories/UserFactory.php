<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Kata sandi bawaan semua user factory: "password".
     */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Mahasiswa,
            'nomor_induk' => fake()->unique()->numerify('09021282######'),
            'angkatan' => 2022,
            'no_hp' => fake()->numerify('08##########'),
            'aktif' => true,
        ];
    }

    public function mahasiswa(): static
    {
        return $this->state(fn () => ['role' => Role::Mahasiswa]);
    }

    public function dosen(): static
    {
        return $this->state(fn () => [
            'role' => Role::Dosen,
            'nomor_induk' => fake()->unique()->numerify('##################'),
            'angkatan' => null,
        ]);
    }

    public function operator(): static
    {
        return $this->state(fn () => [
            'role' => Role::Operator,
            'nomor_induk' => fake()->unique()->numerify('OPR#####'),
            'angkatan' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => Role::Admin,
            'nomor_induk' => fake()->unique()->numerify('ADM#####'),
            'angkatan' => null,
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['aktif' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
