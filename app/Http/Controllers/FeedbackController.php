<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedbackRequest;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    /**
     * Create feedback for the authenticated user.
     */
    public function create(FeedbackRequest $request): JsonResponse
    {
        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'type' => $request->validated('type'),
            'email' => $request->validated('email'),
            'subject' => $request->validated('subject'),
            'message' => $request->validated('message'),
            'status' => 'new',
        ]);

        return response()->json([
            'message' => 'Feedback submitted successfully',
            'feedback' => $feedback,
        ], 201);
    }

    /**
     * Get all feedback for the authenticated user (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $feedbacks = Feedback::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($feedbacks);
    }

    /**
     * Get feedback by ID.
     */
    public function show(Request $request, Feedback $feedback): JsonResponse
    {
        if ($feedback->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($feedback);
    }

    /**
     * Mark feedback as read.
     */
    public function markAsRead(Request $request, Feedback $feedback): JsonResponse
    {
        if ($feedback->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $feedback->update(['status' => 'read']);

        return response()->json($feedback);
    }

    /**
     * Close feedback.
     */
    public function close(Request $request, Feedback $feedback): JsonResponse
    {
        if ($feedback->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $feedback->update(['status' => 'closed']);

        return response()->json($feedback);
    }

    /**
     * Delete feedback.
     */
    public function destroy(Request $request, Feedback $feedback): JsonResponse
    {
        if ($feedback->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $feedback->delete();

        return response()->json(['message' => 'Feedback deleted successfully']);
    }
}
