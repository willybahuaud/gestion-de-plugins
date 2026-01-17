<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadInvoicePdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public Invoice $invoice
    ) {}

    public function handle(): void
    {
        if (!$this->invoice->stripe_pdf_url) {
            Log::info('Invoice has no PDF URL', ['invoice_id' => $this->invoice->id]);
            return;
        }

        // Si déjà téléchargé, ne rien faire
        if ($this->invoice->local_pdf_path) {
            Log::info('Invoice PDF already downloaded', ['invoice_id' => $this->invoice->id]);
            return;
        }

        try {
            $response = Http::timeout(60)->get($this->invoice->stripe_pdf_url);

            if (!$response->successful()) {
                Log::warning('Failed to download invoice PDF', [
                    'invoice_id' => $this->invoice->id,
                    'status' => $response->status(),
                ]);

                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff[$this->attempts() - 1] ?? 900);
                }
                return;
            }

            // Générer le chemin de stockage
            $year = $this->invoice->issued_at->format('Y');
            $filename = $this->invoice->number . '.pdf';
            $path = "invoices/{$year}/{$filename}";

            // Sauvegarder sur B2 (ou local en dev)
            $disk = config('app.env') === 'production' ? 'b2' : 'local';

            Storage::disk($disk)->put($path, $response->body(), [
                'visibility' => 'private',
            ]);

            // Mettre à jour la facture
            $this->invoice->update([
                'local_pdf_path' => $path,
            ]);

            Log::info('Invoice PDF downloaded successfully', [
                'invoice_id' => $this->invoice->id,
                'path' => $path,
                'disk' => $disk,
            ]);

        } catch (\Exception $e) {
            Log::error('Exception downloading invoice PDF', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 900);
            }
        }
    }
}
