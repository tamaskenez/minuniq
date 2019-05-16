<?php

require_once '../util.php';
require_once '../database.php';
require_once '../game_config.php';
require_once '../test_functions.php';


function pr($x) {
  print("<br>BEGIN<br>");
  var_dump($x);
  print("<br>END<br>");
}

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
  global $MAX_PICKED_NUMBER;

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

  foreach(array(0, -1, "notanumber", "not a number", "1 notanumber") as $b) {
    $r = test_curl_request('POST', 'top-up-balance',
      array('email' => $email, 'amount' => $b));
    check($r['response'] == HttpCode::BAD_REQUEST, "player/top-up/$b");
  }

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


  $r = test_curl_request('POST', 'register-player', array('email' => $email));
  check($r['response'] == HttpCode::CREATED, 'player');
  $r = test_curl_request('POST', 'top-up-balance', array('email' => $email, 'amount' => 123.45));
  check($r['response'] == HttpCode::OK, 'player/top-up');

  $tn = 'join-game-corner-cases';

  $join_game_args = array(
    'email' => $email,
    'game-type-id' => 0,
    'picked-number' => 1
  );

  test_missing_empty_invalid_email('POST', 'join-game', "$email.", $join_game_args);

  $args = $join_game_args;
  unset($args['game-type-id']);
  $r = test_curl_request('POST', 'join-game', $args);
  check($r['response'] == HttpCode::BAD_REQUEST, "$tn/missing game-type-id");

  $args = $join_game_args;
  $args['game-type-id'] = 99999;
  $r = test_curl_request('POST', 'join-game', $args);
  check($r['response'] == HttpCode::BAD_REQUEST, "$tn/invalid game-type-id");

  $args = $join_game_args;
  unset($args['picked-number']);
  $r = test_curl_request('POST', 'join-game', $args);
  check($r['response'] == HttpCode::BAD_REQUEST, "$tn/missing picked-number");

  $args = $join_game_args;
  $args['picked-number'] = $MAX_PICKED_NUMBER + 1;
  $r = test_curl_request('POST', 'join-game', $args);
  check($r['response'] == HttpCode::BAD_REQUEST, "$tn/invalid picked-number");

  $args = $join_game_args;
  $args['picked-number'] = 0;
  $r = test_curl_request('POST', 'join-game', $args);
  check($r['response'] == HttpCode::BAD_REQUEST, "$tn/zero picked-number");

  // Delete player from ongoing game.
  $tn = 'delete-player/ongoing game';

  $r = test_curl_request('POST', 'join-game', $join_game_args);
  check($r['response'] == HttpCode::OK, $tn);

  $r = test_curl_request('POST', 'delete-player', array('email' => $email));
  check($r['response'] == HttpCode::BAD_REQUEST, 'player/delete');
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

function game_test_with_picked_numbers($game_type_id, $players, $tn, $multithreaded) {
  progress("-- $tn");
  global $GAME_TYPES, $BET_AMOUNT;

  $NP = $GAME_TYPES[$game_type_id]['num-players'];
  check($NP == count($players), $tn);

  $db = create_open_empty_test_db();
  $i = 100;
  foreach($players as $k => $v) {
    $email = "email-$k";
    $amount = $i;
    $i = $i + 1;

    $players[$k]['email'] = $email;
    $players[$k]['balance'] = $amount;

    $r = test_curl_request('POST', 'register-player', array('email' => $email));
    check($r['response'] == HttpCode::CREATED, $tn);

    $r = test_curl_request('POST', 'top-up-balance', array(
      'email' => $email, 'amount' => $amount));
    check($r['response'] == HttpCode::OK, 'player/top-up');
    check_player_balance($email, $amount, $tn);
  }

  $numbers = array();
  $game_id = NULL;
  $num_players = 0;
  $winner_email = NULL;
  foreach($players as $k => $v) {
    $picked_number = $v['picked-number'];
    $args = array(
      'email' => $v['email'],
      'game-type-id' => $game_type_id,
      'picked-number' => $picked_number);
    ++$num_players;
    if (isset($numbers[$picked_number])) {
      $numbers[$picked_number] = $numbers[$picked_number] + 1;
    } else {
      $numbers[$picked_number] = 1;
    }
    if ($multithreaded) {
      // just save the object
    } else {
      $r = test_curl_request('POST', 'join-game', $args);
      check($r['response'] == HttpCode::OK, $tn);
      $jr = json_decode($r['transfer'], TRUE);
      check(isset($jr['game-id']), $tn);
      if (is_null($game_id)) {
        $game_id = $jr['game-id'];
      } else {
        check($jr['game-id'] == $game_id, $tn);
      }

      $r = test_curl_request('GET', 'query-game', array(
        "game-id" => $game_id
      ));
      check($r['response'] == HttpCode::OK, $tn);
      $jr = json_decode($r['transfer'], TRUE);
      check($jr['game-type-id'] == $game_type_id, $tn . '/invalid game-type-id');
      check($jr['num-players'] == $num_players, $tn . '/invalid num-players');
    }
    if ($num_players == $NP) {
      if ($multithreaded) {
        // Launch and wait for each object to finish.
        // Check game-id for all of them.
        $r = test_curl_request('GET', 'query-game', array(
          "game-id" => $game_id
        ));
        check($r['response'] == HttpCode::OK, $tn);
        $jr = json_decode($r['transfer'], TRUE);
        check($jr['game-type-id'] == $game_type_id, $tn . '/invalid game-type-id');
        check($jr['num-players'] == $num_players, $tn . '/invalid num-players');
      }
      check($jr['finished'] == 1, $tn . '/invalid finished');
      ksort($numbers);
      $winner_number = NULL;
      foreach($numbers as $k => $v) {
        if ($v == 1) {
          $winner_number = $k;
          break;
        }
      }
      if (is_null($winner_number)) {
        check(!isset($jr['winner_number']), $tn . '/winner number set');
        check(!isset($jr['winner_email']), $tn . '/winner email set');
      } else {
        foreach($players as $k => $v) {
          if ($v['picked-number'] == $winner_number) {
            $winner_email = $v['email'];
            break;
          }
        }
        check(!is_null($winner_email), $tn . '/no winner email');
        check($winner_email == $jr['winner-email'],
          $tn . '/invalid winner email');
        check($winner_number == $jr['winner-number'],
          $tn . '/invalid winner number');
      }
    } else {
      check($num_players < $NP, $tn . '/invalid num-players');
      check($jr['finished'] == 0, $tn . '/invalid finished');
    }
  }
  foreach($players as $k => $v) {
    if (is_null($winner_email) || $v['email'] != $winner_email) {
      $expected_balance = $v['balance'] - $BET_AMOUNT;
    } else {
      $expected_balance = $v['balance'] + $BET_AMOUNT * ($NP - 1);
    }
    check_player_balance($v['email'], $expected_balance, $tn);
  }
}

