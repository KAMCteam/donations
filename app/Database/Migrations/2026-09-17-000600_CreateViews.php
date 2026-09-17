<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The views: one for backwards compatibility, four that are reports.
 *
 *   patients         recipients UNION donors, with the `type` column — this is
 *                    what keeps every retained model working now that the two
 *                    are separate tables
 *   waiting_list     unmatched recipients with the score — the Recipient
 *                    Waitlist screen
 *   donors_list      every donor with lab progress and whether they are
 *                    matched — the Donors List screen
 *   pairs_overview   one row per pair, both sides flattened — the Pairs List
 *   dashboard_stats  one row per programme — the Dashboard's counters
 *
 * `recipients` and `donors` are real tables (see `CreatePeople`); these five
 * are derived, and none of them stores anything. `donors_list` exists because
 * the Donors List screen shows two things no column can hold — how many of a
 * donor's labs are done, and whether they are already in an open pair — so
 * they are counted at read time, the same way `waiting_list` does it for
 * recipients.
 *
 * `patients` exists so that `PatientModel`, `PairsModel`, `QueriesModel` and
 * `ListsModel` keep reading the names and shape they were written against.
 * The other four are the reports the list screens draw, and a report cannot
 * be a table without something to refresh it — which is why these are views
 * while the two registers are not.
 */
class CreateViews extends Migration
{
    public function up(): void
    {
        $this->db->query('CREATE OR REPLACE VIEW ' . $this->name('patients') . ' AS ' . $this->patientsSelect());
        $this->db->query('CREATE OR REPLACE VIEW ' . $this->name('waiting_list') . ' AS ' . $this->waitingListSelect());
        $this->db->query('CREATE OR REPLACE VIEW ' . $this->name('donors_list') . ' AS ' . $this->donorsListSelect());
        $this->db->query('CREATE OR REPLACE VIEW ' . $this->name('pairs_overview') . ' AS ' . $this->pairsSelect());
        $this->db->query('CREATE OR REPLACE VIEW ' . $this->name('dashboard_stats') . ' AS ' . $this->dashboardSelect());
    }

    public function down(): void
    {
        foreach (['dashboard_stats', 'pairs_overview', 'donors_list', 'waiting_list', 'patients'] as $view) {
            $this->db->query('DROP VIEW IF EXISTS ' . $this->name($view));
        }
    }

    /**
     * The original `patients` table, reassembled.
     *
     * Every column the old table had, in its old order, with `type` back and
     * the columns that belong to only one role NULL for the other. Read-only:
     * MySQL will not write through a UNION, so inserts and updates go to
     * `recipients` and `donors` directly.
     */
    private function patientsSelect(): string
    {
        $recipients = $this->name('recipients');
        $donors     = $this->name('donors');

        return <<<SQL
            SELECT
                mrn, name, city, phone_number, gender, age, blood_group, organs,
                'recipient' AS type, status, urgency, is_urgent, urgency_rank,
                mrp_id, coordinator_id, hospital,
                diagnosis, dialysis, entry_date,
                NULL AS donation_type, NULL AS relationship,
                note, created_at, updated_at
            FROM {$recipients}
            UNION ALL
            SELECT
                mrn, name, city, phone_number, gender, age, blood_group, organs,
                'donor' AS type, status,
                NULL AS urgency, NULL AS is_urgent, NULL AS urgency_rank,
                mrp_id, coordinator_id, hospital,
                NULL AS diagnosis, NULL AS dialysis, entry_date,
                donation_type, relationship,
                note, created_at, updated_at
            FROM {$donors}
            SQL;
    }

