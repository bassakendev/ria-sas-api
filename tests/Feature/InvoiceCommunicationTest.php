<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $client;
    private $invoice;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'email' => 'client@example.com',
            'phone' => '+33612345678',
        ]);

        $this->invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'invoice_number' => 'INV-2026-001',
        ]);
    }

    /**
     * Test user can send invoice by email.
     */
    public function test_user_can_send_invoice_by_email(): void
    {
        $response = $this->postJson("/api/invoices/{$this->invoice->id}/send-email", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test user cannot send invoice by email if it doesn't exist.
     */
    public function test_user_cannot_send_nonexistent_invoice_by_email(): void
    {
        $response = $this->postJson('/api/invoices/9999/send-email', [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(404);
    }

    /**
     * Test user can send invoice by whatsapp.
     */
    public function test_user_can_send_invoice_by_whatsapp(): void
    {
        $response = $this->postJson("/api/invoices/{$this->invoice->id}/send-whatsapp", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test user cannot send invoice by whatsapp if it doesn't exist.
     */
    public function test_user_cannot_send_nonexistent_invoice_by_whatsapp(): void
    {
        $response = $this->postJson('/api/invoices/9999/send-whatsapp', [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(404);
    }

    /**
     * Test unauthenticated user cannot send invoice.
     */
    public function test_unauthenticated_cannot_send_invoice(): void
    {
        $response = $this->postJson("/api/invoices/{$this->invoice->id}/send-email");
        $response->assertStatus(401);

        $response = $this->postJson("/api/invoices/{$this->invoice->id}/send-whatsapp");
        $response->assertStatus(401);
    }

    /**
     * Test user cannot send another user's invoice.
     */
    public function test_user_cannot_send_others_invoice(): void
    {
        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('test')->plainTextToken;

        $response = $this->postJson("/api/invoices/{$this->invoice->id}/send-email", [], [
            'Authorization' => "Bearer {$otherToken}",
        ]);

        $response->assertStatus(403);
    }
}
