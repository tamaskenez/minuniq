<?php

require_once '../util.php';
require_once '../database.php';
require_once '../game_config.php';
require_once '../test_functions.php';

header( 'Content-type: text/html; charset=utf-8' );

function pr($x) {
  print("<br>BEGIN<br>");
  var_dump($x);
  print("<br>END<br>");
}

$http_host = $_SERVER['HTTP_HOST'];
progress("Full API test, HTTP_HOST = $http_host");

function create_open_empty_test_db() {
  create_empty_test_db();
  $db = open_db_1(TRUE);
  run_db_init_script($db);
  return $db;
}

function test_missing_empty_email($method, $api, $args) {
  unset($args['email']);

  $r = test_curl_request($method, $api, $args);
  check($r['response'] == HttpCode::BAD_REQUEST, "$api/missing email");

  $args['email'] = '';
  $r = test_curl_request($method, $api, $args);
  check($r['response'] == HttpCode::BAD_REQUEST, "$api/empty email");
}

function test_missing_empty_invalid_email($method, $api, $invalid_email, $args) {
  test_missing_empty_email($method, $api, $args);

  $args['email'] = $invalid_email;

  $r = test_curl_request($method, $api, $args);
  check($r['response'] == HttpCode::NOT_FOUND, "$api/invalid email");
}

// Full cycle for a player.
function player_crud_test() {
  progress("-- Player CRUD tests.");
  $db = create_open_empty_test_db();

  $email = 'example@email.com';

  test_missing_empty_email('POST', 'register-player', array());
  test_missing_empty_invalid_email('GET', 'get-player', "$email.", array());

  $r = test_curl_request('POST', 'register-player', array('email' => $email));
  check($r['response'] == HttpCode::CREATED, 'player');

  // .. and check balance.
  check_player_balance($email, 0, 'player/balance');

  $tn = 'register-player/already registered';
  $r = test_curl_request('POST', 'register-player', array('email' => $email));
  check($r['response'] == HttpCode::BAD_REQUEST, $tn);

  test_missing_empty_invalid_email('GET', 'get-player', "$email.", array());

  // Top-up.
  $amount1 = 123.45;

  $top_up_args = array('email' => $email, 'amount' => $amount1);
  test_missing_empty_invalid_email('POST', 'top-up-balance', "$email.",
    $top_up_args);

  $r = test_curl_request('POST', 'top-up-balance', $top_up_args);
  check($r['response'] == HttpCode::OK, 'player/top-up');

  // .. and check data.
  check_player_balance($email, $amount1, 'player/topped-up balance');

  // Top-up again.
  $amount2 = 212.12;
  $r = test_curl_request('POST', 'top-up-balance', array(
    'email' => $email, 'amount' => $amount2));
  check($r['response'] == HttpCode::OK, 'player/top-up');

  // .. and check data.
  check_player_balance($email, $amount1 + $amount2, 'player/topped-up balance 2');

  // Delete player.
  test_missing_empty_invalid_email('POST', 'delete-player', "$email.", array());

  $r = test_curl_request('POST', 'delete-player', array('email' => $email));
  check($r['response'] == HttpCode::OK, 'player/delete');

  $r = test_curl_request('GET', 'get-player', array('email' => $email));
  check($r['response'] == HttpCode::NOT_FOUND, 'player/delete-missing');

  // Delete player from ongoing game.
  $tn = 'delete-player/ongoing game';

  $r = test_curl_request('POST', 'register-player', array('email' => $email));
  check($r['response'] == HttpCode::CREATED, 'player');
  $r = test_curl_request('POST', 'top-up-balance', array('email' => $email, 'amount' => 123.45));
  check($r['response'] == HttpCode::OK, 'player/top-up');

  $r = test_curl_request('POST', 'join-game', array(
    'email' => $email,
    'game-type-id' => 0,
    'picked-number' => 1
  ));
  check($r['response'] == HttpCode::OK, $tn);

  $r = test_curl_request('POST', 'delete-player', array('email' => $email));
  check($r['response'] == HttpCode::BAD_REQUEST, 'player/delete');
}

function player_corner_cases_test() {
  $tn = 'query-game';
  $tn = 'join-game';
}

function static_queries() {
  progress('-- Test list-game-types.');
  $tn = 'list-game-types';
  $r = test_curl_request('GET', 'list-game-types', array());
  check($r['response'] == HttpCode::OK, $tn);
  $jr = json_decode($r['transfer'], TRUE);

  global $GAME_TYPES;
  global $MAX_PICKED_NUMBER;

  check($jr == $GAME_TYPES, $tn);

  progress('-- Test max-picked-number.');
  $tn = 'max-picked-number';
  $r = test_curl_request('GET', 'max-picked-number', array());
  check($r['response'] == HttpCode::OK, $tn);
  $jr = json_decode($r['transfer'], TRUE);
  check($jr == $MAX_PICKED_NUMBER, $tn);
}

player_crud_test();
player_corner_cases_test();
static_queries();

progress("Test done.");

?>