    /**
     * Unmatched recipients with the waiting-list score.
     *
     * The score expression is carried over character for character from
     * `PairsModel::SCORE_CALC`, including its one quirk: `dialysis` is
     * nullable and NULL plus a number is NULL, so a recipient with no dialysis
     * date scores NULL rather than counting only the waiting time. That is the
     * original behaviour; `score_entry_only` sits beside it for anyone who
     * wants the waiting-time half on its own.
     *
     * "Unmatched" is the same rule as `PairsModel::get_unmatched_recipients()`:
     * not referenced by any pair whose `match_status` is other than `closed`.
     */
    private function waitingListSelect(): string
    {
        $recipients = $this->name('recipients');
        $programs   = $this->name('organ_programs');
        $mrp        = $this->name('mrp');
        $coord      = $this->name('coordinators');
        $labResults = $this->name('lab_results');
        $pairs      = $this->name('pairs');

        return <<<SQL
            SELECT
                r.*,
                op.label       AS program_label,
                op.description AS program_description,
                m.name         AS mrp_name,
                m.mrp_id       AS mrp_code,
                c.coordinator_name,
                (
                    (0.1 * TIMESTAMPDIFF(MONTH, r.entry_date, CURDATE())) +
                    (0.1 * TIMESTAMPDIFF(MONTH, r.dialysis,   CURDATE()))
                ) AS score,
                (0.1 * TIMESTAMPDIFF(MONTH, r.entry_date, CURDATE())) AS score_entry_only,
                (
                    SELECT COUNT(*) FROM {$labResults} lr
                    WHERE lr.patient_id = r.mrn AND lr.status = 'completed'
                ) AS labs_completed,
                (SELECT COUNT(*) FROM {$labResults} lr WHERE lr.patient_id = r.mrn) AS labs_total
            FROM {$recipients} r
            LEFT JOIN {$programs} op ON op.code = r.organs
            LEFT JOIN {$mrp}       m ON m.id = r.mrp_id
            LEFT JOIN {$coord}     c ON c.coordinator_id = r.coordinator_id
            WHERE r.mrn NOT IN (
                SELECT recipient_mrn FROM {$pairs}
                WHERE match_status NOT IN ('closed')
            )
            SQL;
    }

    /**
     * The Donors List screen: every donor, with the two things the table shows
     * that no column holds — the Labs column's done/total, and whether they
     * are in an open pair. The screen renders `WHERE is_matched = 0`.
     */
    private function donorsListSelect(): string
    {
        $donors     = $this->name('donors');
        $programs   = $this->name('organ_programs');
        $mrp        = $this->name('mrp');
        $coord      = $this->name('coordinators');
        $labResults = $this->name('lab_results');
        $pairs      = $this->name('pairs');

        return <<<SQL
            SELECT
                d.*,
                op.label       AS program_label,
                op.description AS program_description,
                m.name         AS mrp_name,
                m.mrp_id       AS mrp_code,
                c.coordinator_name,
                (
                    SELECT COUNT(*) FROM {$labResults} lr
                    WHERE lr.patient_id = d.mrn AND lr.status = 'completed'
                ) AS labs_completed,
                (SELECT COUNT(*) FROM {$labResults} lr WHERE lr.patient_id = d.mrn) AS labs_total,
                EXISTS (
                    SELECT 1 FROM {$pairs} pr
                    WHERE pr.donor_mrn = d.mrn AND pr.match_status NOT IN ('closed')
                ) AS is_matched
            FROM {$donors} d
            LEFT JOIN {$programs} op ON op.code = d.organs
            LEFT JOIN {$mrp}       m ON m.id = d.mrp_id
            LEFT JOIN {$coord}     c ON c.coordinator_id = d.coordinator_id
            SQL;
    }

