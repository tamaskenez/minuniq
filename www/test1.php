<?php

require_once 'util.php';
require_once 'database.php';
require_once 'game_config.php';
require_once 'test_functions.php';

header( 'Content-type: text/html; charset=utf-8' );

function progress($msg) {
  echo "$msg<br>";
  flush();
  ob_flush();
}

progress("-- Creating empty test database.");
create_empty_test_db();

progress("-- Initialize database.");
$db = open_db(TRUE);
run_db_init_script($db);


progress("-- Verify empty database.");
check_table_size($db, "game_history", 0);
check_table_size($db, "game_picked_numbers", 0);
check_table_size($db, "player", 0);
check_table_current_game_is_initial_state($db);

progress("Test done.");

?>
