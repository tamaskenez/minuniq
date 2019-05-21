<?php

require_once 'util.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header(
    "Access-Control-Allow-Headers: Content-Type," .
    " Access-Control-Allow-Headers, Authorization, X-Requested-With"
);

assert_or_die(
    $_SERVER['REQUEST_METHOD'] === 'POST',
    HttpCode::METHOD_NOT_ALLOWED, "Only POST is allowed."
);

?>
