<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '09' . fake()->unique()->numerify('########'),
            'photo_url' => 'storage/photos/random.jpg',
            'id_photo' => 'storage/photos/random.jpg',
            'birth_date' => fake()->date('Y-m-d', '2005-01-01'),
            'verified_status' => 'pending',
            'role' => fake()->randomElement(['tenant', 'landlord']),
            'password' => bcrypt('password'),
            'balance' => rand(10000,1000000),
        ];
    }
}
