<?php
/**
 * Display the competency templates box for an IDP
 * todo: move this into plan/lib.php
 *
 * @param db_record $revision from the idp_revision table
 * @param array $competencytemplates
 * @param boolean $editingon
 */
function print_idp_competency_templates_view( $revision, $competencytemplates, $editingon=false ){

global $CFG;

// Display competencies
print_heading(get_string('competencytemplates', 'competency'));
$str_remove = get_string('remove');

?>
<table id="list-idp-competencytemplates" class="generalbox planitems boxaligncenter">
<tr>
    <th class="framework" scope="col">
        <?php echo get_string('framework', 'local') ?>
    </th>

    <th class="name" scope="col">
        <?php echo get_string('template', 'local'); ?>
    </th>

    <th class="status" scope="col">
        <?php echo get_string('status', 'idp') ?>
    </th>

    <th class="duedate" scope="col">
        <?php echo get_string('duedate', 'idp') ?>
    </th>

<?php
    if ($editingon) {
?>
    <th class="options" scope="col">
        <?php echo get_string('options', 'competency') ?>
    </th>
<?php
    }
?>
</tr>
<?php

// # cols varies
$cols = $editingon ? 3 : 2;

$rowcount=0;

if ($competencytemplates) {

    foreach ($competencytemplates as $competency) {

        echo '<tr class=r'.$rowcount.'>';
        echo "<td><a href=\"{$CFG->wwwroot}/hierarchy/index.php?type=competency&frameworkid={$competency->fid}\">{$competency->framework}</a></td>";
        echo "<td><a href=\"{$CFG->wwwroot}/hierarchy/type/competency/template/view.php?id={$competency->id}\">{$competency->fullname}</a></td>";
        echo "<td></td>";
        echo "<td></td>";

        if ($editingon) {
            echo '<td class="options">';

            echo "<a href=\"{$CFG->wwwroot}/competency/idp/remove.php?id={$competency->id}\" title=\"$str_remove\">".
                 "<img src=\"{$CFG->pixpath}/t/delete.gif\" class=\"iconsmall\" alt=\"$str_remove\" /></a>";

            echo '</td>';
        }

        echo '</tr>';
        $rowcount = ($rowcount + 1) % 2;
    }

} else {
    echo '<tr class="noitems"><td colspan="'.$cols.'"><i>'.get_string('emptyplancompetencytemplates', 'idp').'</i></td></tr>';
}

    echo '</table>';

    // Add competencies button
    if ($editingon) {

?>
<table class="generalbox planbuttons boxaligncenter">
    <tr class="noitems" colspan="<?php echo $cols ?>">
        <td>
            <div class="singlebutton">
            <form action="<?php echo $CFG->wwwroot ?>/hierarchy/type/competency/idp/add-template.php?id=<?php echo $revision->id ?>" method="get">
            <input type="submit" id="show-idpcompetencytemplate-dialog" value="<?php echo get_string('addfromframeworks', 'idp') ?>" />
            <input type="submit" id="" value="<?php echo get_string('addfrompositions', 'idp') ?>" />
            </form></div>
        </td>
    </tr>
</table>
<script type="text/javascript">
<!-- //
var idp_competencytemplate_row_count = <?php echo $rowcount ?>
// -->
</script>
<?php

    }
}
?>