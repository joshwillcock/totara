<?php
@raise_memory_limit('496M');
@ini_set('max_execution_time','3000');
print "Loading data for table 'dashb_instance'<br>";
$items = array(array('id' => '1','dashb_id' => '1','userid' => '0','cols' => '3','colwidth' => '290',),
array('id' => '2','dashb_id' => '2','userid' => '0','cols' => '3','colwidth' => '290',),
array('id' => '3','dashb_id' => '2','userid' => '1920','cols' => '3','colwidth' => '290',),
);
print "\n";print "Inserting ".count($items)." records<br />\n";
$i=1;
foreach($items as $item) {
    if(get_field('dashb_instance', 'id', 'id', $item['id'])) {
        print "Record with id of {$item['id']} already exists!<br>\n";
        continue;
    }
    $newid = insert_record('dashb_instance',(object) $item);
    if($newid != $item['id']) {
        if(!set_field('dashb_instance', 'id', $item['id'], 'id', $newid)) {
            print "Could not change id from $newid to {$item['id']}<br>\n";
            continue;
        }
    }
    // record the highest id in the table
    $maxid = get_field_sql('SELECT '.sql_max('id').' FROM '.$CFG->prefix.'dashb_instance');
    // make sure sequence is higher than highest ID
    bump_sequence('dashb_instance', $CFG->prefix, $maxid);
    // print output
    // 1 dot per 10 inserts
    if($i%10==0) {
        print ".";
        flush();
    }
    // new line every 200 dots
    if($i%2000==0) {
        print $i." <br>";
    }
    $i++;
}
print "<br>";

set_config("guestloginbutton", 0);
set_config("langmenu", 0);
set_config("forcelogin", 1);
        