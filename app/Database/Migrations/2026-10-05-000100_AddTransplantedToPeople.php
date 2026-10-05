<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A person can be transplanted, because a person is what a transplant happens
 * to.
 *
 * The three words a person could hold — On Hold, Active, Declined — were the
 * three the pair shares with them, and Transplanted was kept back as the
 * pair's alone: the *case* was transplanted, not the patient. That reads
 * wrongly on the register. A recipient whose transplant has happened is not
 * "active" on a waiting list, and the donor who gave is not "active" either;
 * both are the one word the pair already has, and now they can hold it.
 *
 * Both columns are ENUMs, so the word has to be added before anything can
 * write it. Nothing is rewritten: this only widens what may be stored.
 */
class AddTransplantedToPeople extends Migration
{
    public function up(): void
    {
        $this->db->query(
            'ALTER TABLE ' . $this->table('recipients') . " MODIFY status"
            . " ENUM('pending','confirmed','closed','completed','paired_exchange','on_hold','active','declined','transplanted')"
            . " NOT NULL DEFAULT 'pending'"
        );

        $this->db->query(
            'ALTER TABLE ' . $this->table('donors') . " MODIFY status"
            . " ENUM('on_hold','active','declined','completed','cancelled','transplanted')"
            . " NOT NULL DEFAULT 'on_hold'"
        );
    }

    /**
     * Irreversible by design.
     *
     * Narrowing the ENUM again would turn every transplanted person into an
     * empty string, which MariaDB accepts silently — and CodeIgniter caches a
     * connection's field list, so a migration that alters a column back takes
     * the rest of the suite's rollback with it.
     */
    public function down(): void
    {
    }

    private function table(string $name): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($name), true, false, false);
    }
}
