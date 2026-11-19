<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leases:update-statuses')->daily();

Schedule::command('invoices:generate-recurring')->dailyAt('00:10')
    ->timezone(config('app.timezone'))->withoutOverlapping();

if (config('queue.bulk_scheduled_worker')) {
    Schedule::command('queue:work bulk --queue=bulk --stop-when-empty --max-time=50 --timeout=600 --tries=1')
        ->everyMinute()->withoutOverlapping(15)->runInBackground();

    Schedule::command('queue:work tenant_bulk --queue=tenant-bulk --stop-when-empty --max-time=50 --timeout=600 --tries=1')
        ->everyMinute()->withoutOverlapping(15)->runInBackground();
}
