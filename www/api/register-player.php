<?php

require '../post_prelude.php';
require_once '../util.php';

$email = htmlspecialchars(strip_tags($_POST['email']));

assert_or_die(!empty($email), HttpCode::BAD_REQUEST, "Field 'email' is empty.");

require_once '../database.php';

$db = open_db();

try {
  $stmt = $db->prepare("INSERT INTO player SET email=:email");
  $stmt->bindParam(':email', $email);

  $r = $stmt->execute();
  assert_or_die_msg($r, HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]);

  http_response_code(HttpCode::CREATED);
} catch(Exception $exc){
  http_response_code(HttpCode::SERVICE_UNAVAILABLE);
  die(json_encode(array("error" => "Can't execute query.", "message" => $exc->getMessage())));
}

// test
// get
// post empty
// post valid
// post same
// post new valid

?>
