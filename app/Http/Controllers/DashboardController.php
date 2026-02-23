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
            'total_unpaid' => (float)$unpaidTotal,
            'total_clients' => $totalClients,
            'total_invoices' => $totalInvoices,
        ]);
    }

    /**
     * Get dashboard statistics (alias for index).
     */
    public function stats(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * Export user's invoices as CSV.
     */
    public function exportInvoices(Request $request)
    {
        $userId = $request->user()->id;

        $invoices = Invoice::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'invoices_' . now()->format('Y-m-d') . '.csv';

        // Create CSV headers
        $csv = "Invoice #,Client Name,Amount,Currency,Status,Invoice Date,Due Date,Paid Date\n";

        // Add rows
        foreach ($invoices as $invoice) {
            $csv .= implode(',', [
                $invoice->invoice_number,
                $invoice->client_name,
                $invoice->total_amount,
                $invoice->currency,
                $invoice->status,
                $invoice->invoice_date?->format('Y-m-d') ?? '',
                $invoice->due_date?->format('Y-m-d') ?? '',
                $invoice->paid_date?->format('Y-m-d') ?? '',
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=$filename",
        ]);
    }
}
