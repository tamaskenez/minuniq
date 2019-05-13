<?php

require '../post_prelude.php';
require_once '../util.php'

$email = htmlspecialchars(strip_tags($_POST['email']));
$amount = htmlspecialchars(strip_tags($_POST['amount']));
$amount_float = floatval($amount);

assert_or_die(!empty($email), HttpCode::BAD_REQUEST, "Field 'email' is empty.");
assert_or_die(is_numeric($amount) && $amount_float > 0, HttpCode::BAD_REQUEST, "Field 'amount' is not a positive number.");

require_once '../database.php';

$db = open_db($_POST['testing']);

try {
  $db->beginTransaction();

  $old_balance = select_player_balance_for_update_or_null($db, $email);

  assert_or_die(!is_null($old_balance), HttpCode::NOT_FOUND, "Player not found.");

  $stmt = $db->prepare("UPDATE player SET balance=:new_balance WHERE email=:email");
  $stmt->bindParam(':email', $email);
  $new_balance = $old_balance + $amount_float;
  $stmt->bindParam(':new_balance', $new_balance);

  checked_execute_query($stmt);

  $db->commit();
  http_response_code(HttpCode::OK);
} catch(Exception $exc){
  http_response_code(HttpCode::SERVICE_UNAVAILABLE);
  die(json_encode(array("error" => "Can't execute query.", "message" => $exc->getMessage())));
}

// test
// get
// invalid player
// invalid amount
// valid amount

?>
