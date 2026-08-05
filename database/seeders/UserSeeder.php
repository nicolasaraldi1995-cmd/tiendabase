<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Las dos cuentas con las que se entra por primera vez.
 *
 * La contraseña no está escrita acá: se sortea y se muestra una sola vez al
 * instalar. El motor se instala en servidores de terceros, y una clave fija en
 * el repositorio es una llave que abre todas las tiendas. Se puede fijar de
 * antemano con CLAVE_ADMIN y CLAVE_OPERADOR en el .env.
 *
 * Si nadie la anotó: php artisan usuarios:clave admin@tienda.test <clave-nueva>
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Administrador', 'admin@tienda.test', 'admin', 'CLAVE_ADMIN'],
            ['Operador', 'operador@tienda.test', 'operador', 'CLAVE_OPERADOR'],
        ] as [$nombre, $email, $rol, $variable]) {
            $clave = env($variable) ?: Str::password(16);

            // firstOrCreate y no create: "composer run setup" se puede correr de
            // nuevo sobre una tienda ya instalada sin duplicar ni pisar nada.
            $usuario = User::firstOrCreate(['email' => $email], [
                'name' => $nombre,
                'password' => bcrypt($clave),
                'role' => $rol,
            ]);

            // Solo si se creó recién: si la cuenta ya existía, la clave que se
            // imprimiría no es la que sirve para entrar.
            if ($usuario->wasRecentlyCreated) {
                $this->command?->warn("  {$email} → {$clave}");
            }
        }

        $this->command?->warn('  Anotá esas contraseñas: no se vuelven a mostrar.');
    }
}
