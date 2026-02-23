<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    /**
     * List audit logs with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $page = $request->query('page', 1);
        $limit = $request->query('limit', 50);

        $logs = AuditLog::with('actor')
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'total' => $logs->total(),
            'page' => $page,
            'limit' => $limit,
            'logs' => $logs->map(fn ($log) => [
                'id' => 'log_' . str_pad($log->id, 3, '0', STR_PAD_LEFT),
                'actorId' => $log->actor_id ? 'admin_' . str_pad($log->actor_id, 3, '0', STR_PAD_LEFT) : null,
                'actorEmail' => $log->actor_email,
                'action' => $log->action,
                'target' => $log->target,
                'ipAddress' => $log->ip_address,
                'metadata' => $log->metadata,
                'createdAt' => $log->created_at->toIso8601String(),
            ]),
        ]);
    }
}
