<?php

require_once '../common/util.php';
require_once '../common/game_config.php';
require_once '../common/auth.php';
require_once '../common/database.php';

add_post_headers();

try {
    $user = userdata_from_post();

    $db = open_db();

    $db->beginTransaction();

    $stmt = $db->prepare("SELECT player_id, balance, email" .
      "  FROM player" .
      "  WHERE google_user_id=:google_user_id");
    $stmt->bindParam(':google_user_id', $user['google_user_id']);

    checked_execute_query($stmt);

    $row = $stmt->fetch(PDO::FETCH_NUM);
    assert_or_die($row, HttpCode::NOT_FOUND, "Player not found.");

    $player_id = $row[0];
    $balance = $row[1];

    // Update email if different.
    if ($user['email'] != $row[2]) {
        $stmt = $db->prepare(
          "UPDATE player" .
          "  SET email=:email" .
          "  WHERE google_user_id=:google_user_id");
          $stmt->bindParam(':email', $user['email']);
          $stmt->bindParam(':google_user_id', $user['google_user_id']);
        checked_execute_query($stmt);
    }

    $stmt = $db->prepare(
        "SELECT game_id" .
        "  FROM game_picked_numbers, current_game" .
        "  WHERE game_picked_numbers.game_type_id=:game_type_id" .
        "   AND player_id=:player_id" .
        "   AND game_picked_numbers.game_type_id=current_game.game_type_id"
    );

    $stmt->bindParam('player_id', $player_id);

    $games = array();
    foreach ($GAME_TYPES as $game_type_id => $v) {
        $stmt->bindParam('game_type_id', $game_type_id);
        checked_execute_query($stmt);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row !== false) {
            $game_id = $row[0];
            $games[$game_type_id] = $game_id;
        }
    }

    http_response_code(HttpCode::OK);
    print json_encode(array('balance' => $balance, 'games' => $games));
} catch(Exception $exc){
    http_response_code(HttpCode::SERVICE_UNAVAILABLE);
    die(
        json_encode(
            array(
            "error" => "Can't execute query.", "message" => $exc->getMessage())
        )
    );
}

?>
