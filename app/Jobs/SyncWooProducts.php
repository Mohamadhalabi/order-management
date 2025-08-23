<?php

namespace App\Jobs;

use App\Services\WooSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWooProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WooSyncService $svc): void
    {
        try {
            $count = $svc->syncProducts();
            logger()->info("Woo product sync completed", ['count' => $count]);
        } catch (\Throwable $e) {
            logger()->error('Woo product sync failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            throw $e; // let the job be marked failed / retried
        }
    }
}
