<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// En hosting con cron real (`* * * * * php artisan schedule:run`) esto alcanza solo.
// En Windows/Laragon local no hay cron: hay que crear un Programador de Tareas que
// corra backup:database directo (ver README), y esta línea queda inactiva.
Schedule::command('backup:database')->dailyAt('03:00');
