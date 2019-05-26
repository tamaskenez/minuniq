<?php

require_once '../common/util.php';
require_once '../common/game_config.php';
require_once '../common/database.php';

add_get_headers();

$game_id_string = nonempty_get_arg('game-id');
$game_id = intval($game_id_string);

assert_or_die(
    is_numeric($game_id_string) || $game_id_string != $game_id,
    HttpCode::BAD_REQUEST, "Field 'game-id' is not valid."
);
$known_num_players = nonempty_get_arg_or_null('known-num-players');

try {
    $db = open_db();

    $waiting_since = null;
    $LONG_POLL_TIMEOUT_SEC = 10;

    while(true) {
        $stmt = $db->prepare(
            "SELECT game_type_id, num_players" .
            "  FROM current_game" .
            "  WHERE game_id=:game_id"
        );
        $stmt->bindParam(':game_id', $game_id);
        checked_execute_query($stmt);
        $row = $stmt->fetch(PDO::FETCH_NUM);

        if ($row) {
            $game_type_id = $row[0];
            $num_players = $row[1];
            $winner_number = null;
            $finished = false;
            $winner_email = null;
        } else {
            // Try as finished game.
            $stmt = $db->prepare(
                "SELECT game_type_id, finished, winner_player_email, winner_number" .
                "  FROM game_history" .
                "  WHERE game_id=:game_id"
            );
            $stmt->bindParam(':game_id', $game_id);
            checked_execute_query($stmt);
            $row = $stmt->fetch(PDO::FETCH_NUM);
            assert_or_die($row, HttpCode::NOT_FOUND, "Game not found.");
            $game_type_id = $row[0];
            $finished = $row[1] ? true : false;
            assert_or_die(
                $finished,
                HttpCode::INTERNAL_SERVER_ERROR,
                "Unfinished game not found in current games."
            );
            $winner_email = $row[2];
            $winner_number = $row[3];
            $num_players = $GAME_TYPES[$game_type_id]['num-players'];
        }

        // Do we need to wait until something interesting happens?
        if (!$finished && !is_null($known_num_players)
          && $num_players <= $known_num_players) {
            if (is_null($waiting_since)) {
                $waiting_since = time();
            } else {
                if (time() - $waiting_since > $LONG_POLL_TIMEOUT_SEC) {
                    error_log("timeout");
                    break;
                }
            }
            error_log("sleeping");
            sleep(2);
            continue;
        }

        break;
    }

    $response = array(
      "game-type-id" => $game_type_id,
      "num-players" => $num_players,
      "finished" => $finished
    );

    if (!is_null($winner_email)) {
        $response['winner-email'] = $winner_email;
        $response['winner-number'] = $winner_number;
        assert_or_die(
            !is_null($winner_number),
            HttpCode::INTERNAL_SERVER_ERROR,
            "Game has winner but no winner number."
        );
    }

    print json_encode($response);
    http_response_code(HttpCode::OK);
} catch(Exception $exc){
    http_response_code(HttpCode::SERVICE_UNAVAILABLE);
    die(
        json_encode(
            array(
            "error" => "Can't execute query.", "message" => $exc->getMessage())
        )
    );
}

// test
// post
// invalid player
// valid player

?>
