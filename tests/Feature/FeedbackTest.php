<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can submit feedback.
     */
    public function test_user_can_submit_feedback(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/feedback', [
            'type' => 'bug',
            'email' => 'user@example.com',
            'subject' => 'Invoice calculation error',
            'message' => 'The tax calculation is incorrect on some invoices. It appears to be doubled.',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'feedback' => [
                    'id',
                    'user_id',
                    'type',
                    'subject',
                    'message',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('feedback', [
            'user_id' => $user->id,
            'type' => 'bug',
            'subject' => 'Invoice calculation error',
        ]);
    }

    /**
     * Test feedback validation rules.
     */
    public function test_feedback_validation_fails_with_invalid_data(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/feedback', [
            'type' => 'invalid_type',
            'email' => 'not-an-email',
            'subject' => '',
            'message' => 'short',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'email', 'subject', 'message']);
    }

    /**
     * Test user can list their feedback.
     */
    public function test_user_can_list_their_feedback(): void
    {
        $user = User::factory()->create();
        Feedback::factory()->count(3)->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/feedback', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    /**
     * Test user can view their feedback.
     */
    public function test_user_can_view_their_feedback(): void
    {
        $user = User::factory()->create();
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Feature request',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson("/api/feedback/{$feedback->id}", [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('subject', 'Feature request');
    }

    /**
     * Test user cannot view others feedback.
     */
    public function test_user_cannot_view_others_feedback(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $otherUser->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson("/api/feedback/{$feedback->id}", [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test mark feedback as read.
     */
    public function test_user_can_mark_feedback_as_read(): void
    {
        $user = User::factory()->create();
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => 'new',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->patchJson("/api/feedback/{$feedback->id}/mark-read", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'read');
    }

    /**
     * Test close feedback.
     */
    public function test_user_can_close_feedback(): void
    {
        $user = User::factory()->create();
        $feedback = Feedback::factory()->create([
            'user_id' => $user->id,
            'status' => 'read',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->patchJson("/api/feedback/{$feedback->id}/close", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'closed');
    }

    /**
     * Test delete feedback.
     */
    public function test_user_can_delete_feedback(): void
    {
        $user = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->deleteJson("/api/feedback/{$feedback->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('feedback', ['id' => $feedback->id]);
    }

    /**
     * Test all feedback types are valid.
     */
    public function test_all_feedback_types_are_accepted(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;
        $types = ['question', 'bug', 'feature', 'other'];

        foreach ($types as $type) {
            $response = $this->postJson('/api/feedback', [
                'type' => $type,
                'email' => 'user@example.com',
                'subject' => "Test $type",
                'message' => 'This is a test message for feedback.',
            ], [
                'Authorization' => "Bearer $token",
            ]);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('feedback', 4);
    }
}
