<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Un singur cron in cPanel, in fiecare minut: php artisan schedule:run (vezi INSTALL_CPANEL.md).
Schedule::command('petmarket:daily')->dailyAt('04:30')->withoutOverlapping();
Schedule::command('petmarket:backup --label=zilnic --verify')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('auth:clear-resets')->daily();
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
Schedule::call(fn () => Cache::forever('petmarket.last_cron_run', now()->toIso8601String()))->everyMinute()->name('petmarket-heartbeat');
// Reducerile programate (cu data de inceput / sfarsit) isi actualizeaza pretul afisat si filtrele.
Schedule::command('petmarket:sync-sales')->everyFifteenMinutes()->withoutOverlapping();
