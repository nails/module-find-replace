<?php

use Nails\FindReplace\Admin\Controller\FindReplace;
use Nails\FindReplace\Factory\StoredResult;
use Nails\FindReplace\Model\Search;
use Nails\FindReplace\Resource;

/** @var Resource\Search $search */
/** @var list<StoredResult> $rows */
/** @var array<string, string> $facets */
/** @var array<string, array{label: string, values: array<string, int>}> $facetOptions */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
/** @var bool $canReplace */

$id = (int) $search->id;
$query = ['id' => $id];

if ($facets !== []) {
    $query['facet'] = $facets;
}

$exportUrl = FindReplace::url('export') . '?' . http_build_query($query);
$filtered = $facets !== [];
$badges = [
    Search::STATUS_PENDING => ['badge-warning', 'Pending'],
    Search::STATUS_RUNNING => ['badge-info', 'Running'],
    Search::STATUS_COMPLETE => ['badge-success', 'Complete'],
    Search::STATUS_FAILED => ['badge-danger', 'Failed'],
];
[$badgeClass, $badgeLabel] = $badges[$search->status()] ?? ['badge-secondary', $search->status()];
$scanned = $search->tablesScanned();
$totalTables = $search->tablesTotal();
$percent = $totalTables > 0 ? (int) floor(($scanned / $totalTables) * 100) : 0;
$running = $search->status() === Search::STATUS_RUNNING;

?>
<fieldset class="find-replace-detail"<?php

if ($search->isOpen()) {
    ?> data-find-replace-status data-url="<?=htmlspecialchars(siteUrl('api/find-replace/search/' . $id))?>"<?php
}

?>>
    <legend>Search</legend>
    <p class="find-replace-detail__term">
        <strong><?=htmlspecialchars($search->term())?></strong>
        <?php

        if ($search->replacement() !== '') {
            ?>
            <span class="find-replace-detail__arrow" aria-hidden="true">→</span>
            <strong><?=htmlspecialchars($search->replacement())?></strong>
            <?php
        }

        ?>
    </p>
    <p class="find-replace-detail__meta">
        <span class="badge <?=htmlspecialchars($badgeClass)?>" data-find-replace-badge><?=htmlspecialchars($badgeLabel)?></span>
        <span><?=$search->isCaseSensitive() ? 'Case sensitive' : 'Ignores case'?></span>
        <?php

        if ($search->lastRunLabel() !== '') {
            ?>
            <span>Last run <?=htmlspecialchars($search->lastRunLabel())?></span>
            <?php
        }

        if ($search->status() === Search::STATUS_COMPLETE) {
            ?>
            <span><?=htmlspecialchars((string) $search->resultCount())?> result<?=$search->resultCount() === 1 ? '' : 's'?></span>
            <?php
        }

        ?>
    </p>
    <?php

    if ($search->includeTables() !== []) {
        ?>
        <p class="find-replace-detail__scope">Limited to <?=htmlspecialchars(implode(', ', $search->includeTables()))?></p>
        <?php
    }

    if ($search->excludeTables() !== []) {
        ?>
        <p class="find-replace-detail__scope">Excluding <?=htmlspecialchars(implode(', ', $search->excludeTables()))?></p>
        <?php
    }

    if ($search->isOpen()) {
        ?>
        <p data-find-replace-message>
            <?=$running ? 'Scanning tables.' : 'Waiting for a worker to start this search.'?>
        </p>
        <p class="find-replace-meter-row" data-find-replace-meter<?=$running ? '' : ' hidden'?>>
            <span class="find-replace-meter" role="progressbar" aria-valuemin="0" aria-valuemax="<?=$totalTables?>" aria-valuenow="<?=$scanned?>">
                <span data-find-replace-bar style="width: <?=$percent?>%"></span>
            </span>
            <span class="find-replace-progress"><?=$scanned?> / <?=$totalTables?></span>
        </p>
        <?php
    }

    if ($search->status() === Search::STATUS_FAILED) {
        $failure = $search->errorMessage() !== '' ? $search->errorMessage() : 'The search failed.';
        ?>
        <div class="alert alert-danger"><?=htmlspecialchars($failure)?></div>
        <?php
    }

    ?>
</fieldset>
<?php

if ($search->status() === Search::STATUS_FAILED) {
    echo form_open(FindReplace::url('rerun'));
    ?>
    <input type="hidden" name="id" value="<?=$id?>">
    <button type="submit" class="btn btn-primary">Run again</button>
    <?=form_close()?>
    <?php
}

