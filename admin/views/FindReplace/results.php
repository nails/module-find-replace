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

$resultsUrl = FindReplace::url('results') . '?' . http_build_query($query);
$exportUrl = FindReplace::url('export') . '?' . http_build_query($query);
$filtered = $facets !== [];

?>
<p>
    <strong><?=htmlspecialchars($search->term())?></strong>
    <?php

    if ($search->replacement() !== '') {
        ?>
        → <strong><?=htmlspecialchars($search->replacement())?></strong>
        <?php
    }

    ?>
    <span class="text-muted"><?=htmlspecialchars($search->status())?></span>
</p>

<?php

if ($search->isOpen()) {
    ?>
    <p data-find-replace-status data-url="<?=htmlspecialchars(siteUrl('api/find-replace/search/' . $id))?>">
        Scanning tables:
        <span class="find-replace-progress">
            <?=htmlspecialchars((string) $search->tablesScanned())?>
            /
            <?=htmlspecialchars((string) $search->tablesTotal())?>
        </span>
    </p>
    <?php
}

if ($search->status() === Search::STATUS_FAILED) {
    ?>
    <div class="alert alert-danger"><?=htmlspecialchars($search->errorMessage())?></div>
    <?=form_open(FindReplace::url('rerun'))?>
    <input type="hidden" name="id" value="<?=$id?>">
    <button type="submit" class="btn btn-primary">Run again</button>
    <?=form_close()?>
    <?php
}

if ($search->status() === Search::STATUS_COMPLETE) {
    ?>
    <p>
        <?=htmlspecialchars((string) $total)?>
        result<?=$total === 1 ? '' : 's'?>
        <?php

        if ($filtered) {
            ?>
            with the current filters.
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
        <thead>
            <tr>
                <th><input type="checkbox" class="js-toggle-all" aria-label="Select all"></th>
                <th>Record</th>
                <th>Column</th>
                <th>Match</th>
                <th>Replacement</th>
                <th></th>
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
