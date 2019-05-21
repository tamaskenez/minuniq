<?php

require_once 'util.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

assert_or_die(
    $_SERVER['REQUEST_METHOD'] === 'GET',
    HttpCode::METHOD_NOT_ALLOWED, "Only GET is allowed."
);

?>
