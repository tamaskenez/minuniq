<?php

require_once '../util.php';
require_once '../database.php';
require_once '../game_config.php';
require_once '../test_functions.php';

header( 'Content-type: text/html; charset=utf-8' );

progress("-- Creating empty test database.");
create_empty_test_db();

progress("-- Initialize database.");
$db = open_db_1(TRUE);
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
$r = test_curl_request('POST', 'register-player', array('email' => $email));
check($r['response'] == HttpCode::CREATED,
  "Response should have been CREATED");

check_table_size($db, "player", 1);
$stmt = $db->query('SELECT player_id, email, balance FROM player');
$row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : FALSE;
if (!$row) {
  die("Can't read player row");
}
$player_id = $row[0];
if ($player_id != 1 || $row[1] != $email || $row[2] != 0.0) {
  die("Invalid columns in player row");
}

progress("-- Test top-up-balance.");

$amount = 123.45;
$r = test_curl_request('POST', 'top-up-balance',
  array('email' => $email, 'amount' => $amount));
check($r['response'] == HttpCode::OK,
  "Response should have been OK");
$stmt = $db->query("SELECT balance FROM player WHERE player_id=$player_id");
$row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : FALSE;
if (!$row) {
  die("Can't read player row");
}
if ($row[0] != $amount) {
  die("Invalid amount after top-up.");
}

progress("-- Test delete-player.");
$r = test_curl_request('POST', 'delete-player', array('email' => $email));
check($r['response'] == HttpCode::OK,
  "Response should have been OK");
check_table_size($db, "player", 0);

progress("Test done.");

?>
