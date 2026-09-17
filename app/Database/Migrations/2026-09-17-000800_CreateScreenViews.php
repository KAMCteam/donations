<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A database object per list screen in the design.
 *
 * The design has four screens that show data — Recipient Waitlist, Donors
 * List, Pairs List and Dashboard — and `patients` / `pairs` alone do not make
 * that obvious: a donor is a `patients` row with `type = 'donor'`, and the
 * Pairs List needs both sides of a pair flattened onto one row with their lab
 * counts. These views give each screen something named after it, shaped the
 * way the screen reads:
 *
 *   recipients      every recipient, with lab progress and whether paired
 *   donors          every donor, likewise — the Donors List screen
 *   pairs_overview  one row per pair, both sides flattened — the Pairs List
 *   dashboard_stats one row per programme — the Dashboard's four counters
 *
 * `waiting_list` (earlier migration) is the fifth: unmatched recipients with
 * the score, which is the Recipient Waitlist screen's own filter.
 *
 * They are views, not tables: one copy of the data, `patients` and `pairs`
 * stay the only things written to, and there is nothing to keep in sync. A
 * separate physical `donors` table would mean duplicating fifteen columns,
 * two different foreign-key targets on `pairs`, and splitting
 * `lab_results.patient_id` in two.
 *
 * Note the asymmetry that mirrors the screens: `recipients` and `donors` hold
 * *everyone* of that type, with an `is_matched` flag, so the register is
 * queryable in full; each screen adds `WHERE is_matched = 0` for the unmatched
 * list it actually renders.
 */
class CreateScreenViews extends Migration
{
    public function up(): void
    {
        $this->db->query('CREATE OR REPLACE VIEW ' . $this->name('recipients') . ' AS ' . $this->personSelect('recipient'));
        $this->db->query('CREATE OR REPLACE VIEW ' . $this->name('donors') . ' AS ' . $this->personSelect('donor'));
        $this->db->query('CREATE OR REPLACE VIEW ' . $this->name('pairs_overview') . ' AS ' . $this->pairsSelect());
        $this->db->query('CREATE OR REPLACE VIEW ' . $this->name('dashboard_stats') . ' AS ' . $this->dashboardSelect());
    }

    public function down(): void
    {
        foreach (['dashboard_stats', 'pairs_overview', 'donors', 'recipients'] as $view) {
            $this->db->query('DROP VIEW IF EXISTS ' . $this->name($view));
        }
    }

    /**
     * One person type, with everything the waitlist and donors screens show:
     * the programme's label, the responsible physician and coordinator by
     * name, the lab progress the cards count, and whether they are in an open
     * pair — the "unmatched" both screens filter on.
     */
    private function personSelect(string $type): string
    {
        $patients    = $this->name('patients');
        $mrp         = $this->name('mrp');
        $coordinator = $this->name('coordinators');
        $programs    = $this->name('organ_programs');
        $labResults  = $this->name('lab_results');
        $pairs       = $this->name('pairs');

        // A recipient is matched through pairs.recipient_mrn, a donor through
        // pairs.donor_mrn; closed pairs do not count, exactly as PairsModel has
        // it. This is the only part of the two views that differs.
        $matchColumn = $type === 'recipient' ? 'recipient_mrn' : 'donor_mrn';

        return <<<SQL
            SELECT
                p.*,
                op.label       AS program_label,
                op.description AS program_description,
                m.name         AS mrp_name,
                m.mrp_id       AS mrp_code,
                c.coordinator_name,
                (
                    SELECT COUNT(*) FROM {$labResults} lr
                    WHERE lr.patient_id = p.mrn AND lr.status = 'completed'
                ) AS labs_completed,
                (
                    SELECT COUNT(*) FROM {$labResults} lr
                    WHERE lr.patient_id = p.mrn
                ) AS labs_total,
                EXISTS (
                    SELECT 1 FROM {$pairs} pr
                    WHERE pr.{$matchColumn} = p.mrn AND pr.match_status NOT IN ('closed')
                ) AS is_matched
            FROM {$patients} p
            LEFT JOIN {$programs}    op ON op.code = p.organs
            LEFT JOIN {$mrp}          m ON m.id = p.mrp_id
            LEFT JOIN {$coordinator}  c ON c.coordinator_id = p.coordinator_id
            WHERE p.type = '{$type}'
            SQL;
    }

    /**
     * The Pairs List screen: one row per pair, both sides flattened under
     * `r_` and `d_` prefixes, in the order the table's columns run.
     *
     * The organ comes from the recipient — pairs has no organ of its own,
     * since both sides are on the same programme by construction.
     */
    private function pairsSelect(): string
    {
        $pairs      = $this->name('pairs');
        $patients   = $this->name('patients');
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
                dm.name         AS d_mrp_name,
                (
                    SELECT COUNT(*) FROM {$labResults} lr
                    WHERE lr.patient_id = d.mrn AND lr.status = 'completed'
                ) AS d_labs_completed,
                (SELECT COUNT(*) FROM {$labResults} lr WHERE lr.patient_id = d.mrn) AS d_labs_total
            FROM {$pairs} pr
            JOIN {$patients} r ON r.mrn = pr.recipient_mrn
            JOIN {$patients} d ON d.mrn = pr.donor_mrn
            LEFT JOIN {$mrp} rm ON rm.id = r.mrp_id
            LEFT JOIN {$mrp} dm ON dm.id = d.mrp_id
            SQL;
    }

    /**
     * The Dashboard's four bars, one row per programme: Total Recipients,
     * Waitlist (unmatched), Linked Pairs, Active / Scheduled.
     *
     * Driven from `organ_programs`, so a new programme appears on the
     * dashboard with zeroes rather than being missing.
     */
    private function dashboardSelect(): string
    {
        $programs   = $this->name('organ_programs');
        $patients   = $this->name('patients');
        $pairs      = $this->name('pairs');

        return <<<SQL
            SELECT
                op.code  AS organ,
                op.label AS program_label,
                (
                    SELECT COUNT(*) FROM {$patients} p
                    WHERE p.organs = op.code AND p.type = 'recipient'
                ) AS total_recipients,
                (
                    SELECT COUNT(*) FROM {$patients} p
                    WHERE p.organs = op.code AND p.type = 'recipient'
                      AND NOT EXISTS (
                          SELECT 1 FROM {$pairs} pr
                          WHERE pr.recipient_mrn = p.mrn AND pr.match_status NOT IN ('closed')
                      )
                ) AS unmatched_recipients,
                (
                    SELECT COUNT(*) FROM {$patients} p
                    WHERE p.organs = op.code AND p.type = 'donor'
                ) AS total_donors,
                (
                    SELECT COUNT(*) FROM {$patients} p
                    WHERE p.organs = op.code AND p.type = 'donor'
                      AND NOT EXISTS (
                          SELECT 1 FROM {$pairs} pr
                          WHERE pr.donor_mrn = p.mrn AND pr.match_status NOT IN ('closed')
                      )
                ) AS unmatched_donors,
                (
                    SELECT COUNT(*) FROM {$pairs} pr
                    JOIN {$patients} p ON p.mrn = pr.recipient_mrn
                    WHERE p.organs = op.code
                ) AS total_pairs,
                (
                    SELECT COUNT(*) FROM {$pairs} pr
                    JOIN {$patients} p ON p.mrn = pr.recipient_mrn
                    WHERE p.organs = op.code AND pr.match_status IN ('active', 'scheduled')
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