if ($search->status() === Search::STATUS_COMPLETE) {
    ?>
    <p>
        <?php

        if ($filtered) {
            ?>
            Showing <?=htmlspecialchars((string) $total)?>
            of <?=htmlspecialchars((string) $search->resultCount())?>.
            <a href="<?=htmlspecialchars(FindReplace::url('results') . '?id=' . $id)?>">Clear filters</a>
            <?php
        }

        ?>
        <a class="btn btn-sm btn-default" href="<?=htmlspecialchars($exportUrl)?>">Export CSV</a>
    </p>
    <?=form_open(FindReplace::url('results'), ['method' => 'GET'])?>
    <input type="hidden" name="id" value="<?=$id?>">
    <fieldset>
        <legend>Filters</legend>
        <?php

        foreach ($facetOptions as $key => $option) {
            if ($option['values'] === []) {
                continue;
            }

            ?>
            <label>
                <?=htmlspecialchars($option['label'])?>
                <select name="facet[<?=htmlspecialchars($key)?>]">
                    <option value="">Any</option>
                    <?php

                    foreach ($option['values'] as $value => $count) {
                        $selected = ($facets[$key] ?? '') === (string) $value ? ' selected' : '';
                        ?>
                        <option value="<?=htmlspecialchars((string) $value)?>"<?=$selected?>>
                            <?=htmlspecialchars((string) $value)?> (<?=htmlspecialchars((string) $count)?>)
                        </option>
                        <?php
                    }

                    ?>
                </select>
            </label>
            <?php
        }

        ?>
        <button type="submit" class="btn btn-default">Apply filters</button>
    </fieldset>
    <?=form_close()?>

    <?=form_open(FindReplace::url('replace'), [
        'id' => 'find-replace-results',
        'data-filtered' => $filtered ? '1' : '0',
    ])?>
    <input type="hidden" name="id" value="<?=$id?>">
    <?php

    foreach ($facets as $key => $value) {
        ?>
        <input type="hidden" name="facet[<?=htmlspecialchars($key)?>]" value="<?=htmlspecialchars($value)?>">
        <?php
    }

    ?>
    <table class="table table-striped table-hover find-replace-results">
        <thead class="table-dark">
            <tr>
                <th><input type="checkbox" class="js-toggle-all" aria-label="Select all"></th>
                <th>Record</th>
                <th>Column</th>
                <th>Match</th>
                <th>Replacement</th>
                <th class="actions"></th>
            </tr>
        </thead>
        <tbody>
            <?php

            if ($rows === []) {
                ?>
                <tr>
                    <td colspan="6" class="no-data">No results</td>
                </tr>
                <?php
            }

            foreach ($rows as $row) {
                $hit = $row->getHit();
                ?>
                <tr>
                    <td>
                        <?php

                        if (!$hit->isReadOnly()) {
                            ?>
                            <input type="checkbox" name="result_ids[]" value="<?=$row->getId()?>">
                            <?php
                        }

                        ?>
                    </td>
                    <td>
                        <?php

                        if ($hit->getEntityLabel() !== null && $hit->getEntityLabel() !== '') {
                            ?>
                            <?=htmlspecialchars($hit->getEntityLabel())?>
                            #<?=htmlspecialchars((string) $hit->getEntityId())?>
                            <?php
                        } else {
                            ?>
                            <code><?=htmlspecialchars($hit->getTable())?></code>
                            <?=htmlspecialchars($hit->getId())?>
                            <?php
                        }

                        if ($hit->getTitle() !== null && $hit->getTitle() !== '') {
                            ?>
                            <div><?=htmlspecialchars($hit->getTitle())?></div>
                            <?php
                        }

                        ?>
                    </td>
                    <td><code><?=htmlspecialchars((string) $hit->getMatched()?->getColumn())?></code></td>
                    <td class="find-replace-snippet"><?=$row->snippet($search->isCaseSensitive())?></td>
                    <td class="find-replace-preview"><?=$row->replacementPreview($search->replacement(), $search->isCaseSensitive())?></td>
                    <td class="actions">
                        <?php

                        if ($hit->isReadOnly()) {
                            ?>
                            <span class="text-muted"><?=htmlspecialchars((string) $hit->getReadOnlyReason())?></span>
                            <?php
                        }

                        foreach ($hit->getActions() as $action) {
                            $class = trim('btn btn-sm btn-primary ' . (string) $action->getCssClass());
                            $target = $action->getTarget();
                            ?>
                            <a class="<?=htmlspecialchars($class)?>" href="<?=htmlspecialchars($action->getUrl())?>"<?php

                            if ($target !== null && $target !== '') {
                                ?> target="<?=htmlspecialchars($target)?>" rel="noopener"<?php
                            }

                            ?>><?=htmlspecialchars($action->getLabel())?></a>
                            <?php
                        }

                        ?>
                    </td>
                </tr>
                <?php
            }

            ?>
        </tbody>
    </table>
    <?php

    if ($canReplace && $rows !== []) {
        ?>
        <div class="admin-floating-controls">
            <button type="button" class="btn btn-danger js-find-replace-apply" data-scope="selected" disabled>
                Replace selected
            </button>
            <button type="button" class="btn btn-danger js-find-replace-apply" data-scope="all">
                <?=$filtered ? 'Replace matching filters' : 'Replace all'?>
            </button>
        </div>
        <?php
    }

    echo form_close();

    if ($pages > 1) {
        ?>
        <p>
            Page <?=htmlspecialchars((string) $page)?> of <?=htmlspecialchars((string) $pages)?>
            <?php

            if ($page > 1) {
                $previous = $query;
                $previous['page'] = $page - 1;
                ?>
                <a href="<?=htmlspecialchars(FindReplace::url('results') . '?' . http_build_query($previous))?>">Previous</a>
                <?php
            }

            if ($page < $pages) {
                $next = $query;
                $next['page'] = $page + 1;
                ?>
                <a href="<?=htmlspecialchars(FindReplace::url('results') . '?' . http_build_query($next))?>">Next</a>
                <?php
            }

            ?>
        </p>
        <?php
    }
}

?>
