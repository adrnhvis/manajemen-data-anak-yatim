<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_name' => $this->faker->company(),
            'account_number' => $this->faker->numerify('################'),
            'account_holder_name' => $this->faker->name(),
            'holder_type' => $this->faker->randomElement(['child', 'father', 'mother', 'guardian', 'unknown']),
        ];
    }
}
