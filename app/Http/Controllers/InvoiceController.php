<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{

    use AuthorizesRequests;

    /**
     * Display a listing of invoices for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->with('client', 'items')
            ->get();

        return response()->json($invoices);
    }

    /**
     * Store a newly created invoice.
     */
    public function store(InvoiceRequest $request): JsonResponse
    {
        // Verify that the client belongs to the user
        $client = $request->user()->clients()->find($request->validated('client_id'));
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        // Generate invoice number
        $invoiceNumber = $this->generateInvoiceNumber($request->user()->id);

        // Calculate total amount
        $totalAmount = 0;
        $items = $request->validated('items');
        foreach ($items as $item) {
            $totalAmount += $item['quantity'] * $item['unit_price'];
        }

        // Create invoice
        $invoice = Invoice::create([
            'user_id' => $request->user()->id,
            'client_id' => $request->validated('client_id'),
            'invoice_number' => $invoiceNumber,
            'total_amount' => $totalAmount,
            'status' => 'unpaid',
            'issued_at' => $request->validated('issued_at'),
            'due_date' => $request->validated('due_date'),
        ]);

        // Create invoice items
        foreach ($items as $item) {
            $totalPrice = $item['quantity'] * $item['unit_price'];
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_name' => $item['service_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $totalPrice,
            ]);
        }

        return response()->json(
            Invoice::with('client', 'items')->find($invoice->id),
            201
        );
    }

    /**
     * Display the specified invoice.
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json($invoice->load('client', 'items'));
    }

    /**
     * Update the specified invoice.
     */
    public function update(InvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        // Verify that the client belongs to the user
        $client = $request->user()->clients()->find($request->validated('client_id'));
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        // Calculate total amount
        $totalAmount = 0;
        $items = $request->validated('items');
        foreach ($items as $item) {
            $totalAmount += $item['quantity'] * $item['unit_price'];
        }

        // Update invoice
        $invoice->update([
            'client_id' => $request->validated('client_id'),
            'total_amount' => $totalAmount,
            'issued_at' => $request->validated('issued_at'),
            'due_date' => $request->validated('due_date'),
        ]);

        // Delete and recreate items
        $invoice->items()->delete();
        foreach ($items as $item) {
            $totalPrice = $item['quantity'] * $item['unit_price'];
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_name' => $item['service_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $totalPrice,
            ]);
        }

        return response()->json($invoice->load('client', 'items'));
    }

    /**
     * Remove the specified invoice.
     */
    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }

    /**
     * Mark the invoice as paid.
     */
    public function markAsPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $invoice->markAsPaid();

        return response()->json($invoice);
    }

    /**
     * Export invoice as PDF.
     */
    public function pdf(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        // This will be implemented with DomPDF
        return response()->json([
            'message' => 'PDF generation not yet implemented',
        ]);
    }

    /**
     * Generate a unique invoice number for the user.
     */
    private function generateInvoiceNumber(int $userId): string
    {
        $lastInvoice = Invoice::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastInvoice) {
            return 'INV-001';
        }

        // Extract number from last invoice number
        preg_match('/(\d+)/', $lastInvoice->invoice_number, $matches);
        $nextNumber = isset($matches[1]) ? (int)$matches[1] + 1 : 1;

        return 'INV-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
