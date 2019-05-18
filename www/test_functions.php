<?php

require_once 'util.php';
require_once 'database.php';
require_once 'game_config.php';

function check($condition, $msg) {
  if (!$condition) {
    print("CHECK FAILED: $msg<br>");
    debug_print_backtrace();
    print("<br>");
    die();
  }
}

function check_table_size($db, $table, $expected) {
  $actual = db_table_size($db, $table);
  if ($actual != $expected) {
    die("Table $table size $actual instead of $expected.");
  }
}

function check_table_current_game_is_initial_state($db) {
  global $GAME_TYPES;
  check_table_size($db, "current_game", 3);
  foreach($GAME_TYPES as $k => $v) {
    $stmt = $db->query(
      "SELECT num_players, winner_number, game_id FROM current_game" .
      "  WHERE game_type_id=$k");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : FALSE;
    if (!$row || $row[0] != 0 || !is_null($row[1]) || !is_null($row[2])) {
      print_r($row);
      die("Invalid current_game row: $k");
    }
  }
}

function progress($msg) {
  echo "$msg<br>";
  flush();
  ob_flush();
}

function test_curl_request($method, $api, $data) {
  $data[DbConfig::USE_TEST_DB_FIELD] = 1;
  return curl_request("{$_SERVER['HTTP_HOST']}/api/{$api}.php", $method, $data);
}

function check_balance($expected, $actual, $msg) {
  if (!is_numeric($expected)) {
    die("check_balance: expected value ($expected) is not numberic");
  }
  if (!is_numeric($actual)) {
    die("CHECK FAILED (check_balance): actual value ($actual)" .
      " is not numeric ($msg)");
  }
  if (round(floatval($actual), 2) != round(floatval($expected), 2)) {
    die("CHECK FAILED (check_balance): actual ($actual) != expected" .
      " ($expected) ($msg)");
  }
}

function check_player_balance($email, $expected, $test_name, $games) {
  $r = test_curl_request('GET', 'get-player', array('email' => $email));
  check($r['response'] == HttpCode::OK, $test_name);
  $jr = json_decode($r['transfer'], TRUE);
  check_balance($expected, $jr['balance'], $test_name);
  check($games == $jr['games'], $test_name . '/get-player games mismatch');
}

?>
