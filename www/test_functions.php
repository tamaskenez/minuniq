<?php

require_once 'util.php';
require_once 'database.php';
require_once 'game_config.php';

function check($condition, $msg) {
  if (!$condition) {
    die($msg);
  }
}

function check_table_size($db, $table, $expected) {
  $actual = db_table_size($db, $table);
  if ($actual != $expected) {
    die("Table $table size $actual instead of $expected.");
  }
}

function check_table_current_game_is_initial_state($db) {
  check_table_size($db, "current_game", 3);
  foreach($game_types as $k => $v) {
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

?>