    /**
     * The Pairs List screen: one row per pair, both sides flattened under `r_`
     * and `d_` prefixes, in the order the screen's columns run.
     *
     * The organ comes from the recipient — a pair has no organ of its own,
     * since both sides are on the same programme by construction.
     */
    private function pairsSelect(): string
    {
        $pairs      = $this->name('pairs');
        $recipients = $this->name('recipients');
        $donors     = $this->name('donors');
        $mrp        = $this->name('mrp');
        $labResults = $this->name('lab_results');

        return <<<SQL
            SELECT
                pr.pair_id,
                pr.match_status,
                pr.programs,
                pr.relationship,
                pr.matched_on,
                pr.surgery_on,
                pr.note,
                pr.created_at,
                r.organs AS organ,

                r.mrn          AS r_mrn,
                r.name         AS r_name,
                r.age          AS r_age,
                r.gender       AS r_gender,
                r.blood_group  AS r_blood_group,
                r.phone_number AS r_phone_number,
                r.dialysis     AS r_dialysis,
                r.entry_date   AS r_entry_date,
                r.urgency      AS r_urgency,
                rm.name        AS r_mrp_name,
                (
                    SELECT COUNT(*) FROM {$labResults} lr
                    WHERE lr.patient_id = r.mrn AND lr.status = 'completed'
                ) AS r_labs_completed,
                (SELECT COUNT(*) FROM {$labResults} lr WHERE lr.patient_id = r.mrn) AS r_labs_total,

                d.mrn           AS d_mrn,
                d.name          AS d_name,
                d.age           AS d_age,
                d.gender        AS d_gender,
                d.blood_group   AS d_blood_group,
                d.phone_number  AS d_phone_number,
                d.donation_type AS d_donation_type,
                d.relationship  AS d_relationship,
                dm.name         AS d_mrp_name,
                (
                    SELECT COUNT(*) FROM {$labResults} lr
                    WHERE lr.patient_id = d.mrn AND lr.status = 'completed'
                ) AS d_labs_completed,
                (SELECT COUNT(*) FROM {$labResults} lr WHERE lr.patient_id = d.mrn) AS d_labs_total
            FROM {$pairs} pr
            JOIN {$recipients} r ON r.mrn = pr.recipient_mrn
            JOIN {$donors}     d ON d.mrn = pr.donor_mrn
            LEFT JOIN {$mrp} rm ON rm.id = r.mrp_id
            LEFT JOIN {$mrp} dm ON dm.id = d.mrp_id
            SQL;
    }

    /**
     * The Dashboard's bars, one row per programme: Total Recipients, Waitlist
     * (unmatched), Linked Pairs, Active / Scheduled, and the donor equivalents.
     *
     * Driven from `organ_programs`, so a new programme shows up with zeroes
     * rather than being missing.
     */
    private function dashboardSelect(): string
    {
        $programs   = $this->name('organ_programs');
        $recipients = $this->name('recipients');
        $donors     = $this->name('donors');
        $pairs      = $this->name('pairs');

        return <<<SQL
            SELECT
                op.code  AS organ,
                op.label AS program_label,
                (SELECT COUNT(*) FROM {$recipients} r WHERE r.organs = op.code) AS total_recipients,
                (
                    SELECT COUNT(*) FROM {$recipients} r
                    WHERE r.organs = op.code
                      AND NOT EXISTS (
                          SELECT 1 FROM {$pairs} pr
                          WHERE pr.recipient_mrn = r.mrn AND pr.match_status NOT IN ('closed')
                      )
                ) AS unmatched_recipients,
                (SELECT COUNT(*) FROM {$donors} d WHERE d.organs = op.code) AS total_donors,
                (
                    SELECT COUNT(*) FROM {$donors} d
                    WHERE d.organs = op.code
                      AND NOT EXISTS (
                          SELECT 1 FROM {$pairs} pr
                          WHERE pr.donor_mrn = d.mrn AND pr.match_status NOT IN ('closed')
                      )
                ) AS unmatched_donors,
                (
                    SELECT COUNT(*) FROM {$pairs} pr
                    JOIN {$recipients} r ON r.mrn = pr.recipient_mrn
                    WHERE r.organs = op.code
                ) AS total_pairs,
                (
                    SELECT COUNT(*) FROM {$pairs} pr
                    JOIN {$recipients} r ON r.mrn = pr.recipient_mrn
                    WHERE r.organs = op.code AND pr.match_status IN ('active', 'scheduled')
                ) AS active_or_scheduled_pairs
            FROM {$programs} op
            WHERE op.is_active = 1
            SQL;
    }

    /** Prefixed and quoted name, since raw SQL does not get DBPrefix applied. */
    private function name(string $table): string
    {
        return $this->db->protectIdentifiers($this->db->prefixTable($table), false, true);
    }
}
