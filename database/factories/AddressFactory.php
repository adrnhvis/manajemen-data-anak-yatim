<?php

namespace Database\Factories;

use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rt' => '001',
            'rw' => '002',
            'address_detail' => $this->faker->streetAddress(),
            'living_with' => $this->faker->randomElement(['parents', 'alone', 'relative', 'other']),
        ];
    }
}
