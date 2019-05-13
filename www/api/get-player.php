<?php

require '../get_prelude.php';
require_once '../util.php';

$email = htmlspecialchars(strip_tags($_GET['email']));

assert_or_die(!empty($email), HttpCode::BAD_REQUEST, "Field 'email' is empty.");

require_once '../database.php';

$db = open_db($_GET['testing']);

try {
  $stmt = $db->prepare("SELECT * FROM player WHERE email=:email");
  $stmt->bindParam(':email', $email);

  checked_execute_query($stmt);

  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  assert_or_die($row, HttpCode::NOT_FOUND, "Player not found.");

  http_response_code(HttpCode::OK);
  print json_encode($row);
} catch(Exception $exc){
  http_response_code(HttpCode::SERVICE_UNAVAILABLE);
  die(json_encode(array("error" => "Can't execute query.", "message" => $exc->getMessage())));
}

// test
// post
// invalid player
// valid player

?>
