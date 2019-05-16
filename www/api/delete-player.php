<?php

// delete-player is also POST request because in PHP the DELETE requests have
// inferior support (content parsing).
require '../post_prelude.php';
require_once '../util.php';

$email = htmlspecialchars(strip_tags($_POST['email']));

assert_or_die(!empty($email), HttpCode::BAD_REQUEST, "Field 'email' is empty.");

require_once '../database.php';

$db = open_db($_POST['testing']);

try {

  $db->beginTransaction();
  $player = select_player_for_update_or_null($db, $email);
  assert_or_die(!is_null($player),
    HttpCode::NOT_FOUND, "Player not found.");

  // Check if player is playing.
  $stmt = $db->prepare(
    "SELECT COUNT(1) FROM game_picked_numbers" .
    "  WHERE player_id=:player_id");
  $stmt->bindParam(':player_id', $player_id);
  checked_execute_query($stmt);

  $row = $stmt->fetch(PDO::FETCH_NUM);
  assert_or_die($row,
    HttpCode::SERVICE_UNAVAILABLE, "Can't count player's picked numbers.");
  assert_or_die($row[0] == 0,
    HttpCode::BAD_REQUEST, "Can't delete a player with an active game.");

  $stmt = $db->prepare("DELETE FROM player WHERE player_id=:player_id");
  $stmt->bindParam(':player_id', $player['player_id']);
  checked_execute_query($stmt);
  $db->commit();
  http_response_code(HttpCode::OK);
} catch(Exception $exc){
  http_response_code(HttpCode::SERVICE_UNAVAILABLE);
  die(json_encode(array("error" => "Can't execute query.", "message" => $exc->getMessage())));
}

?>
