<?php

class HttpCode {
  const OK = 200;
  const CREATED = 201;
  const BAD_REQUEST = 400;
  const PAYMENT_REQUIRED = 402;
  const NOT_FOUND = 404;
  const METHOD_NOT_ALLOWED = 405;
  const INTERNAL_SERVER_ERROR = 500;
  const SERVICE_UNAVAILABLE = 503;
}

function assert_or_die($condition, $code, $error) {
  if (!condition) {
    http_response_code($code);
    die(json_encode(array("error" => $error)));
  }
}

function assert_or_die_msg($condition, $code, $error, $message) {
  if (!condition) {
    http_response_code($code);
    die(json_encode(array("error" => $error, "message" => $message)));
  }
}

?>
