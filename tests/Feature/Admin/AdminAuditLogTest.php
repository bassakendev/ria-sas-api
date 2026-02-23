<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
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
     * Test superadmin can list audit logs.
     */
    public function test_superadmin_can_list_audit_logs(): void
    {
        // Create some audit logs
        $actor = User::factory()->admin()->create();

        AuditLog::create([
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'action' => 'SUSPEND_USER',
            'target' => 'usr_001',
            'ip_address' => '192.168.1.1',
            'metadata' => ['reason' => 'Policy violation'],
        ]);

        AuditLog::create([
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'action' => 'CHANGE_ROLE',
            'target' => 'usr_002',
            'ip_address' => '192.168.1.1',
            'metadata' => ['old_role' => 'user', 'new_role' => 'admin'],
        ]);

        $response = $this->getJson('/api/admin/audit-logs', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'logs' => [
                    '*' => [
                        'id',
                        'actorId',
                        'actorEmail',
                        'action',
                        'target',
                        'ipAddress',
                        'createdAt',
                    ],
                ],
                'page',
                'total',
            ]);
    }

    /**
     * Test audit logs include actor information.
     */
    public function test_audit_logs_include_actor_information(): void
    {
        $actor = User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);

        AuditLog::create([
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'action' => 'DELETE_USER',
            'target' => 'usr_100',
            'ip_address' => '10.0.0.1',
        ]);

        $response = $this->getJson('/api/admin/audit-logs', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        $firstLog = $response->json('logs.0');
        $this->assertEquals('admin_' . str_pad($actor->id, 3, '0', STR_PAD_LEFT), $firstLog['actorId']);
        $this->assertEquals('admin@example.com', $firstLog['actorEmail']);
    }

    /**
     * Test audit logs are paginated.
     */
    public function test_audit_logs_are_paginated(): void
    {
        $actor = User::factory()->admin()->create();

        // Create 60 audit logs
        for ($i = 0; $i < 60; $i++) {
            AuditLog::create([
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'action' => 'TEST_ACTION',
                'target' => "target_{$i}",
                'ip_address' => '127.0.0.1',
            ]);
        }

        $response = $this->getJson('/api/admin/audit-logs', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertEquals(60, $response->json('total'));
        $this->assertCount(50, $response->json('logs')); // Default limit is 50
    }

    /**
     * Test audit logs can specify per page.
     */
    public function test_audit_logs_can_specify_per_page(): void
    {
        $actor = User::factory()->admin()->create();

        // Create 30 audit logs
        for ($i = 0; $i < 30; $i++) {
            AuditLog::create([
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'action' => 'TEST_ACTION',
                'target' => "target_{$i}",
                'ip_address' => '127.0.0.1',
            ]);
        }

        $response = $this->getJson('/api/admin/audit-logs?limit=10', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);
        $this->assertCount(10, $response->json('logs'));
    }

    /**
     * Test audit logs are ordered newest first.
     */
    public function test_audit_logs_are_ordered_newest_first(): void
    {
        $actor = User::factory()->admin()->create();

        // Create logs with specific timestamps
        $newLog = AuditLog::create([
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'action' => 'NEW_ACTION',
            'target' => 'new_target',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subHours(1),
            'updated_at' => now()->subHours(1),
        ]);

        $oldLog = AuditLog::create([
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'action' => 'OLD_ACTION',
            'target' => 'old_target',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $response = $this->getJson('/api/admin/audit-logs', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        $logs = $response->json('logs');
        $this->assertEquals('NEW_ACTION', $logs[0]['action']);
        $this->assertEquals('OLD_ACTION', $logs[1]['action']);
    }

    /**
     * Test audit logs with null actor.
     */
    public function test_audit_logs_with_null_actor(): void
    {
        // System action without actor
        AuditLog::create([
            'actor_id' => null,
            'actor_email' => 'system',
            'action' => 'SYSTEM_BACKUP',
            'target' => null,
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson('/api/admin/audit-logs', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        $firstLog = $response->json('logs.0');
        $this->assertNull($firstLog['actorId']);
        $this->assertEquals('system', $firstLog['actorEmail']);
    }

    /**
     * Test audit logs include metadata.
     */
    public function test_audit_logs_include_metadata(): void
    {
        $actor = User::factory()->admin()->create();

        AuditLog::create([
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'action' => 'CHANGE_PLAN',
            'target' => 'sub_123',
            'ip_address' => '192.168.1.1',
            'metadata' => [
                'old_plan' => 'free',
                'new_plan' => 'pro',
                'reason' => 'Upgrade request',
            ],
        ]);

        $response = $this->getJson('/api/admin/audit-logs', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        $firstLog = $response->json('logs.0');
        $metadata = $firstLog['metadata'];
        $this->assertEquals('free', $metadata['old_plan']);
        $this->assertEquals('pro', $metadata['new_plan']);
        $this->assertEquals('Upgrade request', $metadata['reason']);
    }

    /**
     * Test regular user cannot access audit logs.
     */
    public function test_regular_user_cannot_access_audit_logs(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/admin/audit-logs', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
