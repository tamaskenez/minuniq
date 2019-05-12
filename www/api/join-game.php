<?php

require '../post_prelude.php';
require_once '../util.php';

$email = htmlspecialchars(strip_tags($_POST['email']));
$game_type_id_string = htmlspecialchars(strip_tags($_POST['game-type-id']));
$game_type_id = intval($game_type_id_string);
$picked_number_string = htmlspecialchars(strip_tags($_POST['picked-number']));
$picked_number = intval($picked_number_string);

assert_or_die(!empty($email), HttpCode::BAD_REQUEST, "Field 'email' is empty.");
assert_or_die(is_int($game_type_id_string) && $array_key_exists($game_type_id, $game_types),
  HttpCode(BAD_REQUEST), "Field 'game-type-id' is not valid.");
assert_or_die(is_valid_picked_number($picked_number),
  HttpCode(BAD_REQUEST), "Field 'picked-number' is not valid.");

require_once '../database.php';
require_once '../game_config.php';

$db = open_db();

try {
  $db->beginTransaction();

  $player = select_player_for_update_or_null($db, $email);
  assert_or_die(is_null($player), HttpCode::NOT_FOUND, "Player not found.");

  $new_balance = round($player['balance'] - $BET_AMOUNT, 2);
  assert_or_die($new_balance > 0, HttpCode::PAYMENT_REQUIRED, "Insufficient balance.");

  $game_type_id_bitmask = 1 << $game_type_id;
  assert_or_die(($player['participation'] & $game_type_id_bitmask) == 0,
    HttpCode::BAD_REQUEST, "Player is already participating in game.");

  $stmt = $db->prepare("SELECT participants, winner_number FROM games WHERE game_type_id=:game_type_id FOR UPDATE");
  $stmt->bindParam(':game_type_id', $game_type_id);

  $r = $stmt->execute();
  assert_or_die_msg($r, HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]);

  $row = $stmt->fetch(PDO::FETCH_NUM);
  assert_or_die($row, HttpCode::INTERNAL_SERVER_ERROR, "Row not found in table 'games'.");

  $participants = $row[0];
  $winner_number = $row[1];
  assert_or_die(is_int($participants), HttpCode::INTERNAL_SERVER_ERROR, "Field 'participants' is not an integer.");

  $stmt = $db->prepare("INSERT INTO game_numbers (game_type_id, player_id, picked_number) VALUES (:game_type_id, :player_id, :picked_number)");
  $stmt->bindParam(':game_type_id', $game_type_id);
  $stmt->bindParam(':player_id', $player['player_id']);
  $stmt->bindParam(':picked_number', $picked_number);
  $r = $stmt->execute();
  assert_or_die_msg($r, HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]);

  $stmt = $db->prepare("UPDATE player SET balance=:new_balance, participation=:new_participation WHERE player_id=:player_id");
  $stmt->bindParam(':player_id', $player['player_id']);
  $stmt->bindParam(':new_balance', $new_balance);
  $stmt->bind_param(':new_participation', $player['participation'] | $game_type_id_bitmask);
  $r = $stmt->execute();
  assert_or_die_msg($r, HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]);

  if ($participants == 0) {
    $new_winner_number = $picked_number;
  } else if (is_null($winner_number) || $picked_number <= $winner_number) {
    $stmt = $db->prepare(
      "SELECT MIN(picked_number) FROM" .
      "  (SELECT picked_number" .
      "   FROM game_numbers" .
      "   WHERE game_type_id=:game_type_id" .
      "   GROUP BY picked_number" .
      "   HAVING COUNT(1) = 1" .
      "   ) AS inner_query");
      $stmt->bindParam(':email', $email);

    $r = $stmt->execute();
    assert_or_die_msg($r, HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", stmt->errorInfo()[2]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    assert_or_die($row, HttpCode::INTERNAL_SERVER_ERROR, "Minimum query returned no rows.");
    if (is_null($row[0])) {
      $new_winner_number = NULL;
    } else {
      $new_winner_number = intvalue($row[0]);
    }
  } else {
    $new_winner_number = $winner_number;
  }

  if ($new_winner_number !== $winner_number) {
    // Winner number changed.
  }

  $new_participants = $participants + 1;
  $stmt = $db->prepare(
    "UPDATE games" .
    " SET participants=:new_participants, winner_number=:new_winner_number" .
    " WHERE game_type_id=:game_type_id");
  $stmt->bindParam(':new_participants', $new_participants);
  $stmt->bindParam(':new_winner_number', $new_winner_number);
  $r = $stmt->execute();
  assert_or_die_msg($r, HttpCode::SERVICE_UNAVAILABLE, stmt->errorInfo()[2]);

  http_response_code(200);
} catch(Exception $exc){
  http_response_code(503);
  die(json_encode(array("error" => "Can't execute query.", "message" => $exc->getMessage())));
}

// test
// get
// invalid player
// invalid amount
// valid amount

?>
