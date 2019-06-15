<?php

require '../common/circuit_breaker.php';

require_once '../common/util.php';
require_once '../common/database.php';
require_once '../common/auth.php';

add_post_headers();

$amount = nonempty_post_arg('amount');
$amount_float = floatval($amount);

assert_or_die(
    is_numeric($amount) && $amount_float > 0, HttpCode::BAD_REQUEST,
    "Field 'amount' is not a positive number."
);

try {
    $user = userdata_from_post();

    $db = open_db();

    $db->beginTransaction();

    $player = select_player_for_update_or_null($db, $user);
    $old_balance = $player['balance'];

    assert_or_die(!is_null($old_balance), HttpCode::NOT_FOUND, "Player not found.");

    $stmt = $db->prepare(
        "UPDATE player SET balance=:new_balance WHERE player_id=:id"
    );
    $stmt->bindParam(':id', $player['player_id']);
    $new_balance = $old_balance + $amount_float;
    $stmt->bindParam(':new_balance', $new_balance);

    checked_execute_query($stmt);
    $stmt = $db->query("SELECT * FROM player");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $r = $db->commit();
    assert_or_die($r === true, HttpCode::SERVICE_UNAVAILABLE, "Commit failed.");
    http_response_code(HttpCode::OK);
} catch(Exception $exc) {
    assert_or_die_msg(false, HttpCode::SERVICE_UNAVAILABLE,
      "Can't execute query.", $exc->getMessage());
}

circuit_breaker_epilog();

?>
