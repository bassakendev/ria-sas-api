<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminFeedbackController extends Controller
{
    /**
     * List all feedbacks with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Feedback::with('user');

        // Filter by type
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // Filter by status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $page = $request->query('page', 1);
        $limit = $request->query('limit', 10);

        $feedbacks = $query->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'total' => $feedbacks->total(),
            'page' => $page,
            'limit' => $limit,
            'feedbacks' => $feedbacks->map(fn ($fb) => [
                'id' => 'fb_' . str_pad($fb->id, 3, '0', STR_PAD_LEFT),
                'type' => $fb->type,
                'status' => $fb->status,
                'email' => $fb->email,
                'subject' => $fb->subject,
                'createdAt' => $fb->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Get detailed feedback information.
     */
    public function show(Feedback $feedback): JsonResponse
    {
        return response()->json([
            'id' => 'fb_' . str_pad($feedback->id, 3, '0', STR_PAD_LEFT),
            'type' => $feedback->type,
            'status' => $feedback->status,
            'email' => $feedback->email,
            'subject' => $feedback->subject,
            'message' => $feedback->message,
            'response' => $feedback->response,
            'respondedAt' => $feedback->responded_at?->toIso8601String(),
            'respondedBy' => $feedback->responded_by,
            'createdAt' => $feedback->created_at->toIso8601String(),
        ]);
    }

    /**
     * Change feedback status.
     */
    public function updateStatus(Request $request, Feedback $feedback): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['new', 'read', 'closed'])],
        ]);

        $oldStatus = $feedback->status;
        $newStatus = $request->input('status');

        $feedback->update(['status' => $newStatus]);

        AuditLog::log('UPDATE_FEEDBACK_STATUS', 'fb_' . $feedback->id, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        return response()->json([
            'id' => 'fb_' . str_pad($feedback->id, 3, '0', STR_PAD_LEFT),
            'status' => $feedback->status,
            'message' => 'Feedback status updated successfully',
        ]);
    }

    /**
     * Add or update response to feedback.
     */
    public function updateResponse(Request $request, Feedback $feedback): JsonResponse
    {
        $request->validate([
            'response' => 'required|string|max:2000',
        ]);

        $response = $request->input('response');
        $admin = $request->user();

        $feedback->update([
            'response' => $response,
            'responded_at' => now(),
            'responded_by' => $admin->email,
            'status' => 'read', // Automatically mark as read when responded
        ]);

        AuditLog::log('UPDATE_FEEDBACK_RESPONSE', 'fb_' . $feedback->id);

        return response()->json([
            'id' => 'fb_' . str_pad($feedback->id, 3, '0', STR_PAD_LEFT),
            'status' => $feedback->status,
            'response' => $feedback->response,
            'respondedAt' => $feedback->responded_at->toIso8601String(),
            'respondedBy' => $feedback->responded_by,
            'message' => 'Feedback response updated successfully',
        ]);
    }

    /**
     * Delete a feedback.
     */
    public function destroy(Feedback $feedback): JsonResponse
    {
        AuditLog::log('DELETE_FEEDBACK', 'fb_' . $feedback->id);

        $feedback->delete();

        return response()->json([
            'message' => 'Feedback deleted successfully',
        ]);
    }
}
