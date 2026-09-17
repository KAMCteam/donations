<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `recipients` and `donors` — a table each, as the design has them.
 *
 * The original schema kept everyone in one `patients` table with a
 * `type ENUM('recipient','donor')` column. These are two tables instead, which
 * is what the design implies: it has a Recipient Waitlist screen and a Donors
 * List screen, and their forms ask for different things. The split is not only
 * cosmetic — it removes columns that were always NULL for one side:
 *
 *   recipients only   entry_date, urgency, diagnosis, dialysis
 *   donors only       donation_type, relationship
 *
 * Everything the retained models read still works, because
 * `CreateCompatibilityViews` adds a `patients` view that unions the two back
 * together with the `type` column those models filter on. So
 * `PatientModel::get_all_patients()`, `PairsModel::get_unmatched_donors()` and
 * the waiting-list score all keep running against the same names as before.
 * What that view cannot do is take an INSERT — MySQL will not write through a
 * UNION — so new people are written to these two tables directly.
 */
class CreatePeople extends Migration
{
    public function up(): void
    {
        $this->createRecipients();
        $this->createDonors();
    }

    public function down(): void
    {
        $this->forge->dropTable('donors', true);
        $this->forge->dropTable('recipients', true);
    }

    private function createRecipients(): void
    {
        $this->forge->addField(array_merge($this->sharedFields(), [
            // ---- Recipient-only, all of it from the recipient form ----------
            'diagnosis' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            // "First Dialysis" on the form, and half of the waiting-list score.
            'dialysis' => [
                'type' => 'DATE',
                'null' => true,
            ],
            // "Entry Date"; the other half of the score. Required, since the
            // score is meaningless without it.
            'entry_date' => [
                'type' => 'DATE',
            ],
            // Was 0/1 in the original schema. The design grades it in four
            // steps, so it is an ENUM — declared least-urgent-first on
            // purpose, because MySQL sorts an ENUM by declaration index and
            // `PairsModel::get_unmatched_recipients()` still does
            // `ORDER BY urgency DESC`, which has to keep meaning
            // most-urgent-first. Declaring it critical-first would silently
            // invert the waiting list. The screens take their own order from
            // UiStore::URGENCY_OPTIONS, so this is invisible in the UI.
            'urgency' => [
                'type'       => 'ENUM',
                'constraint' => ['low', 'medium', 'high', 'critical'],
                'default'    => 'medium',
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
        ]));

        $this->forge->addPrimaryKey('mrn');
        $this->forge->addKey('organs');
        $this->forge->addKey('blood_group');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('mrp_id', 'mrp', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('coordinator_id', 'coordinators', 'coordinator_id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('recipients', true, ['ENGINE' => 'InnoDB']);

        // Generated columns are outside Forge's vocabulary. STORED rather than
        // VIRTUAL so `urgency_rank` can be indexed. prefixTable() because raw
        // SQL does not get DBPrefix applied for us.
        $recipients = $this->name('recipients');

        $this->db->query(<<<SQL
            ALTER TABLE {$recipients}
                ADD COLUMN `is_urgent` TINYINT(1)
                    GENERATED ALWAYS AS (`urgency` IN ('critical', 'high')) STORED
                    COMMENT 'The original 0/1 urgency, derived from the four-step one',
                ADD COLUMN `urgency_rank` TINYINT(1)
                    GENERATED ALWAYS AS (FIELD(`urgency`, 'critical', 'high', 'medium', 'low')) STORED
                    COMMENT '1 = critical .. 4 = low; ORDER BY this ASC for most-urgent-first',
                ADD KEY `recipients_urgency_rank` (`urgency_rank`)
            SQL);
    }

    private function createDonors(): void
    {
        $this->forge->addField(array_merge($this->sharedFields(), [
            // ---- Donor-only -------------------------------------------------
            // Rendered as the badge in the Donors List's Type column.
            'donation_type' => [
                'type'       => 'ENUM',
                'constraint' => ['living', 'deceased'],
                'default'    => 'living',
            ],
            // "Brother of recipient R-001". The pair carries a relationship
            // too; this one exists before any pair does, and the Donors List
            // shows it for unmatched donors.
            'relationship' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            // When the donor came onto the register. Nullable, unlike a
            // recipient's: no score depends on it.
            'entry_date' => [
                'type' => 'DATE',
                'null' => true,
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
        ]));

        $this->forge->addPrimaryKey('mrn');
        $this->forge->addKey('organs');
        $this->forge->addKey('blood_group');
        $this->forge->addKey('status');
        $this->forge->addKey('donation_type');
        $this->forge->addForeignKey('mrp_id', 'mrp', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('coordinator_id', 'coordinators', 'coordinator_id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('donors', true, ['ENGINE' => 'InnoDB']);
    }

    /**
     * The columns both forms collect, under the original schema's names so the
     * retained models and the `patients` view line up.
     *
     * @return array<string, array<string, mixed>>
     */
    private function sharedFields(): array
    {
        return [
            // The hospital's own medical record number, typed in or fetched
            // from TrakCare — not auto-increment. The original code rejected
            // anything >= 2147483647, so it always fitted in four bytes.
            //
            // The same person can appear in both tables under one MRN: they
            // may donate on one programme and receive on another, and it is
            // still one person with one set of lab results.
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
            // Kept plural, the original name: ListsModel reads it back with
            // SHOW COLUMNS to build its dropdowns. Values match
            // organ_programs.code.
            'organs' => [
                'type'       => 'ENUM',
                'constraint' => ['kidney', 'liver'],
            ],
            // The original five values plus `completed` and `cancelled`, which
            // the design's Donor Status dropdown offers.
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'ready', 'active', 'on_hold', 'declined', 'completed', 'cancelled'],
                'default'    => 'pending',
            ],
            'hospital' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'mrp_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            // The add-patient form always had a Coordinator dropdown and the
            // design has one too, but no column ever existed, so the choice
            // was silently dropped on save.
            'coordinator_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
        ];
    }

    /** Prefixed and quoted name, since raw SQL does not get DBPrefix applied. */
    private function name(string $table): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($table), false, true);
    }
}
