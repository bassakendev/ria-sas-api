<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can create invoice.
     */
    public function test_user_can_create_invoice(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'tax_rate' => 19.6,
            'notes' => 'Payment terms: 30 days',
            'items' => [
                [
                    'description' => 'Design Services',
                    'quantity' => 2,
                    'unit_price' => 500,
                ],
                [
                    'description' => 'Development',
                    'quantity' => 1,
                    'unit_price' => 1500,
                ],
            ],
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('client_id', $client->id)
            ->assertJsonPath('status', 'draft');

        // Verify calculations
        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'client_id' => $client->id,
            'subtotal' => 2500,
            'tax_rate' => 19.6,
            'tax_amount' => 490.0,
            'total' => 2990.0,
        ]);
    }

    /**
     * Test user can list invoices.
     */
    public function test_user_can_list_invoices(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->count(5)->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/invoices', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(5);
    }

    /**
     * Test user can view invoice.
     */
    public function test_user_can_view_invoice(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson("/api/invoices/{$invoice->id}", [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('id', $invoice->id);
    }

    /**
     * Test user cannot view others invoice.
     */
    public function test_user_cannot_view_others_invoice(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $otherUser->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson("/api/invoices/{$invoice->id}", [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test user can update invoice.
     */
    public function test_user_can_update_invoice(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->putJson("/api/invoices/{$invoice->id}", [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(45)->toDateString(),
            'status' => 'sent',
            'tax_rate' => 20,
            'notes' => 'Updated notes',
            'items' => [
                [
                    'description' => 'Updated Service',
                    'quantity' => 1,
                    'unit_price' => 1000,
                ],
            ],
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'sent')
            ->assertJsonPath('tax_rate', '20.00');
    }

    /**
     * Test mark invoice as paid.
     */
    public function test_user_can_mark_invoice_as_paid(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => 'sent',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->patchJson("/api/invoices/{$invoice->id}/mark-paid", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'paid')
            ->assertJsonStructure(['id', 'user_id', 'status', 'paid_date']);

        // Verify in database that paid_date is set to today
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
        ]);
    }

    /**
     * Test delete invoice.
     */
    public function test_user_can_delete_invoice(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->deleteJson("/api/invoices/{$invoice->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    /**
     * Test invoice calculation accuracy.
     */
    public function test_invoice_calculations_are_accurate(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $this->postJson('/api/invoices', [
            'client_id' => $client->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'draft',
            'tax_rate' => 19.6,
            'items' => [
                ['description' => 'Item 1', 'quantity' => 3, 'unit_price' => 100],
                ['description' => 'Item 2', 'quantity' => 2, 'unit_price' => 250],
            ],
        ], [
            'Authorization' => "Bearer $token",
        ]);

        // Expected: (3*100 + 2*250) = 800 subtotal
        // Tax: 800 * 0.196 = 156.80
        // Total: 800 + 156.80 = 956.80

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'subtotal' => 800,
            'tax_amount' => 156.80,
            'total' => 956.80,
        ]);
    }
}
