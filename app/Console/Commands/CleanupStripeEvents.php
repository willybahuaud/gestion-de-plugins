<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupStripeEvents extends Command
{
    protected $signature = 'stripe:cleanup-events {--days=30 : Nombre de jours a garder}';

    protected $description = 'Supprime les anciens evenements Stripe traites';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = DB::table('stripe_processed_events')
            ->where('processed_at', '<', $cutoff)
            ->delete();

        $this->info("$deleted evenements supprimes (plus vieux que $days jours).");

        return self::SUCCESS;
    }
}
