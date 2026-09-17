<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `patients` — one row per person, recipient or donor, as before.
 *
 * Every column the original schema had is here under the same name, so
 * `PatientModel` and the waiting-list score keep working. The additions are
 * the fields the platform's screens ask for and the old schema had nowhere to
 * put; each is marked NEW below.
 */
class CreatePatients extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            // Not auto-increment: the MRN is the hospital's own number, typed
            // in or fetched from TrakCare. The old code rejected anything
            // >= 2147483647, so it always fitted in a 4-byte integer.
            'mrn' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'city' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'phone_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'gender' => [
                'type'       => 'ENUM',
                'constraint' => ['M', 'F'],
                'null'       => true,
            ],
            'age' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'null'       => true,
            ],
            'blood_group' => [
                'type'       => 'ENUM',
                'constraint' => ['A', 'B', 'AB', 'O'],
            ],
            // Kept as `organs`, the original name, rather than tidied to the
            // singular: ListsModel reads it back by name with SHOW COLUMNS,
            // and a rename would have to be chased through every caller for
            // no behavioural gain.
            'organs' => [
                'type'       => 'ENUM',
                'constraint' => ['kidney', 'liver'],
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['recipient', 'donor'],
            ],
            // The original five values plus `completed` and `cancelled`, which
            // the platform's Donor Status dropdown offers.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'ready', 'active', 'on_hold', 'declined', 'completed', 'cancelled'],
                'default'    => 'pending',
            ],
            // Was 0/1. The platform grades urgency in four steps, so it is an
            // ENUM now; `is_urgent` below keeps the old boolean readable and
            // `urgency_rank` keeps it sortable. Both are derived, never written.
            //
            // The values are declared least-urgent-first on purpose: MySQL
            // sorts an ENUM by declaration index, so `ORDER BY urgency DESC`
            // — which PairsModel::get_unmatched_recipients() still does —
            // keeps meaning most-urgent-first, exactly as it did when this
            // column held 0 or 1. Declaring them critical-first would quietly
            // invert the waiting list. The screens take their own order from
            // UiStore::URGENCY_OPTIONS, so this is invisible in the UI.
            'urgency' => [
                'type'       => 'ENUM',
                'constraint' => ['low', 'medium', 'high', 'critical'],
                'default'    => 'medium',
            ],
            'mrp_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            // NEW. The add-patient form always had a Coordinator dropdown and
            // the platform has one too, but no column ever existed, so the
            // choice was silently dropped on save.
            'coordinator_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            // NEW. Shown on every record screen and in the donors table.
            'hospital' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            // NEW. Recipient screens collect a primary diagnosis.
            'diagnosis' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            // NEW. Donors only; the donors table renders it as a badge.
            'donation_type' => [
                'type'       => 'ENUM',
                'constraint' => ['living', 'deceased'],
                'null'       => true,
            ],
            // NEW. Donors carry "Brother of recipient R-001" style text. The
            // pair also has a relationship; this one survives before a pair
            // exists.
            'relationship' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'dialysis' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'entry_date' => [
                'type' => 'DATE',
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('mrn');
        // The waitlist, donors list and pairs filters all narrow on these.
        $this->forge->addKey(['organs', 'type']);
        $this->forge->addKey('blood_group');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('mrp_id', 'mrp', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('coordinator_id', 'coordinators', 'coordinator_id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('patients', true, ['ENGINE' => 'InnoDB']);

        // Generated columns are outside Forge's vocabulary, so they are added
        // by hand. STORED rather than VIRTUAL so both can be indexed.
        // prefixTable() because raw SQL does not get DBPrefix applied for us.
        $patients = $this->db->protectIdentifiers($this->db->prefixTable('patients'), false, true);

        $this->db->query(<<<SQL
            ALTER TABLE {$patients}
                ADD COLUMN `is_urgent` TINYINT(1)
                    GENERATED ALWAYS AS (`urgency` IN ('critical', 'high')) STORED
                    COMMENT 'The old 0/1 urgency, derived from the four-step one',
                ADD COLUMN `urgency_rank` TINYINT(1)
                    GENERATED ALWAYS AS (FIELD(`urgency`, 'critical', 'high', 'medium', 'low')) STORED
                    COMMENT '1 = critical .. 4 = low; ORDER BY this ASC for most-urgent-first',
                ADD KEY `patients_urgency_rank` (`urgency_rank`)
            SQL);
    }

    public function down(): void
    {
        $this->forge->dropTable('patients', true);
    }
}
