<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Export invoices as CSV.
     */
    public function invoicesCsv(Request $request): StreamedResponse
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->with('client', 'items')
            ->get();

        $callback = function () use ($invoices) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Invoice Number',
                'Client Name',
                'Status',
                'Total Amount',
                'Issued At',
                'Due Date',
                'Created At'
            ]);

            // Data rows
            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice->invoice_number,
                    $invoice->client->name,
                    $invoice->status,
                    $invoice->total_amount,
                    $invoice->issued_at,
                    $invoice->due_date,
                    $invoice->created_at,
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="invoices_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Export clients as CSV.
     */
    public function clientsCsv(Request $request): StreamedResponse
    {
        $clients = Client::where('user_id', $request->user()->id)->get();

        $callback = function () use ($clients) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Name',
                'Email',
                'Phone',
                'Notes',
                'Created At'
            ]);

            // Data rows
            foreach ($clients as $client) {
                fputcsv($file, [
                    $client->name,
                    $client->email,
                    $client->phone,
                    $client->notes,
                    $client->created_at,
                ]);
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="clients_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
