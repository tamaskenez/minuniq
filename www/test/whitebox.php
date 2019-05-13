<?php

require_once '../util.php';
require_once '../database.php';
require_once '../game_config.php';
require_once '../test_functions.php';

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

$http_host = $_SERVER['HTTP_HOST'];
progress("API smoke test, http_host = $http_host");

progress("-- Test register-player.");

$email = 'example@email.com';
$r = curl_request("$http_host/api/register-player.php", 'POST',
  array('email' => $email, 'testing' => 1));
check($r['response'] == HttpCode::CREATED,
  "Response should have been CREATED");

check_table_size($db, "player", 1);
$stmt = $db->query('SELECT player_id, email, balance FROM player');
$row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : FALSE;
if (!$row) {
  die("Can't read player row");
}
if ($row[0] != 1 || $row[1] != $email || $row[2] != 0.0) {
  die("Invalid columns in player row");
}

progress("-- Test top-up-balance TODO.");
progress("-- Test delete-player TODO.");

progress("Test done.");

?>
