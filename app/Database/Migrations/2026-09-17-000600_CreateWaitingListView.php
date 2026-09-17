<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `waiting_list` — the unmatched-recipient view, with the score.
 *
 * The score expression is carried over **character for character** from
 * `PairsModel::SCORE_CALC`:
 *
 *     (0.1 * TIMESTAMPDIFF(MONTH, entry_date, CURDATE())) +
 *     (0.1 * TIMESTAMPDIFF(MONTH, dialysis,   CURDATE()))
 *
 * so it keeps producing exactly the numbers the old waiting list produced,
 * including its one quirk: `dialysis` is nullable and NULL + anything is NULL,
 * so a recipient with no dialysis date scores NULL rather than counting only
 * the waiting time. That is the original behaviour and is left alone;
 * `score_entry_only` is added beside it for anyone who wants the
 * waiting-time half on its own.
 *
 * "Unmatched" is the same rule as `PairsModel::get_unmatched_recipients()`:
 * not referenced by any pair whose `match_status` is anything other than
 * `closed`.
 *
 * The view is read-only sugar. `PairsModel` still works untouched, and any
 * SQL client can now ask for the waiting list without rebuilding the score.
 */
class CreateWaitingListView extends Migration
{
    public function up(): void
    {
        // Raw SQL does not get DBPrefix applied, so every name is built here.
        $view        = $this->name('waiting_list');
        $patients    = $this->name('patients');
        $mrp         = $this->name('mrp');
        $coordinator = $this->name('coordinators');
        $labResults  = $this->name('lab_results');
        $pairs       = $this->name('pairs');

        $this->db->query(<<<SQL
            CREATE OR REPLACE VIEW {$view} AS
            SELECT
                p.*,
                m.name   AS mrp_name,
                m.mrp_id AS mrp_code,
                c.coordinator_name,
                (
                    (0.1 * TIMESTAMPDIFF(MONTH, p.entry_date, CURDATE())) +
                    (0.1 * TIMESTAMPDIFF(MONTH, p.dialysis,   CURDATE()))
                ) AS score,
                (0.1 * TIMESTAMPDIFF(MONTH, p.entry_date, CURDATE())) AS score_entry_only,
                (
                    SELECT COUNT(*) FROM {$labResults} lr
                    WHERE lr.patient_id = p.mrn AND lr.status = 'completed'
                ) AS labs_completed,
                (
                    SELECT COUNT(*) FROM {$labResults} lr
                    WHERE lr.patient_id = p.mrn
                ) AS labs_total
            FROM {$patients} p
            LEFT JOIN {$mrp}         m ON m.id = p.mrp_id
            LEFT JOIN {$coordinator} c ON c.coordinator_id = p.coordinator_id
            WHERE p.type = 'recipient'
              AND p.mrn NOT IN (
                  SELECT recipient_mrn FROM {$pairs}
                  WHERE match_status NOT IN ('closed')
              )
            SQL);
    }

    public function down(): void
    {
        $this->db->query('DROP VIEW IF EXISTS ' . $this->name('waiting_list'));
    }

    /** Prefixed and quoted table name for use in raw SQL. */
    private function name(string $table): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($table), false, true);
    }
}
