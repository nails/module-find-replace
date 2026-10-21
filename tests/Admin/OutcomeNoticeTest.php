<?php

namespace Tests\FindReplace\Admin;

use Nails\FindReplace\Admin\OutcomeNotice;
use Nails\FindReplace\Factory\Hit;
use Nails\FindReplace\Factory\Matched;
use Nails\FindReplace\Factory\Outcome;
use Nails\FindReplace\Reason;
use PHPUnit\Framework\TestCase;

class OutcomeNoticeTest extends TestCase
{
    public function test_summary_counts_each_status(): void
    {
        $notice = new OutcomeNotice();
        $outcomes = [
            new Outcome(Outcome::APPLIED, $this->hit('1')),
            new Outcome(Outcome::APPLIED, $this->hit('2')),
            new Outcome(Outcome::SKIPPED, $this->hit('3'), Reason::STALE),
            new Outcome(Outcome::FAILED, $this->hit('4'), 'change insert failed'),
        ];

        $this->assertSame('Applied 2, skipped 1, failed 1.', $notice->summary($outcomes));
    }

    public function test_applied_rows_add_no_detail(): void
    {
        $notice = new OutcomeNotice();

        $this->assertSame('', $notice->skipped([new Outcome(Outcome::APPLIED, $this->hit('1'))]));
        $this->assertSame('', $notice->failed([new Outcome(Outcome::APPLIED, $this->hit('1'))]));
    }

    public function test_a_skip_names_the_row_and_the_reason(): void
    {
        $notice = new OutcomeNotice();
        $text = $notice->skipped([
            new Outcome(Outcome::SKIPPED, $this->hit('3'), Reason::STALE),
            new Outcome(Outcome::SKIPPED, $this->hit('9', 'notes'), Reason::SERIALISED),
        ]);

        $this->assertSame(
            'app_article.body #3 was skipped: cell no longer contains the term.'
            . '<br>app_article.notes #9 was skipped: serialised value — edit via its own screen.',
            $text,
        );
    }

    public function test_a_failure_is_escaped_and_kept_apart_from_skips(): void
    {
        $notice = new OutcomeNotice();
        $outcomes = [
            new Outcome(Outcome::SKIPPED, $this->hit('3'), Reason::MISSING),
            new Outcome(Outcome::FAILED, $this->hit('4'), 'insert <failed>'),
        ];

        $this->assertSame(
            'app_article.body #3 was skipped: row not found.',
            $notice->skipped($outcomes),
        );
        $this->assertSame(
            'app_article.body #4 failed: insert &lt;failed&gt;.',
            $notice->failed($outcomes),
        );
    }

    public function test_a_missing_reason_omits_the_colon(): void
    {
        $notice = new OutcomeNotice();

        $this->assertSame(
            'app_article.body #3 was skipped.',
            $notice->skipped([new Outcome(Outcome::SKIPPED, $this->hit('3'))]),
        );
    }

    private function hit(string $id, string $column = 'body'): Hit
    {
        return (new Hit('app_article', $id))->withMatch(
            new Matched('app_article', $column, 'raw', 'Nimbus', $id, 'Nimbus'),
            false,
            null,
        );
    }
}
