<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ruta a mysqldump
    |--------------------------------------------------------------------------
    |
    | Normalmente se deja vacío: el comando de backup busca mysqldump solo, en
    | el PATH del servidor y en la instalación de Laragon. Se completa nada más
    | cuando el backup falla diciendo que no lo encuentra, indicando la ruta
    | entera al ejecutable (variable de entorno BACKUP_MYSQLDUMP_PATH).
    |
    */

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH'),

];
