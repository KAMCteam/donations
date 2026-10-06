<?php

namespace App\Libraries;

use LogicException;

// The two words for "nobody has looked at this yet" and the one result type
// that cannot be answered at all are the platform's, said once over there.

/**
 * How far a workup has got — counted, and weighted.
 *
 * Counting the cards gave every test the same say. Seventeen serologies and
 * one cross match moved the bar by the same amount each, so a sheet could read
 * 30% on the strength of the easy half and 30% again with the immunology
 * untouched. The number was arithmetic about cards rather than about a
 * transplant.
 *
 * So the groups carry the weight. Each group's own bar is unchanged — it is
 * that group, 0 to 100% of itself — and the figure at the top of the card is
 * the sum of what each group contributes:
 *
 *     contribution = the group's weight × how far that group has got
 *     the whole    = every contribution added up
 *
 * A group with nothing in it contributes nothing, and its weight is not given
 * to anybody else: a sheet missing a group is a sheet that cannot reach 100%,
 * which is the true thing to say about it.
 *
 * `Other` — the heading a record's own added tests sit under — has no weight
 * and never gets one. The weights are the transplant check list's, and a test
 * somebody added for one patient is not part of it; giving it a share would
 * mean every record's percentage measured a slightly different thing.
 *
 * The counts are untouched. "16 of 54 completed" is still sixteen cards out of
 * fifty-four, because that sentence is about cards and is read as such.
 */
final class LabProgress
{
    /**
     * What each group is worth, per side, as whole percents.
     *
     * The two sheets are weighted apart because they are different sheets
     * asking different questions: the donor's urine and imaging decide whether
     * somebody can safely give a kidney, and carry more than the recipient's
     * do. Four headings are spelled the same on both and weighted differently,
     * which is why the side is read off the tests rather than guessed from the
     * names.
     *
     * Each side must come to exactly 100. {@see self::weights()} refuses to
     * answer otherwise, because a table that sums to 95 produces a bar that
     * cannot be filled and nothing on the screen would say why.
     */
    public const WEIGHTS = [
        'recipient' => [
            'Immunology tests'         => 15,
            'Infectious workup'        => 15,
            'Referrals and Clearances' => 15,
            'Hematology/Biochemistry'  => 10,
            'Imaging'                  => 10,
            'Transplant Clinic'        => 10,
            'Cancer screening'         => 10,
            'Vaccinations'             => 10,
            'Urine/Stool'              => 5,
        ],
        'donor' => [
            'Clearances'         => 16,
            'Imaging'            => 16,
            'Immunology'         => 16,
            'Hematology/Biochem' => 16,
            'Infectious workup'  => 16,
            'Transplant Clinic'  => 10,
            'Urine/Stool'        => 10,
        ],
    ];

    /**
     * One group's own progress: the plain count, and nothing to do with
     * weights.
     *
     * This is what a group's bar shows, and what "16 of 54 completed" counts.
     *
     * @param list<array<string, mixed>> $tests
     *
     * @return array{done: int, total: int, pct: int}
     */
    public static function counted(array $tests): array
    {
        $countable = array_filter(
            $tests,
            // A free-text line is a box somebody writes in: no answer to
            // give, so neither done nor outstanding. Counting it would hold
            // every bar it appears on below 100% for ever.
            static fn (array $test): bool => ! in_array($test['resultType'] ?? '', UiStore::FREE_TEXT_TYPES, true)
        );

        $total = count($countable);
        $done  = count(array_filter(
            $countable,
            static fn (array $test): bool => ! in_array($test['status'] ?? 'not_done', UiStore::RESULT_UNANSWERED, true)
        ));

        return [
            'done'  => $done,
            'total' => $total,
            // `max(…, 1)` and not a branch: a workup of nothing is 0%, and
            // dividing by one says so without a special case.
            'pct'   => (int) round($done / max($total, 1) * 100),
        ];
    }

    /**
     * A whole workup: the same counts, and a percentage that weighs the groups.
     *
     * Pass the whole of somebody's workup. Handed one group it would answer
     * that group's weight times its progress, which is a true number about the
     * wrong thing — {@see self::counted()} is what a group wants.
     *
     * @param list<array<string, mixed>> $tests
     *
     * @return array{done: int, total: int, pct: int}
     */
    public static function weighted(array $tests): array
    {
        $whole   = self::counted($tests);
        $weights = self::weights(self::sideOf($tests));

        // No table for this side — a sheet nobody has weighted — so the honest
        // answer is the one the card gave before there were weights at all.
        if ($weights === []) {
            return $whole;
        }

        $groups = [];

        foreach ($tests as $test) {
            $groups[(string) ($test['group'] ?? '')][] = $test;
        }

        $sum = 0.0;

        foreach ($weights as $group => $weight) {
            // A group with no tests on this record contributes nothing, and
            // keeps its weight out of the total by doing so.
            $sum += $weight * (self::counted($groups[$group] ?? [])['pct'] / 100);
        }

        $whole['pct'] = (int) round($sum);

        return $whole;
    }

    /** What one group is worth on one side, or 0 for a group nobody weighted. */
    public static function weightFor(string $side, string $group): int
    {
        return self::weights($side)[$group] ?? 0;
    }

    /**
     * Which sheet these tests are from.
     *
     * Stamped on every test by the store that reads them, because the four
     * headings the two sheets share are weighted differently and a name cannot
     * say which sheet it is on.
     *
     * @param list<array<string, mixed>> $tests
     */
    public static function sideOf(array $tests): string
    {
        foreach ($tests as $test) {
            if (($test['side'] ?? '') !== '') {
                return (string) $test['side'];
            }
        }

        return '';
    }

    /**
     * One side's table, checked.
     *
     * The check is here rather than in a test alone because the failure it
     * catches is silent: a table summing to 95 gives a bar that stops at 95%
     * on a finished workup, and nothing on the screen would say why. Better to
     * refuse than to show a number that is quietly wrong about a patient.
     *
     * @return array<string, int>
     */
    private static function weights(string $side): array
    {
        $weights = self::WEIGHTS[$side] ?? [];

        if ($weights !== [] && array_sum($weights) !== 100) {
            throw new LogicException(sprintf(
                'The %s workup weights come to %d%%, not 100%%.',
                $side,
                array_sum($weights)
            ));
        }

        return $weights;
    }
}
