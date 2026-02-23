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
            ->orderBy('issue_date', 'desc')
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

        // Calculate financial totals
        $subtotal = $this->calculateSubtotal($request->validated('items'));
        $tax_rate = $request->validated('tax_rate', 0);
        $tax_amount = round($subtotal * ($tax_rate / 100), 2);
        $total = round($subtotal + $tax_amount, 2);

        // Create invoice
        $invoice = Invoice::create([
            'user_id' => $request->user()->id,
            'client_id' => $request->validated('client_id'),
            'invoice_number' => $invoiceNumber,
            'subtotal' => $subtotal,
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'total' => $total,
            'status' => $request->validated('status', 'draft'),
            'issue_date' => $request->validated('issue_date'),
            'due_date' => $request->validated('due_date'),
            'notes' => $request->validated('notes'),
            'watermark' => $request->validated('watermark'),
        ]);

        // Create invoice items
        foreach ($request->validated('items') as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
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

        // Calculate financial totals
        $subtotal = $this->calculateSubtotal($request->validated('items'));
        $tax_rate = $request->validated('tax_rate', $invoice->tax_rate);
        $tax_amount = round($subtotal * ($tax_rate / 100), 2);
        $total = round($subtotal + $tax_amount, 2);

        // Update invoice
        $invoice->update([
            'client_id' => $request->validated('client_id'),
            'subtotal' => $subtotal,
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'total' => $total,
            'status' => $request->validated('status', $invoice->status),
            'issue_date' => $request->validated('issue_date', $invoice->issue_date),
            'due_date' => $request->validated('due_date', $invoice->due_date),
            'notes' => $request->validated('notes', $invoice->notes),
            'watermark' => $request->validated('watermark', $invoice->watermark),
        ]);

        // Delete and recreate items
        $invoice->items()->delete();
        foreach ($request->validated('items') as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
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
     * Send invoice via email.
     */
    public function sendEmail(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        // TODO: Implement email sending logic
        // Mail::to($invoice->client->email)->send(new InvoiceMailable($invoice));

        return response()->json([
            'message' => 'Invoice email sent successfully',
        ]);
    }

    /**
     * Send invoice via WhatsApp.
     */
    public function sendWhatsapp(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        // TODO: Implement WhatsApp sending logic via Twilio or similar

        return response()->json([
            'message' => 'Invoice WhatsApp sent successfully',
        ]);
    }

    /**
     * Export invoice as PDF.
     */
    public function pdf(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        // TODO: Implement PDF generation with DomPDF
        return response()->json([
            'message' => 'PDF generation not yet implemented',
        ]);
    }

    /**
     * Calculate subtotal from invoice items array.
     */
    private function calculateSubtotal(array $items): float
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        return round($subtotal, 2);
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
