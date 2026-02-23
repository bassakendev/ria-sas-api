<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvoicesSeeder extends Seeder
{
    private array $descriptions = [
        'Design & UX Audit',
        'Web Development',
        'Mobile App Development',
        'UI/UX Design',
        'Project Management',
        'System Architecture',
        'Database Design',
        'API Development',
        'Frontend Development',
        'Backend Development',
        'Quality Assurance',
        'DevOps & Infrastructure',
        'Maintenance & Support',
        'Consulting Services',
        'Training & Documentation',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereNotIn('role', ['superadmin', 'admin'])->get();

        foreach ($users as $user) {
            $clients = Client::where('user_id', $user->id)->get();

            if ($clients->isEmpty()) {
                continue;
            }

            // Create 5-15 invoices per user (more for pro)
            $invoiceCount = $user->subscription_plan === 'pro' ? rand(10, 20) : rand(3, 10);

            for ($i = 0; $i < $invoiceCount; $i++) {
                $client = $clients->random();
                $status = $this->randomStatus();

                // Generate invoice items
                $items = [];
                $itemCount = rand(1, 5);
                for ($j = 0; $j < $itemCount; $j++) {
                    $items[] = [
                        'description' => $this->descriptions[array_rand($this->descriptions)],
                        'quantity' => rand(1, 10),
                        'unit_price' => rand(50, 500),
                    ];
                }

                // Calculate totals
                $subtotal = collect($items)
                    ->sum(fn($item) => $item['quantity'] * $item['unit_price']);

                $tax_rate = 19.6;
                $tax_amount = round($subtotal * ($tax_rate / 100), 2);
                $total = round($subtotal + $tax_amount, 2);

                // Create invoice
                $issueDate = fake()->dateTimeBetween('-180 days', 'now');
                $invoice = Invoice::create([
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                    'invoice_number' => 'INV-' . str_pad($user->id, 3, '0', STR_PAD_LEFT) . '-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                    'subtotal' => $subtotal,
                    'tax_rate' => $tax_rate,
                    'tax_amount' => $tax_amount,
                    'total' => $total,
                    'status' => $status,
                    'issue_date' => $issueDate->format('Y-m-d'),
                    'due_date' => (clone $issueDate)->modify('+30 days')->format('Y-m-d'),
                    'paid_date' => $status === 'paid' ? (clone $issueDate)->modify('+' . rand(1, 30) . ' days')->format('Y-m-d') : null,
                    'notes' => fake()->boolean(30) ? fake()->sentence() : null,
                    'watermark' => [
                        'text' => $status === 'paid' ? 'PAID' : 'UNPAID',
                        'opacity' => 0.15,
                    ],
                ]);

                // Create invoice items
                foreach ($items as $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                    ]);
                }
            }
        }
    }

    private function randomStatus(): string
    {
        $statuses = ['draft', 'sent', 'paid', 'unpaid'];
        $weights = [10, 30, 40, 20]; // draft=10%, sent=30%, paid=40%, unpaid=20%

        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($statuses as $index => $status) {
            $cumulative += $weights[$index];
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return 'paid';
    }
}
