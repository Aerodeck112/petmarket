<?php

namespace App\Console\Commands;

use App\Services\Subscriptions\SubscriptionActivation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DailyMaintenance extends Command
{
    protected $signature = 'petmarket:daily';

    protected $description = 'Marchează abonamentele expirate și ofertele neplătite și trimite avertizările de expirare.';

    public function handle(SubscriptionActivation $activation): int
    {
        $stats = $activation->runDailyMaintenance();
        Cache::forever('petmarket.last_daily_run', now()->toIso8601String());
        $this->info(sprintf('Expirate: %d, oferte anulate: %d, avertizări trimise: %d', $stats['expired'], $stats['offers_lapsed'], $stats['notices']));

        return self::SUCCESS;
    }
}
