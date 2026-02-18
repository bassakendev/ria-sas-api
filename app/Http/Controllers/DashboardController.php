<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalRevenue = Invoice::where('user_id', $userId)
            ->where('status', 'paid')
            ->sum('total_amount');

        $unpaidTotal = Invoice::where('user_id', $userId)
            ->where('status', 'unpaid')
            ->sum('total_amount');

        $totalClients = $request->user()->clients()->count();

        $totalInvoices = Invoice::where('user_id', $userId)->count();

        return response()->json([
            'total_revenue' => (float)$totalRevenue,
            'unpaid_total' => (float)$unpaidTotal,
            'total_clients' => $totalClients,
            'total_invoices' => $totalInvoices,
        ]);
    }
}
