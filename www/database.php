<?php

function open_db() {
  $db_host = "localhost";
  $db_name = "minuniq";
  $db_username = "root";
  $db_password = "root";

  try {
    return new PDO("mysql:host={$db_host};dbname={$db_name}", $db_username, $db_password);
  } catch(Exception $exc) {
    http_response_code(503);
    die(json_encode(array("error" => "Can't connect to database.", "message" => $exc->getMessage())));
  }
}

function select_player_for_update_or_null($db, $email) {
  $stmt = $db->prepare("SELECT player_id, balance, participation FROM player WHERE email=:email FOR UPDATE");
  $stmt->bindParam(':email', $email);

  $r = $stmt->execute();
  assert_or_die($r, HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]);

  $row = $stmt->fetch(PDO::FETCH_NUM);
  if (!$row) {
    return NULL;
  }
  $player_id = $row[0];
  $balance = $row[1];
  $participation = $row[2];

  assert_or_die(is_numeric($balance),
    HttpCode::INTERNAL_SERVER_ERROR, "Player balance is not numeric in database.");
  assert_or_die(is_int($participation),
    HttpCode::INTERNAL_SERVER_ERROR, "Player participation bit mask is not an integer in database.");

  return array(
    'player_id' => $player_id,
    'balance' => floatval($balance),
    'participation' => intval($participation));
}

function select_player_balance_for_update_or_null($db, $email) {
  $bp = select_player_balance_participation_for_update_or_null($db, $email);
  return is_null($bp) ? NULL : $bp['balance'];
}

function checked_execute_query($stmt) {
  $r = $stmt->execute();
  assert_or_die_msg($r, HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", stmt->errorInfo()[2]);
}

?>
