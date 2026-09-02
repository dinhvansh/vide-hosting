<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('deployments:reconcile')->everyMinute()->withoutOverlapping();
Schedule::command('subscriptions:process')->hourly()->withoutOverlapping();
Schedule::command('payments:cancel-stale')->everyTenMinutes()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
