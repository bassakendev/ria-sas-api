<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
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

    protected function tearDown(): void
    {
        Cache::forget('admin_settings');
        parent::tearDown();
    }

    /**
     * Test superadmin can get settings.
     */
    public function test_superadmin_can_get_settings(): void
    {
        $response = $this->getJson('/api/admin/settings', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'security' => [
                    'mfaRequired',
                    'passwordMinLength',
                    'tokenTtlMinutes',
                ],
                'access' => [
                    'allowAdminImpersonation',
                    'maxAdminSessions',
                ],
                'billing' => [
                    'prorationEnabled',
                    'gracePeriodDays',
                    'defaultCurrency',
                ],
                'notifications' => [
                    'slaWarningHours',
                    'emailFrom',
                    'webhookUrl',
                ],
                'integrations' => [
                    'crmProvider',
                    'analyticsProvider',
                ],
                'system' => [
                    'maintenanceMode',
                    'backupFrequencyHours',
                ],
                'audit' => [
                    'retentionDays',
                    'exportEnabled',
                ],
            ]);
    }

    /**
     * Test default settings are returned when cache is empty.
     */
    public function test_default_settings_are_returned(): void
    {
        Cache::forget('admin_settings');

        $response = $this->getJson('/api/admin/settings', [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        // Check default values
        $this->assertFalse($response->json('security.mfaRequired'));
        $this->assertEquals(8, $response->json('security.passwordMinLength'));
        $this->assertEquals(10080, $response->json('security.tokenTtlMinutes'));
        $this->assertEquals('USD', $response->json('billing.defaultCurrency'));
    }

    /**
     * Test superadmin can update security settings.
     */
    public function test_superadmin_can_update_security_settings(): void
    {
        $response = $this->patchJson('/api/admin/settings', [
            'security' => [
                'mfaRequired' => true,
                'passwordMinLength' => 12,
                'tokenTtlMinutes' => 1440,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'security' => [
                    'mfaRequired' => true,
                    'passwordMinLength' => 12,
                    'tokenTtlMinutes' => 1440,
                ],
                'message' => 'Settings updated successfully',
            ]);

        // Verify cache was updated
        $cached = Cache::get('admin_settings');
        $this->assertTrue($cached['security']['mfaRequired']);
        $this->assertEquals(12, $cached['security']['passwordMinLength']);
    }

    /**
     * Test superadmin can update billing settings.
     */
    public function test_superadmin_can_update_billing_settings(): void
    {
        $response = $this->patchJson('/api/admin/settings', [
            'billing' => [
                'prorationEnabled' => true,
                'gracePeriodDays' => 7,
                'defaultCurrency' => 'EUR',
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'billing' => [
                    'prorationEnabled' => true,
                    'gracePeriodDays' => 7,
                    'defaultCurrency' => 'EUR',
                ],
            ]);
    }

    /**
     * Test partial settings update works.
     */
    public function test_partial_settings_update_works(): void
    {
        // First, set some initial settings
        Cache::put('admin_settings', [
            'security' => ['mfaRequired' => true, 'passwordMinLength' => 10, 'tokenTtlMinutes' => 1440],
            'billing' => ['prorationEnabled' => false, 'gracePeriodDays' => 3, 'defaultCurrency' => 'USD'],
        ], 3600);

        // Update only security.mfaRequired
        $response = $this->patchJson('/api/admin/settings', [
            'security' => [
                'mfaRequired' => false,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(200);

        // Other security settings should be preserved
        $this->assertFalse($response->json('security.mfaRequired'));
        $this->assertEquals(10, $response->json('security.passwordMinLength'));

        // Billing settings should be unchanged
        $this->assertFalse($response->json('billing.prorationEnabled'));
    }

    /**
     * Test password min length validation.
     */
    public function test_password_min_length_validation(): void
    {
        // Too short
        $response = $this->patchJson('/api/admin/settings', [
            'security' => [
                'passwordMinLength' => 5,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['security.passwordMinLength']);

        // Too long
        $response = $this->patchJson('/api/admin/settings', [
            'security' => [
                'passwordMinLength' => 50,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['security.passwordMinLength']);
    }

    /**
     * Test token TTL validation.
     */
    public function test_token_ttl_validation(): void
    {
        // Too short
        $response = $this->patchJson('/api/admin/settings', [
            'security' => [
                'tokenTtlMinutes' => 30,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['security.tokenTtlMinutes']);

        // Too long
        $response = $this->patchJson('/api/admin/settings', [
            'security' => [
                'tokenTtlMinutes' => 20000,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['security.tokenTtlMinutes']);
    }

    /**
     * Test grace period validation.
     */
    public function test_grace_period_validation(): void
    {
        // Negative value
        $response = $this->patchJson('/api/admin/settings', [
            'billing' => [
                'gracePeriodDays' => -1,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billing.gracePeriodDays']);

        // Too large
        $response = $this->patchJson('/api/admin/settings', [
            'billing' => [
                'gracePeriodDays' => 50,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billing.gracePeriodDays']);
    }

    /**
     * Test currency validation.
     */
    public function test_currency_validation(): void
    {
        // Invalid currency code
        $response = $this->patchJson('/api/admin/settings', [
            'billing' => [
                'defaultCurrency' => 'INVALID',
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billing.defaultCurrency']);

        // Too short
        $response = $this->patchJson('/api/admin/settings', [
            'billing' => [
                'defaultCurrency' => 'US',
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billing.defaultCurrency']);
    }

    /**
     * Test SLA warning hours validation.
     */
    public function test_sla_warning_hours_validation(): void
    {
        // Too small
        $response = $this->patchJson('/api/admin/settings', [
            'notifications' => [
                'slaWarningHours' => 0,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['notifications.slaWarningHours']);

        // Too large
        $response = $this->patchJson('/api/admin/settings', [
            'notifications' => [
                'slaWarningHours' => 100,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['notifications.slaWarningHours']);
    }

    /**
     * Test audit retention validation.
     */
    public function test_audit_retention_validation(): void
    {
        // Too short
        $response = $this->patchJson('/api/admin/settings', [
            'audit' => [
                'retentionDays' => 10,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audit.retentionDays']);

        // Too long
        $response = $this->patchJson('/api/admin/settings', [
            'audit' => [
                'retentionDays' => 500,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audit.retentionDays']);
    }

    /**
     * Test settings update creates audit log.
     */
    public function test_settings_update_creates_audit_log(): void
    {
        $this->patchJson('/api/admin/settings', [
            'security' => [
                'mfaRequired' => true,
            ],
        ], [
            'Authorization' => "Bearer {$this->token}",
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->superadmin->id,
            'action' => 'UPDATE_SETTINGS',
        ]);
    }

    /**
     * Test regular user cannot access settings.
     */
    public function test_regular_user_cannot_access_settings(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->getJson('/api/admin/settings', [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test regular user cannot update settings.
     */
    public function test_regular_user_cannot_update_settings(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->patchJson('/api/admin/settings', [
            'security' => ['mfaRequired' => true],
        ], [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertStatus(403);
    }
}
