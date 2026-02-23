<?php

namespace Database\Factories;

use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            'Design Services',
            'Web Development',
            'API Integration',
            'Database Design',
            'DevOps Setup',
            'QA Testing',
            'UI/UX Consultation',
            'Cloud Migration',
            'Security Audit',
            'Performance Optimization',
            'Documentation',
            'Technical Support',
            'Code Review',
            'Training',
            'Maintenance & Support',
        ];

        return [
            'invoice_id' => null, // Must be set by caller
            'description' => fake()->randomElement($services),
            'quantity' => rand(1, 5),
            'unit_price' => round(rand(100, 1000), 2),
        ];
    }
}
