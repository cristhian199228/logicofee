<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldo diario de la base de datos a SharePoint, a las 11:00 hora de Perú.
Schedule::command('respaldo:base-de-datos')
    ->dailyAt('11:00')
    ->timezone('America/Lima')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/respaldos.log'));
