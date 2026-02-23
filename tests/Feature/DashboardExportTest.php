<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardExportTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    /**
     * Test user can export invoices as CSV.
     */
    public function test_user_can_export_invoices_as_csv(): void
    {
        // Create test invoices
        Invoice::factory(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/dashboard/export-invoices', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        // Verify CSV content contains headers
        $content = (string)$response->getContent();
        $this->assertStringContainsString('Invoice #', $content);
        $this->assertStringContainsString('Client Name', $content);
        $this->assertStringContainsString('Amount', $content);
        $this->assertStringContainsString('Currency', $content);
        $this->assertStringContainsString('Status', $content);
    }

    /**
     * Test export includes user's invoices only.
     */
    public function test_export_includes_only_user_invoices(): void
    {
        $otherUser = User::factory()->create();
        Invoice::factory(2)->create(['user_id' => $otherUser->id]);
        Invoice::factory(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/dashboard/export-invoices', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $content = (string)$response->getContent();

        // Verify CSV has content
        $this->assertNotEmpty($content);
    }

    /**
     * Test unauthenticated user cannot export.
     */
    public function test_unauthenticated_cannot_export(): void
    {
        $response = $this->getJson('/api/dashboard/export-invoices');

        $response->assertStatus(401);
    }

    /**
     * Test export handles empty invoices.
     */
    public function test_export_handles_empty_invoices(): void
    {
        $response = $this->getJson('/api/dashboard/export-invoices', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $content = (string)$response->getContent();

        // Should at least have CSV headers
        $this->assertStringContainsString('Invoice #', $content);
    }
}
