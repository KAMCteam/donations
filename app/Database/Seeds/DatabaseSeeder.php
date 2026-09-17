<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Every reference table the platform needs to run, in one command:
 *
 *     php spark db:seed DatabaseSeeder
 *
 * Seeds no people — no patients, no staff accounts, no physicians or
 * coordinators. Only the programmes the picker offers and the lab catalogue
 * the workup cards are built from. Both are re-runnable.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OrganProgramSeeder::class);
        $this->call(LabCatalogueSeeder::class);
    }
}
