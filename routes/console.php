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

// La ventana para pagar es de media hora, así que revisar cada cinco minutos
// suelta el stock enseguida sin llenar de pedidos cancelados el historial.
// OJO: esto también es la reconciliación de los pagos cuyo aviso nunca llegó,
// así que en un hosting SIN scheduler no solo se traba el stock — se pierden
// acreditaciones. Verificar que corra de verdad, no solo que esté escrito acá.
Schedule::command('pedidos:vencer-impagos')->everyFiveMinutes()->withoutOverlapping();
