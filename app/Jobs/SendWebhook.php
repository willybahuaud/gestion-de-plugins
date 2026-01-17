<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use App\Models\WebhookLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nombre de tentatives maximum
     */
    public int $tries = 3;

    /**
     * Délai entre les tentatives (en secondes)
     */
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $event,
        public array $payload
    ) {}

    public function handle(): void
    {
        $fullPayload = [
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ];

        $jsonPayload = json_encode($fullPayload);
        $signature = $this->endpoint->generateSignature($jsonPayload);

        $log = WebhookLog::create([
            'webhook_endpoint_id' => $this->endpoint->id,
            'event' => $this->event,
            'payload' => $fullPayload,
            'attempts' => $this->attempts(),
            'sent_at' => now(),
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->event,
                    'X-Webhook-Timestamp' => now()->timestamp,
                ])
                ->post($this->endpoint->url, $fullPayload);

            $log->update([
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 5000),
            ]);

            if (!$response->successful()) {
                Log::warning('Webhook failed', [
                    'endpoint' => $this->endpoint->url,
                    'event' => $this->event,
                    'status' => $response->status(),
                ]);

                // Si pas de succès et qu'on n'a pas épuisé les tentatives, relancer
                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff[$this->attempts() - 1] ?? 900);
                }
            }
        } catch (\Exception $e) {
            Log::error('Webhook exception', [
                'endpoint' => $this->endpoint->url,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'response_status' => 0,
                'response_body' => $e->getMessage(),
            ]);

            // Relancer si on n'a pas épuisé les tentatives
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 900);
            }
        }
    }
}
