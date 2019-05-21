<?php

require '../common/post_prelude.php';
require_once '../common/util.php';

$email = nonempty_post_arg('email');

require_once '../common/database.php';

$db = open_db();

try {
    $stmt = $db->prepare("INSERT INTO player SET email=:email");
    $stmt->bindParam(':email', $email);

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
} catch(Exception $exc){
    http_response_code(HttpCode::SERVICE_UNAVAILABLE);
    die(
        json_encode(
            array("error" => "Can't execute query.",
            "message" => $exc->getMessage())
        )
    );
}

?>
