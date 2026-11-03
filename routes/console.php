<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\{Artisan, Schedule};

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leases:update-statuses')->daily();

Schedule::command('invoices:generate-recurring')->dailyAt('00:10')
    ->timezone(config('app.timezone'))->withoutOverlapping();
