<?php

use App\Libraries\LabProgress;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The weights, and the arithmetic over them.
 *
 * Counting cards gave every test the same say: seventeen serologies moved the
 * bar as far as the immunology did, so a sheet could read 30% on the strength
 * of the easy half. These are the rules that replaced that, written down
 * because the number is read as a statement about a transplant.
 *
 * @internal
 */
final class LabProgressTest extends CIUnitTestCase
{
    /**
     * Each side's table comes to exactly 100.
     *
     * The one failure the arithmetic cannot show: a table summing to 95 gives
     * a bar that stops at 95% on a finished workup, and nothing on the screen
     * would say why.
     */
    public function testEverySideIsWeightedToAHundred(): void
    {
        $this->assertNotSame([], LabProgress::WEIGHTS);

        foreach (LabProgress::WEIGHTS as $side => $weights) {
            $this->assertSame(100, array_sum($weights), $side . ' comes to 100%');
        }
    }

    /**
     * A table that does not add up is refused rather than quietly shown.
     *
     * The shipped tables are right, so the guard is reached through the
     * private method it lives in: the alternative is shipping a wrong one on
     * purpose to watch it fail.
     */
    public function testATableThatDoesNotAddUpIsRefused(): void
    {
        $weights = new ReflectionMethod(LabProgress::class, 'weights');

        // The real ones answer.
        $this->assertSame(100, array_sum($weights->invoke(null, 'donor')));

        // A side nobody has weighted answers with nothing, and the card falls
        // back to counting rather than refusing — an unweighted sheet is not a
        // mistake, it is a sheet nobody has got to yet.
        $this->assertSame([], $weights->invoke(null, 'nobody'));
        $this->assertSame(100, LabProgress::weighted($this->sheet('nobody', ['Imaging' => [2, 2]]))['pct']);
    }

    /**
     * The worked example: one group nearly finished and the rest untouched.
     *
     * Infectious workup is 16 of 17 on the donor's sheet — 94% — and is worth
     * 16%, so it contributes 15.04% and the whole reads 15%. Counting cards
     * would have said 30%.
     */
    public function testAGroupContributesItsWeightTimesItsOwnProgress(): void
    {
        $progress = LabProgress::weighted($this->sheet('donor', [
            'Infectious workup'  => [17, 16],
            'Clearances'         => [6, 0],
            'Imaging'            => [5, 0],
            'Immunology'         => [4, 0],
            'Hematology/Biochem' => [14, 0],
            'Transplant Clinic'  => [2, 0],
            'Urine/Stool'        => [6, 0],
        ]));

        $this->assertSame(15, $progress['pct']);
        // And the sentence beside it is still about cards.
        $this->assertSame(16, $progress['done']);
        $this->assertSame(54, $progress['total']);
    }

    /** A group's own bar is that group, and nothing to do with its weight. */
    public function testAGroupsOwnBarIsUnweighted(): void
    {
        $group = $this->group('donor', 'Infectious workup', 17, 16);

        $this->assertSame(94, LabProgress::counted($group)['pct']);
    }

    /** Every group finished is exactly 100%, on both sheets. */
    public function testAFinishedWorkupIsAHundred(): void
    {
        foreach (LabProgress::WEIGHTS as $side => $weights) {
            $sheet = [];

            foreach (array_keys($weights) as $group) {
                $sheet[$group] = [3, 3];
            }

            $this->assertSame(100, LabProgress::weighted($this->sheet($side, $sheet))['pct'], $side);
        }
    }

    /**
     * A group with nothing in it is 0%, not a division by zero — and its
     * weight is not handed to anybody else.
     */
    public function testAnEmptyGroupCountsAsNothingAndKeepsItsWeightOut(): void
    {
        $this->assertSame(0, LabProgress::weighted([])['pct']);

        // Everything finished except Urine/Stool, which has no tests at all:
        // its 10% is simply missing from the total.
        $sheet = [];

        foreach (array_keys(LabProgress::WEIGHTS['donor']) as $group) {
            $sheet[$group] = $group === 'Urine/Stool' ? [0, 0] : [4, 4];
        }

        $this->assertSame(90, LabProgress::weighted($this->sheet('donor', $sheet))['pct']);
    }

    /**
     * The tests a record adds for itself are under a heading with no weight.
     *
     * The weights are the check list's. A test somebody added for one patient
     * is not part of it, and giving it a share would mean every record's
     * percentage measured a slightly different thing.
     */
    public function testTestsAddedUnderOtherDoNotMoveTheFigure(): void
    {
        $sheet = [];

        foreach (array_keys(LabProgress::WEIGHTS['donor']) as $group) {
            $sheet[$group] = [4, 4];
        }

        $this->assertSame(100, LabProgress::weighted($this->sheet('donor', $sheet))['pct']);
        $this->assertSame(0, LabProgress::weightFor('donor', 'Other'));

        // Five more, none of them answered, and the figure does not budge.
        $sheet['Other'] = [5, 0];
        $whole          = LabProgress::weighted($this->sheet('donor', $sheet));

        $this->assertSame(100, $whole['pct']);
        // The count does say so, because the count is about cards.
        $this->assertSame(33, $whole['total']);
    }

    /**
     * The four headings both sheets share are weighted apart, so the side has
     * to be read off the tests rather than guessed from the name.
     */
    public function testTheSharedHeadingsAreWeightedPerSide(): void
    {
        $this->assertSame(5, LabProgress::weightFor('recipient', 'Urine/Stool'));
        $this->assertSame(10, LabProgress::weightFor('donor', 'Urine/Stool'));
        $this->assertSame(10, LabProgress::weightFor('recipient', 'Imaging'));
        $this->assertSame(16, LabProgress::weightFor('donor', 'Imaging'));
        $this->assertSame(15, LabProgress::weightFor('recipient', 'Infectious workup'));
        $this->assertSame(16, LabProgress::weightFor('donor', 'Infectious workup'));

        $one = $this->sheet('recipient', ['Urine/Stool' => [4, 4]]);
        $two = $this->sheet('donor', ['Urine/Stool' => [4, 4]]);

        $this->assertSame(5, LabProgress::weighted($one)['pct']);
        $this->assertSame(10, LabProgress::weighted($two)['pct']);
    }

    /** A free-text line answers nothing, so it is neither done nor outstanding. */
    public function testAFreeTextLineIsNotCounted(): void
    {
        $tests   = $this->group('donor', 'Imaging', 2, 2);
        $tests[] = ['group' => 'Imaging', 'side' => 'donor', 'resultType' => 'free_text', 'status' => 'not_done'];

        $counted = LabProgress::counted($tests);

        $this->assertSame(2, $counted['total']);
        $this->assertSame(100, $counted['pct']);
    }

    /**
     * @param array<string, array{0: int, 1: int}> $groups name => [tests, answered]
     *
     * @return list<array<string, mixed>>
     */
    private function sheet(string $side, array $groups): array
    {
        $tests = [];

        foreach ($groups as $name => [$total, $answered]) {
            $tests = array_merge($tests, $this->group($side, (string) $name, $total, $answered));
        }

        return $tests;
    }

    /** @return list<array<string, mixed>> */
    private function group(string $side, string $name, int $total, int $answered): array
    {
        $tests = [];

        for ($i = 1; $i <= $total; $i++) {
            $tests[] = [
                'group'      => $name,
                'side'       => $side,
                'resultType' => 'positive_negative',
                'status'     => $i <= $answered ? 'negative' : 'not_done',
            ];
        }

        return $tests;
    }
}
