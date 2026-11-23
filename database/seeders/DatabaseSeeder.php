<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Date de baza, necesare si in productie: roluri, metode de livrare, paginile magazinului.
     * Catalogul (produse, categorii, branduri) se importa separat, din admin: Sistem → Import catalog.
     */
    public function run(): void
    {
        $this->call([RolesSeeder::class, ShopSeeder::class]);
    }
}
