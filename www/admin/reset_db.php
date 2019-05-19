<?php

require_once '../util.php';
require_once '../database.php';

drop_and_init_db(FALSE);
$db = open_db_1(FALSE);
run_db_init_script($db);

print "Done.";

?>
