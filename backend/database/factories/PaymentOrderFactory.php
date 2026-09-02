<?php

namespace Database\Factories;

use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentOrder>
 */
class PaymentOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'invoice_number' => 'VIVE-'.$this->faker->unique()->numerify('############'),
            'type' => 'APP_SLOT',
            'duration_months' => 1,
            'quantity' => 1,
            'amount_vnd' => 49000,
            'status' => 'PENDING',
            'provider' => 'SEPAY',
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
