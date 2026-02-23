<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can create client.
     */
    public function test_user_can_create_client(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/clients', [
            'name' => 'Acme Corporation',
            'email' => 'billing@acme.com',
            'phone' => '+33123456789',
            'address' => '123 Business Street',
            'city' => 'Paris',
            'postal_code' => '75001',
            'country' => 'France',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Acme Corporation')
            ->assertJsonPath('city', 'Paris');

        $this->assertDatabaseHas('clients', [
            'user_id' => $user->id,
            'name' => 'Acme Corporation',
        ]);
    }

    /**
     * Test user can list their clients.
     */
    public function test_user_can_list_their_clients(): void
    {
        $user = User::factory()->create();
        Client::factory()->count(5)->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/clients', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(5);
    }

    /**
     * Test user sees only their clients.
     */
    public function test_user_sees_only_their_clients(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Client::factory()->count(3)->create(['user_id' => $user->id]);
        Client::factory()->count(5)->create(['user_id' => $otherUser->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/clients', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    /**
     * Test user can view client.
     */
    public function test_user_can_view_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            'name' => 'Test Client',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson("/api/clients/{$client->id}", [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Test Client');
    }

    /**
     * Test user cannot view others client.
     */
    public function test_user_cannot_view_others_client(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $otherUser->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson("/api/clients/{$client->id}", [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test user can update client.
     */
    public function test_user_can_update_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->putJson("/api/clients/{$client->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+33987654321',
            'address' => '456 New Street',
            'city' => 'Lyon',
            'postal_code' => '69000',
            'country' => 'France',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('city', 'Lyon');
    }

    /**
     * Test user can delete client.
     */
    public function test_user_can_delete_client(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->deleteJson("/api/clients/{$client->id}", [], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    /**
     * Test client name is required.
     */
    public function test_client_name_is_required(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->postJson('/api/clients', [
            'email' => 'test@example.com',
            // name is missing
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test client address fields are stored correctly.
     */
    public function test_client_address_fields_stored_correctly(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->postJson('/api/clients', [
            'name' => 'Address Test Client',
            'address' => '789 Location Ave',
            'city' => 'Marseille',
            'postal_code' => '13000',
            'country' => 'France',
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $this->assertDatabaseHas('clients', [
            'name' => 'Address Test Client',
            'address' => '789 Location Ave',
            'city' => 'Marseille',
            'postal_code' => '13000',
            'country' => 'France',
        ]);
    }
}
