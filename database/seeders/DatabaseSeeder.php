<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Solo usuarios: el catálogo (marcas, categorías, productos) lo carga
     * cada negocio desde el panel o con el importador de Excel.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}
