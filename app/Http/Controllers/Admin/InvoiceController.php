<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with(['user', 'license.product'])
            ->orderByDesc('issued_at');

        // Filtres
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhere('stripe_invoice_id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('issued_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('issued_at', '<=', $request->to);
        }

        $invoices = $query->paginate(25);
        $users = User::orderBy('email')->get(['id', 'email', 'name']);

        return view('admin.invoices.index', compact('invoices', 'users'));
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['user', 'license.product']);

        return view('admin.invoices.show', compact('invoice'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Invoice::with(['user', 'license.product'])
            ->orderByDesc('issued_at');

        // Mêmes filtres que l'index
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('issued_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('issued_at', '<=', $request->to);
        }

        $invoices = $query->get();

        $filename = 'factures_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // En-têtes
            fputcsv($handle, [
                'Numéro',
                'Date',
                'Client',
                'Email',
                'Produit',
                'Montant HT',
                'TVA',
                'Montant TTC',
                'Statut',
                'Stripe ID',
            ], ';');

            foreach ($invoices as $invoice) {
                $amountHt = ($invoice->amount_total - $invoice->amount_tax) / 100;
                $amountTax = $invoice->amount_tax / 100;
                $amountTtc = $invoice->amount_total / 100;

                fputcsv($handle, [
                    $invoice->number,
                    $invoice->issued_at->format('d/m/Y'),
                    $invoice->user?->name ?? '-',
                    $invoice->user?->email ?? '-',
                    $invoice->license?->product?->name ?? '-',
                    number_format($amountHt, 2, ',', ' ') . ' €',
                    number_format($amountTax, 2, ',', ' ') . ' €',
                    number_format($amountTtc, 2, ',', ' ') . ' €',
                    $invoice->status,
                    $invoice->stripe_invoice_id,
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadPdf(Invoice $invoice)
    {
        if (!$invoice->local_pdf_path) {
            return back()->with('error', 'PDF non disponible localement.');
        }

        $disk = config('app.env') === 'production' ? 'b2' : 'local';

        if (!Storage::disk($disk)->exists($invoice->local_pdf_path)) {
            return back()->with('error', 'Fichier PDF introuvable.');
        }

        return Storage::disk($disk)->download(
            $invoice->local_pdf_path,
            $invoice->number . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
