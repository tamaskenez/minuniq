<?php

class HttpCode
{
    const OK = 200;
    const CREATED = 201;
    const BAD_REQUEST = 400;
    const PAYMENT_REQUIRED = 402;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const INTERNAL_SERVER_ERROR = 500;
    const SERVICE_UNAVAILABLE = 503;
}

function assert_or_die($condition, $code, $error)
{
    if (!$condition) {
        circuit_breaker_epilog();
        http_response_code($code);
        die(json_encode(array("error" => $error)));
    }
}

function assert_or_die_msg($condition, $code, $error, $message)
{
    if (!$condition) {
        circuit_breaker_epilog();
        http_response_code($code);
        die(json_encode(array("error" => $error, "message" => $message)));
    }
}

function var_error_log($x)
{
    ob_start();
    var_dump($x);
    error_log(ob_get_clean());
}

function add_get_headers() {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");

    assert_or_die(
        $_SERVER['REQUEST_METHOD'] === 'GET',
        HttpCode::METHOD_NOT_ALLOWED, "Only GET is allowed."
    );
}

function add_post_headers() {
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
}

// method can be 'GET', 'POST' (form-data) and 'POSTX' (x-www-form-urlencoded)
function curl_request($url, $method, $data)
{
    $curl = curl_init();
    switch ($method)
    {
    case 'POST':
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        break;
    case 'POSTX':
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt(
                $curl, CURLOPT_HTTPHEADER,
                array('Content-Type: application/x-www-form-urlencoded')
            );
        }
        break;
    case 'GET':
        if ($data) {
            $url = sprintf("%s?%s", $url, http_build_query($data));
        }
        break;
    default:
        die("Invalid request method: ". $method);
    }

    // curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // curl_setopt($curl, CURLOPT_USERPWD, "username:password");

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $transfer = curl_exec($curl);
    $response = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

    curl_close($curl);

    if ($transfer === false) {
        return false;
    }

    return array('transfer' => $transfer, 'response' => $response);
}

function nonempty_post_arg($name)
{
    assert_or_die(
        array_key_exists($name, $_POST),
        HttpCode::BAD_REQUEST, "Field '$name' is missing."
    );
    $r = htmlspecialchars(strip_tags($_POST[$name]));
    assert_or_die($r != "", HttpCode::BAD_REQUEST, "Field '$name' is empty.");
    return $r;
}

function nonempty_get_arg($name)
{
    assert_or_die(
        array_key_exists($name, $_GET),
        HttpCode::BAD_REQUEST, "Field '$name' is missing."
    );
    $r = htmlspecialchars(strip_tags($_GET[$name]));
    assert_or_die(!empty($r), HttpCode::BAD_REQUEST, "Field '$name' is empty.");
    return $r;
}

function nonempty_post_arg_or_null($name)
{
    if (!array_key_exists($name, $_POST)) {
        return null;
    }

    $r = htmlspecialchars(strip_tags($_POST[$name]));
    if ($r == "") {
        return null;
    }

    return $r;
}

function nonempty_get_arg_or_null($name)
{
    if (!array_key_exists($name, $_GET)) {
        return null;
    }

    $r = htmlspecialchars(strip_tags($_GET[$name]));
    if ($r == "") {
        return null;
    }

    return $r;
}

?>
