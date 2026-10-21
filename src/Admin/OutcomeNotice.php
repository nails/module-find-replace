<?php

namespace Nails\FindReplace\Admin;

use Nails\FindReplace\Factory\Outcome;

class OutcomeNotice
{
    /**
     * @param list<Outcome> $outcomes
     */
    public function summary(array $outcomes): string
    {
        $applied = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($outcomes as $outcome) {
            if ($outcome->getStatus() === Outcome::APPLIED) {
                $applied++;
            } elseif ($outcome->getStatus() === Outcome::SKIPPED) {
                $skipped++;
            } else {
                $failed++;
            }
        }

        return 'Applied ' . $applied . ', skipped ' . $skipped . ', failed ' . $failed . '.';
    }

    /**
     * Escaped lines for the warning alert. Empty when nothing was skipped.
     *
     * @param list<Outcome> $outcomes
     */
    public function skipped(array $outcomes): string
    {
        return $this->lines($outcomes, Outcome::SKIPPED, 'was skipped');
    }

    /**
     * Escaped lines for the error alert. Empty when nothing failed.
     *
     * @param list<Outcome> $outcomes
     */
    public function failed(array $outcomes): string
    {
        return $this->lines($outcomes, Outcome::FAILED, 'failed');
    }

    /**
     * @param list<Outcome> $outcomes
     */
    private function lines(array $outcomes, string $status, string $verb): string
    {
        $lines = [];

        foreach ($outcomes as $outcome) {
            if ($outcome->getStatus() !== $status) {
                continue;
            }

            $lines[] = $this->line($outcome, $verb);
        }

        return implode('<br>', $lines);
    }

    private function line(Outcome $outcome, string $verb): string
    {
        $hit = $outcome->getHit();
        $label = 'This row';

        if ($hit !== null) {
            $column = $hit->getMatched()?->getColumn() ?? '';
            $label = $hit->getTable();

            if ($column !== '') {
                $label .= '.' . $column;
            }

            $label .= ' #' . $hit->getId();
        }

        $text = $label . ' ' . $verb;
        $reason = trim((string) $outcome->getReason());

        if ($reason !== '') {
            $text .= ': ' . $reason;
        }

        if (!preg_match('/[.!?]$/u', $text)) {
            $text .= '.';
        }

        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
