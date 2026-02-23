<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminSettingsController extends Controller
{
    private const SETTINGS_CACHE_KEY = 'admin_settings';

    /**
     * Get all system settings.
     */
    public function index(): JsonResponse
    {
        $settings = Cache::remember(self::SETTINGS_CACHE_KEY, 3600, function () {
            return $this->getDefaultSettings();
        });

        return response()->json($settings);
    }

    /**
     * Update system settings (partial update supported).
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'security' => 'nullable|array',
            'security.mfaRequired' => 'nullable|boolean',
            'security.passwordMinLength' => 'nullable|integer|min:8|max:32',
            'security.tokenTtlMinutes' => 'nullable|integer|min:60|max:10080',

            'access' => 'nullable|array',
            'access.allowAdminImpersonation' => 'nullable|boolean',
            'access.maxAdminSessions' => 'nullable|integer|min:1|max:10',

            'billing' => 'nullable|array',
            'billing.prorationEnabled' => 'nullable|boolean',
            'billing.gracePeriodDays' => 'nullable|integer|min:0|max:30',
            'billing.defaultCurrency' => 'nullable|string|size:3',

            'notifications' => 'nullable|array',
            'notifications.slaWarningHours' => 'nullable|integer|min:1|max:72',
            'notifications.emailFrom' => 'nullable|email',
            'notifications.webhookUrl' => 'nullable|url',

            'integrations' => 'nullable|array',
            'integrations.crmProvider' => 'nullable|string',
            'integrations.analyticsProvider' => 'nullable|string',

            'system' => 'nullable|array',
            'system.maintenanceMode' => 'nullable|boolean',
            'system.backupFrequencyHours' => 'nullable|integer|min:1|max:168',

            'audit' => 'nullable|array',
            'audit.retentionDays' => 'nullable|integer|min:30|max:365',
            'audit.exportEnabled' => 'nullable|boolean',
        ]);

        // Get current settings
        $currentSettings = Cache::get(self::SETTINGS_CACHE_KEY, $this->getDefaultSettings());

        // Merge with new settings (deep merge)
        $newSettings = array_replace_recursive($currentSettings, $request->only([
            'security', 'access', 'billing', 'notifications',
            'integrations', 'system', 'audit'
        ]));

        // Store in cache (in production, store in database)
        Cache::put(self::SETTINGS_CACHE_KEY, $newSettings, 3600);

        AuditLog::log('UPDATE_SETTINGS', null, [
            'changes' => $request->only([
                'security', 'access', 'billing', 'notifications',
                'integrations', 'system', 'audit'
            ]),
        ]);

        return response()->json(array_merge($newSettings, [
            'message' => 'Settings updated successfully',
        ]));
    }

    /**
     * Get default settings structure.
     */
    private function getDefaultSettings(): array
    {
        return [
            'security' => [
                'mfaRequired' => false,
                'passwordMinLength' => 8,
                'tokenTtlMinutes' => 10080,
            ],
            'access' => [
                'allowAdminImpersonation' => false,
                'maxAdminSessions' => 3,
            ],
            'billing' => [
                'prorationEnabled' => true,
                'gracePeriodDays' => 7,
                'defaultCurrency' => 'USD',
            ],
            'notifications' => [
                'slaWarningHours' => 24,
                'emailFrom' => 'support@ria-sas.local',
                'webhookUrl' => 'https://api.ria-sas.local/webhooks/admin',
            ],
            'integrations' => [
                'crmProvider' => 'hubspot',
                'analyticsProvider' => 'mixpanel',
            ],
            'system' => [
                'maintenanceMode' => false,
                'backupFrequencyHours' => 6,
            ],
            'audit' => [
                'retentionDays' => 90,
                'exportEnabled' => true,
            ],
        ];
    }
}
