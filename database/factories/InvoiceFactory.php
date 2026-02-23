<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = rand(500, 5000);
        $tax_rate = 19.6;
        $tax_amount = round($subtotal * ($tax_rate / 100), 2);
        $total = round($subtotal + $tax_amount, 2);

        return [
            'user_id' => null, // Must be set by caller
            'client_id' => function (array $attributes) {
                // If user_id is set, create a client for that user
                if (!empty($attributes['user_id'])) {
                    return Client::factory()->create(['user_id' => $attributes['user_id']])->id;
                }
                // Otherwise create a generic client
                return Client::factory()->create()->id;
            },
            'invoice_number' => 'INV-' . fake()->unique()->numberBetween(1000, 9999),
            'subtotal' => $subtotal,
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'total' => $total,
            'status' => fake()->randomElement(['draft', 'sent', 'paid', 'unpaid']),
            'issue_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'due_date' => fake()->dateTimeBetween('now', '+60 days')->format('Y-m-d'),
            'paid_date' => null,
            'notes' => fake()->sentence(),
            'watermark' => ['text' => 'UNPAID', 'opacity' => 0.15],
        ];
    }

    /**
     * Mark invoice as paid.
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'paid',
                'paid_date' => fake()->dateTimeBetween($attributes['issue_date'], 'now')->format('Y-m-d'),
                'watermark' => ['text' => 'PAID', 'opacity' => 0.15],
            ];
        });
    }
}