function game_test_exhaustive_3_and_corners() {
  global $GAME_TYPES;

  $NP = $GAME_TYPES[0]['num-players'];
  check($NP == 3, 'game-test-exhaustive-3');
  $i = 0;

  for($a = 1; $a <= 3; ++$a) {
    for($b = 1; $b <= 3; ++$b) {
      for($c = 1; $c <= 3; ++$c) {
        game_test_with_picked_numbers(0, array(
            0 => array('picked-number' => $a),
            1 => array('picked-number' => $b),
            2 => array('picked-number' => $c)),
          "Exhaustive 3-player game test/$i",
          FALSE);
          ++$i;
      }
    }
  }

  progress('-- query-game corner cases');
  $tn = 'query-game-corner-cases';

  $r = test_curl_request('GET', 'query-game', array());
  check($r['response'] == HttpCode::BAD_REQUEST, "$tn/missing game-id");

  $r = test_curl_request('GET', 'query-game', array('game-id' => ''));
  check($r['response'] == HttpCode::BAD_REQUEST, "$tn/empty game-id");

  $r = test_curl_request('GET', 'query-game', array('game-id' => 'nosuch'));
  check($r['response'] == HttpCode::BAD_REQUEST, "$tn/invalid game-id 1");

  $r = test_curl_request('GET', 'query-game', array('game-id' => '1 nosuch'));
  check($r['response'] == HttpCode::BAD_REQUEST, "$tn/invalid game-id 2");

  $r = test_curl_request('GET', 'query-game', array('game-id' => '2'));
  check($r['response'] == HttpCode::NOT_FOUND, "$tn/invalid game-id 3");
}

function game_test($game_type_id, $multithreaded) {
  global $GAME_TYPES, $MAX_PICKED_NUMBER;
  $NUM_GAMES_PER_TYPE = 4;
  $NP = $GAME_TYPES[$game_type_id]['num-players'];
  srand(1);
  for($j = 0; $j < $NUM_GAMES_PER_TYPE; ++$j) {
    $players = array();
    for($i = 0; $i < $NP; ++$i) {
      $picked_number = rand(1, $NP);
      $players[$i] = array('picked-number' => $picked_number);
    }
    game_test_with_picked_numbers($game_type_id, $players,
      "Random game test $NP players #$j", $multithreaded);
  }
}

error_log("Start test.");
header( 'Content-type: text/html; charset=utf-8' );
$http_host = $_SERVER['HTTP_HOST'];
progress("Full API test, HTTP_HOST = $http_host");

player_crud_test();
static_queries();
game_test_exhaustive_3_and_corners();

foreach($GAME_TYPES as $k => $v) {
  game_test($k, FALSE);
}

foreach($GAME_TYPES as $k => $v) {
  game_test($k, TRUE);
}

progress("Test done.");

?>
