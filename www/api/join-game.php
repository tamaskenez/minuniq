<?php

require '../post_prelude.php';
require_once '../util.php';

// Validate arguments.
$email = htmlspecialchars(strip_tags($_POST['email']));
$game_type_id_string = htmlspecialchars(strip_tags($_POST['game-type-id']));
$game_type_id = intval($game_type_id_string);
$picked_number_string = htmlspecialchars(strip_tags($_POST['picked-number']));
$picked_number = intval($picked_number_string);

assert_or_die(!empty($email), HttpCode::BAD_REQUEST, "Field 'email' is empty.");
assert_or_die(
  is_int($game_type_id_string) && $array_key_exists($game_type_id, $game_types),
  HttpCode(BAD_REQUEST), "Field 'game-type-id' is not valid.");
assert_or_die(is_valid_picked_number($picked_number),
  HttpCode(BAD_REQUEST), "Field 'picked-number' is not valid.");

require_once '../database.php';
require_once '../game_config.php';

$db = open_db();

try {
  $db->beginTransaction();

  // Lock player's row and verify if they're allowed to participate
  $player = select_player_for_update_or_null($db, $email);
  assert_or_die(is_null($player), HttpCode::NOT_FOUND, "Player not found.");

  $new_balance = round($player['balance'] - $BET_AMOUNT, 2);
  assert_or_die($new_balance > 0,
    HttpCode::PAYMENT_REQUIRED, "Insufficient balance.");

  $player_id = $player['player_id'];

  // Check if player is already participating in this game.
  $stmt = $db->prepare(
    "SELECT COUNT(1) FROM game_picked_numbers" .
    "  WHERE game_type_id=:game_type_id AND player_id=:player_id");
  $stmt->bindParam(':game_type_id', $game_type_id);
  $stmt->bindParam(':player_id', $player_id);
  checked_execute_query($stmt);
  $row = $stmt->fetch(PDO::FETCH_NUM);
  assert_or_die($row,
    HttpCode::SERVICE_UNAVAILABLE, "Can't determine if player is in game.");
  $player_in_game = $row[0] != 0;
  assert_or_die(!$player_in_game,
    HttpCode::BAD_REQUEST, "Player is already participating in game.");

  // Lock and retrieve current game row.
  $stmt = $db->prepare(
    "SELECT num_players, winner_number, game_id" .
    "  FROM current_game" .
    "  WHERE game_type_id=:game_type_id" .
    "  FOR UPDATE");
  $stmt->bindParam(':game_type_id', $game_type_id);

  checked_execute_query($stmt);

  $row = $stmt->fetch(PDO::FETCH_NUM);
  assert_or_die($row, HttpCode::INTERNAL_SERVER_ERROR,
    "Row not found in table 'games'.");

  assert_or_die(is_int($row[0]),
    HttpCode::INTERNAL_SERVER_ERROR, "Field 'num_players' is not an integer.");
  $old_num_players = intval($row[0]);
  $old_winner_number = $row[1];
  $game_id = $row[2];

  // Record picked number.
  $stmt = $db->prepare(
    "INSERT INTO game_picked_numbers (game_type_id, player_id, picked_number)" .
    "  VALUES (:game_type_id, :player_id, :picked_number)");
  $stmt->bindParam(':game_type_id', $game_type_id);
  $stmt->bindParam(':player_id', $player_id]);
  $stmt->bindParam(':picked_number', $picked_number);
  checked_execute_query($stmt);

  // Update player.
  $stmt = $db->prepare(
    "UPDATE player" .
    "  SET balance=:new_balance" .
    "  WHERE player_id=:player_id");
  $stmt->bindParam(':player_id', $player_id);
  $stmt->bindParam(':new_balance', $new_balance);
  checked_execute_query($stmt);

  // Calculate new winner number.
  if ($old_num_players == 0) {
    // First player in this game.
    assert_or_die(is_null($game_id),
      HttpCode::INTERNAL_SERVER_ERROR, "Exisiting game has 0 players.");
    $new_winner_number = $picked_number;
    $stmt = $db->prepare(
      "INSERT INTO game_history (game_type_id)" .
      "  VALUES (:game_type_id)";
    $stmt->bindParam(':game_type_id', $game_type_id);
    checked_execute_query($stmt);

    $game_id = $db->lastInsertId();
    assert_or_die(!is_null($game_id) && $game_id != 0,
      HttpCode::INTERNAL_SERVER_ERROR,
      "Last insert id for table 'game_history' is invalid.");
  } else {
    assert_or_die(!is_null($game_id),
      HttpCode::INTERNAL_SERVER_ERROR,
      "Existing game with players has no game_id.");

    // Recalculate winner number only if it's NULL or greater then current
    // picked number,  otherwise the current winner number won't change.
    if (is_null($old_winner_number) || $picked_number <= $old_winner_number) {
      // Select the minimum of unique numbers in the current game.
      $stmt = $db->prepare(
        "SELECT MIN(picked_number) FROM" .
        "  (SELECT picked_number" .
        "   FROM game_numbers" .
        "   WHERE game_type_id=:game_type_id" .
        "   GROUP BY picked_number" .
        "   HAVING COUNT(1) = 1" .
        "   ) AS inner_query");
        $stmt->bindParam(':email', $email);

      checked_execute_query($stmt);
      $row = $stmt->fetch(PDO::FETCH_NUM);
      assert_or_die($row,
        HttpCode::INTERNAL_SERVER_ERROR, "Minimum query returned no rows.");
      if (is_null($row[0])) {
        $new_winner_number = NULL;
      } else {
        $new_winner_number = intval($row[0]);
      }
    } else {
      $new_winner_number = $old_winner_number;
    }
  }

  if ($new_winner_number !== $winner_number) {
    // TODO: Winner number changed.
  }

  // Update current game row.
  $new_num_players = $old_num_players + 1;
  $maybe_update_game_id = $old_num_players == 0 ? ", game_id=:game_id" : "";
  $stmt = $db->prepare(
    "UPDATE games" .
    "  SET num_players=:new_num_players, winner_number=:new_winner_number" .
    "  $maybe_update_game_id" .
    "  WHERE game_type_id=:game_type_id");
  $stmt->bindParam(':new_num_players', $new_num_players);
  $stmt->bindParam(':new_winner_number', $new_winner_number);
  if ($old_num_players == 0) {
    $stmt->bindParam(':game_id', $game_id);
  }
  checked_execute_query($stmt);

  $total_num_players = $game_types[$game_type_id][$new_num_players];

  if ($new_num_players >= $total_num_players) {
    // This game has ended, update all tables.

    assert_or_die($total_num_players == $new_num_players,
      HttpCode::INTERNAL_SERVER_ERROR, "Too many players in game.");


    // Calculate winner number and player.
    if (is_null($new_winner_number)) {
      $winner_player_email = NULL;
    } else if ($new_winner_number == $picked_number) {
      $winner_player_email = $email;
    } else {
      $stmt = $db->prepare(
        "SELECT email FROM player, game_picked_numbers" .
        "  WHERE game_type_id=:game_type_id AND picked_number=:picked_number" .
        "    AND player.player_id=game_picked_numbers.player_id);
      $stmt->bindParam(':game_type_id', $game_type_id);
      $stmt->bindParam(':picked_number', $new_winner_number);
      checked_execute_query($stmt);
      $row = $stmt->fetch(PDO::FETCH_NUM);
      assert_or_die(!$row,
        HttpCode::INTERNAL_SERVER_ERROR, "Winner email not found.");
        $winner_player_email = $row[0];
    }

    // Reset game_picked_numbers.
    $stmt = $db->prepare(
      "DELETE FROM game_picked_numbers" .
      "  WHERE game_type_id=:game_type_id");
    $stmt->bindParam(':game_type_id', $game_type_id);
    checked_execute_query($stmt);

    // Update game history.
    $stmt = $db->prepare(
      "UPDATE game_history" .
      "  SET finished=:1, winner_player_email=:winner_player_email," .
      "    winner_number=:winner_number" .
      "  WHERE game_id=:game_id");
    $stmt->bindParam(':winner_player_email', $winner_player_email);
    $stmt->bindParam(':winner_number', $new_winner_number);
    $stmt->bindParam(':game_id', $game_id);
    checked_execute_query($stmt);

    // Reset current game.
    $stmt = $db->prepare(
      "UPDATE current_game" .
      "  SET num_players=0, winner_number=NULL, game_id=NULL" .
      "  WHERE game_type_id=:game_type_id");
    $stmt->bindParam(':game_type_id', $game_type_id);
    checked_execute_query($stmt);

    // Update player.
    $winner_balance = $new_balance + $BET_AMOUNT * $total_num_players;
    $stmt = $db->prepare(
      "UPDATE player" .
      "  SET balance=:balance" .
      "  WHERE player_id=:player_id");
    $stmt->bindParam(':player_id', $player_id);
    $stmt->bindParam(':balance', $winner_balance);
    checked_execute_query($stmt);
  } // If game has ended.

  $db->commit();
  http_response_code(HttpCode::OK);
  print json_encode(array("game_id" => $game_id));
} catch(Exception $exc){
  http_response_code(503);
  die(json_encode(
    array("error" => "Can't execute query.", "message" => $exc->getMessage())));
}

?>
