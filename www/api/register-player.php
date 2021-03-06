<?php

require '../common/circuit_breaker.php';

require_once '../common/util.php';
require_once '../common/auth.php';
require_once '../common/database.php';

add_post_headers();

try {
    $user = userdata_from_post();

    $db = open_db();

    $stmt = $db->prepare(
      "INSERT INTO player SET email=:email, google_user_id=:google_user_id");
    $stmt->bindParam(':email', $user['email']);
    $stmt->bindParam(':google_user_id', $user['google_user_id']);

    $r = $stmt->execute();
    if ($r === false) {
        if ($stmt->errorInfo()[1] == MySql::ER_DUP_ENTRY) {
            http_response_code(HttpCode::BAD_REQUEST);
            die(json_encode(array("error" => "Duplicate entry.")));
        } else {
            assert_or_die_msg(
                false,
                HttpCode::SERVICE_UNAVAILABLE,
                "Can't execute query.", $stmt->errorInfo()[2]
            );
        }
    }
    assert_or_die_msg(
        $r !== false,
        HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]
    );

    http_response_code(HttpCode::CREATED);
} catch(Exception $exc) {
    assert_or_die_msg(false, HttpCode::SERVICE_UNAVAILABLE,
      "Can't execute query.", $exc->getMessage());
}

circuit_breaker_epilog();

?>
