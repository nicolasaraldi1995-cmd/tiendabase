<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * firstOrCreate y no create: `composer run setup` se puede correr de
     * nuevo sobre una tienda ya instalada sin duplicar ni pisar usuarios.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'admin@tienda.test'], [
            'name' => 'Administrador',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        User::firstOrCreate(['email' => 'operador@tienda.test'], [
            'name' => 'Operador',
            'password' => bcrypt('password'),
            'role' => 'operador',
        ]);
    }
}
