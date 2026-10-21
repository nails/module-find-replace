<?php

/** @var string|null $queueWarning */

$apiUrl = siteUrl('api/find-replace/search');

?>
<div id="find-replace" data-api="<?=htmlspecialchars($apiUrl)?>">
    <?php

    if (is_string($queueWarning) && $queueWarning !== '') {
        ?>
        <div class="alert alert-warning"><?=htmlspecialchars($queueWarning)?></div>
        <?php
    }

    ?>
    <p>Search saved content, then replace matches from the results screen. A search and its rows are kept or deleted together.</p>
    <div id="find-replace-alert" class="alert" hidden></div>
    <?=form_open(null, 'id="find-replace-form"')?>
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
    </fieldset>
    <fieldset data-collapse="closed">
        <legend>Advanced</legend>
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

    <fieldset class="find-replace-searches">
        <legend>My searches</legend>
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Term</th>
                    <th class="find-replace-status">Status</th>
                    <th class="find-replace-progress">Progress</th>
                    <th class="find-replace-results">Results</th>
                    <th class="find-replace-last-run">Last run</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody id="find-replace-searches">
                <tr>
                    <td colspan="6" class="no-data">Loading searches…</td>
                </tr>
            </tbody>
        </table>
    </fieldset>
</div>
