<?php

require '../common/post_prelude.php';
require_once '../common/util.php';
require_once '../common/game_config.php';
require_once '../common/database.php';
require_once '../common/game_config.php';
require_once '../common/auth.php';

// Validate arguments.

$game_type_id_string = nonempty_post_arg('game-type-id');
$game_type_id = intval($game_type_id_string);
$picked_number_string = nonempty_post_arg('picked-number');
$picked_number = intval($picked_number_string);

assert_or_die(
    is_numeric($game_type_id_string) && array_key_exists($game_type_id, $GAME_TYPES),
    HttpCode::BAD_REQUEST, "Field 'game-type-id' is not valid."
);
assert_or_die(
    is_valid_picked_number($picked_number),
    HttpCode::BAD_REQUEST, "Field 'picked-number' is not valid."
);

try {
    $user = userdata_from_post();

    $db = open_db();

    $db->beginTransaction();

    // Lock player's row and verify if they're allowed to participate
    $player = select_player_for_update_or_null($db, $user);
    assert_or_die(!is_null($player), HttpCode::NOT_FOUND, "Player not found.");

    $new_balance = round($player['balance'] - $BET_AMOUNT, 2);
    assert_or_die(
        $new_balance > 0,
        HttpCode::PAYMENT_REQUIRED, "Insufficient balance."
    );

    $player_id = $player['player_id'];

    // Check if player is already participating in this game.
    $stmt = $db->prepare(
        "SELECT COUNT(1) FROM game_picked_numbers" .
        "  WHERE game_type_id=:game_type_id AND player_id=:player_id"
    );
    $stmt->bindParam(':game_type_id', $game_type_id);
    $stmt->bindParam(':player_id', $player_id);
    checked_execute_query($stmt);
    $row = $stmt->fetch(PDO::FETCH_NUM);

    assert_or_die(
        $row,
        HttpCode::SERVICE_UNAVAILABLE, "Can't determine if player is in game."
    );
    $player_in_game = $row[0] != 0;
    assert_or_die(
        !$player_in_game,
        HttpCode::BAD_REQUEST, "Player is already participating in game."
    );

    // Lock and retrieve current game row.
    $stmt = $db->prepare(
        "SELECT num_players, game_id" .
        "  FROM current_game" .
        "  WHERE game_type_id=:game_type_id" .
        "  FOR UPDATE"
    );
    $stmt->bindParam(':game_type_id', $game_type_id);

    checked_execute_query($stmt);

    $row = $stmt->fetch(PDO::FETCH_NUM);
    assert_or_die(
        $row, HttpCode::INTERNAL_SERVER_ERROR,
        "Row not found in table 'games'."
    );

    assert_or_die(
        is_numeric($row[0]),
        HttpCode::INTERNAL_SERVER_ERROR, "Field 'num_players' is not an integer."
    );
    $old_num_players = intval($row[0]);
    $game_id = $row[1];

    // Record picked number.
    $stmt = $db->prepare(
        "INSERT INTO game_picked_numbers (game_type_id, player_id, picked_number)" .
        "  VALUES (:game_type_id, :player_id, :picked_number)"
    );
    $stmt->bindParam(':game_type_id', $game_type_id);
    $stmt->bindParam(':player_id', $player_id);
    $stmt->bindParam(':picked_number', $picked_number);
    checked_execute_query($stmt);

    // Update player.
    $stmt = $db->prepare(
        "UPDATE player" .
        "  SET balance=:new_balance" .
        "  WHERE player_id=:player_id"
    );
    $stmt->bindParam(':player_id', $player_id);
    $stmt->bindParam(':new_balance', $new_balance);
    checked_execute_query($stmt);

    if ($old_num_players == 0) {
        // First player in this game.
        assert_or_die(
            is_null($game_id),
            HttpCode::INTERNAL_SERVER_ERROR, "Exisiting game has 0 players."
        );
        // Open new game in game_history, we need a new valid game_id.
        $stmt = $db->prepare(
            "INSERT INTO game_history (game_type_id)" .
            "  VALUES (:game_type_id)"
        );
        $stmt->bindParam(':game_type_id', $game_type_id);
        checked_execute_query($stmt);

        $game_id = $db->lastInsertId();
        assert_or_die(
            !is_null($game_id) && $game_id != 0,
            HttpCode::INTERNAL_SERVER_ERROR,
            "Last insert id for table 'game_history' is invalid."
        );
    } else {
        assert_or_die(
            !is_null($game_id),
            HttpCode::INTERNAL_SERVER_ERROR,
            "Existing game with players has no game_id."
        );
    }

    // Update current game row.
    $new_num_players = $old_num_players + 1;
    $maybe_update_game_id = $old_num_players == 0 ? ", game_id=:game_id" : "";
    $stmt = $db->prepare(
        "UPDATE current_game" .
        "  SET num_players=:new_num_players" .
        "  $maybe_update_game_id" .
        "  WHERE game_type_id=:game_type_id"
    );
    $stmt->bindParam(':new_num_players', $new_num_players);
    $stmt->bindParam(':game_type_id', $game_type_id);
    if ($old_num_players == 0) {
        $stmt->bindParam(':game_id', $game_id);
    }

    checked_execute_query($stmt);

    $total_num_players = $GAME_TYPES[$game_type_id]['num-players'];

    if ($new_num_players >= $total_num_players) {
        // This game has ended, update all tables.

        assert_or_die(
            $total_num_players == $new_num_players,
            HttpCode::INTERNAL_SERVER_ERROR, "Too many players in game."
        );

        // Calculate winner number.
        // Select the minimum of unique numbers in the current game.
        $stmt = $db->prepare(
            "SELECT MIN(picked_number) FROM" .
            "  (SELECT picked_number" .
            "   FROM game_picked_numbers" .
            "   WHERE game_type_id=:game_type_id" .
            "   GROUP BY picked_number" .
            "   HAVING COUNT(1) = 1" .
            "   ) AS inner_query"
        );
        $stmt->bindParam(':game_type_id', $game_type_id);

        checked_execute_query($stmt);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        assert_or_die(
            $row,
            HttpCode::INTERNAL_SERVER_ERROR, "Minimum query returned no rows."
        );
        if (is_null($row[0])) {
            $winner_number = null;
        } else {
            $winner_number = intval($row[0]);
        }

        // Calculate winner number and player.
        if (is_null($winner_number)) {
            $winner_player_email = null;
            $winner_player_id = null;
        } else {
            $stmt = $db->prepare(
                "SELECT player.player_id, email" .
                "  FROM player, game_picked_numbers" .
                "  WHERE game_type_id=:game_type_id AND" .
                "      picked_number=:picked_number" .
                "    AND player.player_id=game_picked_numbers.player_id"
            );
            $stmt->bindParam(':game_type_id', $game_type_id);
            $stmt->bindParam(':picked_number', $winner_number);
            checked_execute_query($stmt);
            $row = $stmt->fetch(PDO::FETCH_NUM);
            assert_or_die(
                $row !== false,
                HttpCode::INTERNAL_SERVER_ERROR, "Winner email not found."
            );
            $winner_player_id = $row[0];
            $winner_player_email = $row[1];
        }

        // Reset game_picked_numbers.
        $stmt = $db->prepare(
            "DELETE FROM game_picked_numbers" .
            "  WHERE game_type_id=:game_type_id"
        );
        $stmt->bindParam(':game_type_id', $game_type_id);
        checked_execute_query($stmt);

        // Update game history.
        $stmt = $db->prepare(
            "UPDATE game_history" .
            "  SET finished=1, winner_player_email=:winner_player_email," .
            "    winner_number=:winner_number" .
            "  WHERE game_id=:game_id"
        );
        $stmt->bindParam(':winner_player_email', $winner_player_email);
        $stmt->bindParam(':winner_number', $winner_number);
        $stmt->bindParam(':game_id', $game_id);
        checked_execute_query($stmt);

        // Reset current game.
        $stmt = $db->prepare(
            "UPDATE current_game" .
            "  SET num_players=0, game_id=NULL" .
            "  WHERE game_type_id=:game_type_id"
        );
        $stmt->bindParam(':game_type_id', $game_type_id);
        checked_execute_query($stmt);

        // Update player.
        if (!is_null($winner_player_email)) {
            $stmt = $db->prepare(
                "UPDATE player" .
                "  SET balance = balance + :amount" .
                "  WHERE player_id=:player_id"
            );
            $stmt->bindParam(':player_id', $winner_player_id);
            $amount = $BET_AMOUNT * $total_num_players;
            $stmt->bindParam(':amount', $amount);
            checked_execute_query($stmt);
        }
    } // If game has ended.

    $r = $db->commit();
    assert_or_die($r === true, HttpCode::SERVICE_UNAVAILABLE, "Commit failed.");

    http_response_code(HttpCode::OK);
    print json_encode(array("game-id" => $game_id));
} catch(Exception $exc){
    http_response_code(503);
    die(
        json_encode(
            array("error" => "Can't execute query.", "message" => $exc->getMessage())
        )
    );
}

?>
