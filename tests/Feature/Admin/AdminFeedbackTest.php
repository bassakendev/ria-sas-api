<?php

namespace Tests\Feature\Admin;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superadmin = User::factory()->superadmin()->create();
        $this->token = $this->superadmin->createToken('api-token')->plainTextToken;
    }

    /**
     * Test superadmin can list all feedbacks.
     */
    public function test_superadmin_can_list_feedbacks(): void
    {
        Feedback::factory()->count(10)->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson('/api/admin/feedbacks', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'feedbacks' => [
                    '*' => [
                        'id',
                        'type',
                        'status',
                        'email',
                        'subject',
                        'createdAt',
                    ],
                ],
                'page',
                'total',
            ]);
    }

    /**
     * Test feedbacks list can be filtered by type.
     */
    public function test_feedbacks_list_can_be_filtered_by_type(): void
    {
        Feedback::factory()->count(3)->create([
            'user_id' => User::factory()->create()->id,
            'type' => 'bug',
        ]);
        Feedback::factory()->count(2)->create([
            'user_id' => User::factory()->create()->id,
            'type' => 'feature',
        ]);

        $response = $this->getJson('/api/admin/feedbacks?type=bug', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('total'));
    }

    /**
     * Test feedbacks list can be filtered by status.
     */
    public function test_feedbacks_list_can_be_filtered_by_status(): void
    {
        Feedback::factory()->count(3)->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'new',
        ]);
        Feedback::factory()->count(2)->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'read',
        ]);

        $response = $this->getJson('/api/admin/feedbacks?status=new', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('total'));
    }

    /**
     * Test superadmin can show single feedback.
     */
    public function test_superadmin_can_show_feedback(): void
    {
        $feedback = Feedback::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->getJson("/api/admin/feedbacks/{$feedback->id}", [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => 'fb_' . str_pad($feedback->id, 3, '0', STR_PAD_LEFT),
                'subject' => $feedback->subject,
            ]);
    }

    /**
     * Test superadmin can update feedback status.
     */
    public function test_superadmin_can_update_feedback_status(): void
    {
        $feedback = Feedback::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'new',
        ]);

        $response = $this->patchJson("/api/admin/feedbacks/{$feedback->id}/status", [
            'status' => 'read',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'read',
                'message' => 'Feedback status updated successfully',
            ]);

        $this->assertDatabaseHas('feedback', [
            'id' => $feedback->id,
            'status' => 'read',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_FEEDBACK_STATUS',
        ]);
    }

    /**
     * Test status update requires valid status.
     */
    public function test_status_update_requires_valid_status(): void
    {
        $feedback = Feedback::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->patchJson("/api/admin/feedbacks/{$feedback->id}/status", [
            'status' => 'invalid',
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * Test superadmin can update feedback response.
     */
    public function test_superadmin_can_update_feedback_response(): void
    {
        $feedback = Feedback::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'new',
        ]);

        $responseText = 'Thank you for your feedback. We will look into this issue.';

        $response = $this->patchJson("/api/admin/feedbacks/{$feedback->id}/response", [
            'response' => $responseText,
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'response' => $responseText,
                'status' => 'read', // Should auto-update to 'read'
                'respondedBy' => $this->superadmin->email,
                'message' => 'Feedback response updated successfully',
            ]);

        // Verify database
        $feedback->refresh();
        $this->assertEquals($responseText, $feedback->response);
        $this->assertEquals('read', $feedback->status);
        $this->assertEquals($this->superadmin->email, $feedback->responded_by);
        $this->assertNotNull($feedback->responded_at);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_FEEDBACK_RESPONSE',
        ]);
    }

    /**
     * Test response update requires response text.
     */
    public function test_response_update_requires_response_text(): void
    {
        $feedback = Feedback::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->patchJson("/api/admin/feedbacks/{$feedback->id}/response", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['response']);
    }

    /**
     * Test response must not exceed max length.
     */
    public function test_response_must_not_exceed_max_length(): void
    {
        $feedback = Feedback::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $response = $this->patchJson("/api/admin/feedbacks/{$feedback->id}/response", [
            'response' => str_repeat('a', 2001), // Max is 2000
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['response']);
    }

    /**
     * Test superadmin can delete feedback.
     */
    public function test_superadmin_can_delete_feedback(): void
    {
        $feedback = Feedback::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);
        $feedbackId = $feedback->id;

        $response = $this->deleteJson("/api/admin/feedbacks/{$feedback->id}", [], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Feedback deleted successfully',
            ]);

        $this->assertDatabaseMissing('feedback', [
            'id' => $feedbackId,
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DELETE_FEEDBACK',
        ]);
    }

    /**
     * Test regular user cannot access admin feedback endpoints.
     */
    public function test_regular_user_cannot_access_admin_feedback_endpoints(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/admin/feedbacks', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
