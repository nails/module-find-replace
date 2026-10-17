<?php

use Nails\FindReplace\Admin\Controller\FindReplace;
use Nails\FindReplace\Factory\ChangeRecord;

/** @var array<string, list<ChangeRecord>> $runs */
/** @var bool $canReplace */

?>
<p>Changes you applied from Find &amp; Replace. Command-line replacements are not listed here.</p>
<?php

if ($runs === []) {
    ?>
    <p class="no-data">No changes yet</p>
    <?php
}

foreach ($runs as $runId => $rows) {
    ?>
    <fieldset>
        <legend>
            Run <?=htmlspecialchars($runId)?>
            <?php

            if ($canReplace) {
                echo form_open(FindReplace::url('revert'), ['class' => 'form-inline']);
                ?>
                <input type="hidden" name="run_id" value="<?=htmlspecialchars($runId)?>">
                <button type="submit" class="btn btn-sm btn-default">Revert run</button>
                <?=form_close()?>
                <?php
            }

            ?>
        </legend>
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Term</th>
                    <th>Replacement</th>
                    <th>Record</th>
                    <th>Column</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php

                foreach ($rows as $row) {
                    ?>
                    <tr>
                        <td><?=htmlspecialchars((string) $row->getCreated())?></td>
                        <td><?=htmlspecialchars($row->getTerm())?></td>
                        <td><?=htmlspecialchars($row->getReplacement())?></td>
                        <td>
                            <code><?=htmlspecialchars($row->getTable())?></code>
                            <?=htmlspecialchars($row->getPrimaryKey())?>
                        </td>
                        <td><code><?=htmlspecialchars($row->getColumn())?></code></td>
                        <td class="actions">
                            <?php

                            if ($canReplace) {
                                echo form_open(FindReplace::url('revert'));
                                ?>
                                <input type="hidden" name="change_id" value="<?=$row->getId()?>">
                                <button type="submit" class="btn btn-sm btn-default">Revert</button>
                                <?=form_close()?>
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
    </fieldset>
    <?php
}
