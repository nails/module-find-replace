<?php

use Nails\FindReplace\Admin\Controller\FindReplace;
use Nails\FindReplace\Model\Search;
use Nails\FindReplace\Resource;

/** @var list<Resource\Search> $searches */

?>
<p>Search saved content, then replace matches from the results screen. A search and its rows are kept or deleted together.</p>
<?=form_open(FindReplace::url('create'))?>
<fieldset>
    <legend>New search</legend>
    <?=form_field([
        'key' => 'term',
        'label' => 'Find',
        'required' => true,
    ])?>
    <?=form_field([
        'key' => 'replacement',
        'label' => 'Replace with',
    ])?>
    <input type="hidden" name="case_sensitive" value="0">
    <?=form_field_boolean([
        'key' => 'case_sensitive',
        'label' => 'Case sensitive',
        'default' => true,
        'text_on' => 'Yes',
        'text_off' => 'No',
    ])?>
    <?=form_field_textarea([
        'key' => 'include_tables',
        'label' => 'Limit to tables',
        'placeholder' => "One table, table.column, or /regex/ per line\nLeave blank to search every table",
    ])?>
    <?=form_field_textarea([
        'key' => 'exclude_tables',
        'label' => 'Exclude tables',
        'placeholder' => "One table, table.column, or /regex/ per line",
    ])?>
</fieldset>
<div class="admin-floating-controls">
    <button type="submit" class="btn btn-primary">Search</button>
</div>
<?=form_close()?>

<fieldset>
    <legend>My searches</legend>
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>Term</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Results</th>
                <th>Last run</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php

            if ($searches === []) {
                ?>
                <tr>
                    <td colspan="6" class="no-data">No searches yet</td>
                </tr>
                <?php
            }

            foreach ($searches as $search) {
                $id = (int) $search->id;
                $resultsUrl = FindReplace::url('results') . '?id=' . $id;
                ?>
                <tr>
                    <td>
                        <a href="<?=htmlspecialchars($resultsUrl)?>"><?=htmlspecialchars($search->term())?></a>
                    </td>
                    <td><?=htmlspecialchars($search->status())?></td>
                    <td<?php

                    if ($search->isOpen()) {
                        ?> data-find-replace-status data-url="<?=htmlspecialchars(FindReplace::url('status') . '?id=' . $id)?>"<?php
                    }

                    ?>>
                        <?=htmlspecialchars((string) $search->tablesScanned())?>
                        /
                        <?=htmlspecialchars((string) $search->tablesTotal())?>
                    </td>
                    <td><?=htmlspecialchars((string) $search->resultCount())?></td>
                    <td><?=htmlspecialchars($search->lastRunLabel())?></td>
                    <td class="actions">
                        <?=form_open(FindReplace::url('persistent'))?>
                        <input type="hidden" name="id" value="<?=$id?>">
                        <input type="hidden" name="persistent" value="<?=$search->isPersistent() ? '0' : '1'?>">
                        <button type="submit" class="btn btn-sm btn-default">
                            <?=$search->isPersistent() ? 'Stop keeping' : 'Keep'?>
                        </button>
                        <?=form_close()?>
                        <?=form_open(FindReplace::url('rerun'))?>
                        <input type="hidden" name="id" value="<?=$id?>">
                        <button type="submit" class="btn btn-sm btn-default">Run again</button>
                        <?=form_close()?>
                        <?=form_open(FindReplace::url('delete'), [
                            'onsubmit' => "return confirm('Delete this search and its results?');",
                        ])?>
                        <input type="hidden" name="id" value="<?=$id?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        <?=form_close()?>
                    </td>
                </tr>
                <?php

                if ($search->status() === Search::STATUS_FAILED && $search->errorMessage() !== '') {
                    ?>
                    <tr>
                        <td colspan="6"><?=htmlspecialchars($search->errorMessage())?></td>
                    </tr>
                    <?php
                }
            }

            ?>
        </tbody>
    </table>
</fieldset>
