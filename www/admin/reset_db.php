<?php

require_once '../common/util.php';
require_once '../common/database.php';

drop_and_init_db(FALSE);
$db = open_db_1(FALSE);
run_db_init_script($db);

print "Done.";

?>
