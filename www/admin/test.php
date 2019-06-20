<?php

require '../common/circuit_breaker.php';

require_once '../common/util.php';
require_once '../common/database.php';
require_once '../common/game_config.php';
require_once '../common/test_functions.php';

function pr($x)
{
    print("<br>BEGIN<br>");
    var_dump($x);
    print("<br>END<br>");
}

function create_open_empty_test_db()
{
    drop_and_init_db(true);
    $db = open_db_1(true);
    run_db_init_script($db);
    return $db;
}

function test_missing_empty_email($method, $api, $args)
{
    unset($args['email']);

    $r = test_curl_request($method, $api, $args);
    check($r['response'] == HttpCode::BAD_REQUEST, "$api/missing email");

    $args['email'] = '';
    $r = test_curl_request($method, $api, $args);
    check($r['response'] == HttpCode::BAD_REQUEST, "$api/empty email");
}

function test_missing_empty_invalid_email($method, $api, $invalid_email, $args)
{
    test_missing_empty_email($method, $api, $args);

    $args['email'] = $invalid_email;

    $r = test_curl_request($method, $api, $args);
    check($r['response'] == HttpCode::NOT_FOUND, "$api/invalid email");
}

function create_and_init_test()
{
    progress("-- Create empty test database.");
    drop_and_init_db(true);

    progress("-- Initialize database.");
    $db = open_db_1(true);
    run_db_init_script($db);

    progress("-- Verify empty database.");
    check_table_size($db, "game_history", 0);
    check_table_size($db, "game_picked_numbers", 0);
    check_table_size($db, "player", 0);
    check_table_current_game_is_initial_state($db);
}

function api_whitebox_smoke_test()
{
    progress("API smoke test (whitebox)");

    progress("-- Test register-player.");

    $db = create_open_empty_test_db();
    $email = 'example@email.com';
    $r = test_curl_request('POST', 'register-player', array('email' => $email));
    check(
        $r['response'] == HttpCode::CREATED,
        "Response should have been CREATED"
    );

    check_table_size($db, "player", 1);
    $stmt = $db->query('SELECT player_id, email, balance FROM player');
    $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : false;
    if (!$row) {
        die("Can't read player row");
    }
    $player_id = $row[0];
    if ($player_id != 1 || $row[1] != $email || $row[2] != 0.0) {
        die("Invalid columns in player row");
    }

    progress("-- Test top-up-balance.");

    $amount = 123.45;
    $r = test_curl_request(
        'POST', 'top-up-balance',
        array('email' => $email, 'amount' => $amount)
    );
    check(
        $r['response'] == HttpCode::OK,
        "Response should have been OK"
    );
    $stmt = $db->query("SELECT balance FROM player WHERE player_id=$player_id");
    $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : false;
    if (!$row) {
        die("Can't read player row");
    }
    if ($row[0] != $amount) {
        die("Invalid amount after top-up.");
    }

    progress("-- Test delete-player.");
    $r = test_curl_request('POST', 'delete-player', array('email' => $email));
    check(
        $r['response'] == HttpCode::OK,
        "Response should have been OK"
    );
    check_table_size($db, "player", 0);
}

// Full cycle for a player.
function player_crud_test()
{
    global $MAX_PICKED_NUMBER, $BET_AMOUNT;

    progress("-- Player CRUD tests.");
    $db = create_open_empty_test_db();

    $email = 'example@email.com';

    test_missing_empty_email('POST', 'register-player', array());
    test_missing_empty_invalid_email('POST', 'get-player', "$email.", array());

    $r = test_curl_request('POST', 'register-player', array('email' => $email));
    check($r['response'] == HttpCode::CREATED, 'player');

    // .. and check balance.
    check_player_balance($email, 0, 'player/balance', array());

    $tn = 'register-player/already registered';
    $r = test_curl_request('POST', 'register-player', array('email' => $email));
    check($r['response'] == HttpCode::BAD_REQUEST, $tn);

    test_missing_empty_invalid_email('POST', 'get-player', "$email.", array());

    // Top-up.
    $amount1 = 123.45;

    $top_up_args = array('email' => $email, 'amount' => $amount1);
    test_missing_empty_invalid_email(
        'POST', 'top-up-balance', "$email.",
        $top_up_args
    );

    foreach (array(0, -1, "notanumber", "not a number", "1 notanumber") as $b) {
        $r = test_curl_request(
            'POST', 'top-up-balance',
            array('email' => $email, 'amount' => $b)
        );
        check($r['response'] == HttpCode::BAD_REQUEST, "player/top-up/$b");
    }

    $r = test_curl_request('POST', 'top-up-balance', $top_up_args);
    check($r['response'] == HttpCode::OK, 'player/top-up');

    // .. and check data.
    check_player_balance($email, $amount1, 'player/topped-up balance', array());

    // Top-up again.
    $amount2 = 212.12;
    $r = test_curl_request(
        'POST', 'top-up-balance', array(
        'email' => $email, 'amount' => $amount2)
    );
    check($r['response'] == HttpCode::OK, 'player/top-up');

    // .. and check data.
    check_player_balance(
        $email, $amount1 + $amount2,
        'player/topped-up balance 2', array()
    );

    // Delete player.
    test_missing_empty_invalid_email('POST', 'delete-player', "$email.", array());

    $r = test_curl_request('POST', 'delete-player', array('email' => $email));
    check($r['response'] == HttpCode::OK, 'player/delete');

    $r = test_curl_request('POST', 'get-player', array('email' => $email));
    check($r['response'] == HttpCode::NOT_FOUND, 'player/delete-missing');


    $r = test_curl_request('POST', 'register-player', array('email' => $email));
    check($r['response'] == HttpCode::CREATED, 'player');
    $r = test_curl_request(
        'POST', 'top-up-balance', array(
        'email' => $email, 'amount' => $amount1)
    );
    check($r['response'] == HttpCode::OK, 'player/top-up');

    $tn = 'join-game-corner-cases';

    $join_game_args = array(
    'email' => $email,
    'game-type-id' => 0,
    'picked-number' => 1
    );

    test_missing_empty_invalid_email(
        'POST', 'join-game', "$email.",
        $join_game_args
    );

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

    $args = $join_game_args;
    $args['picked-number'] = 'a';
    $r = test_curl_request('POST', 'join-game', $args);
    check($r['response'] == HttpCode::BAD_REQUEST, "$tn/letter picked-number");

    $args = $join_game_args;
    $args['picked-number'] = '5 a';
    $r = test_curl_request('POST', 'join-game', $args);
    check($r['response'] == HttpCode::BAD_REQUEST,
      "$tn/number plus letter picked-number");

    // Delete player from ongoing game.
    $tn = 'delete-player/ongoing game';

    $r = test_curl_request('POST', 'join-game', $join_game_args);
    check($r['response'] == HttpCode::OK, $tn);
    $jr = json_decode($r['transfer'], true);
    $game_id = $jr['game-id'];

    // Try joining again the same game type.
    $r = test_curl_request('POST', 'join-game', $join_game_args);
    check($r['response'] == HttpCode::BAD_REQUEST, 'player/join again');

    check_player_balance(
        $email, $amount1 - $BET_AMOUNT,
        'player/join again balance',
        array($join_game_args['game-type-id'] => $game_id)
    );

    // Delete player with ongoing game.
    $r = test_curl_request('POST', 'delete-player', array('email' => $email));
    check($r['response'] == HttpCode::BAD_REQUEST, 'player/delete');
}

function static_queries()
{
    global $GAME_TYPES;
    global $MAX_PICKED_NUMBER;

    progress('-- Test list-game-types.');
    $tn = 'list-game-types';
    $r = test_curl_request('GET', 'list-game-types', array());
    check($r['response'] == HttpCode::OK, $tn);
    $jr = json_decode($r['transfer'], true);
    check($jr == $GAME_TYPES, $tn);

    progress('-- Test max-picked-number.');
    $tn = 'max-picked-number';
    $r = test_curl_request('GET', 'max-picked-number', array());
    check($r['response'] == HttpCode::OK, $tn);
    $jr = json_decode($r['transfer'], true);
    check($jr == $MAX_PICKED_NUMBER, $tn);
}

function circuit_breaker_test()
{
    progress('-- Test circuit breaker.');

    $tn = 'list-game-types';
    $r = test_curl_request('GET', 'list-game-types', array());
    check($r['response'] == HttpCode::OK, $tn);

    // Simulate long request.
    global $SCRIPT_START_TIME, $CIRCUIT_BREAKER_REJECT_SEC,
      $MAX_SCRIPT_TIME_SEC;

    $SCRIPT_START_TIME = time() - 2 * $MAX_SCRIPT_TIME_SEC;
    circuit_breaker_epilog();

    $tn = 'list-game-types';
    $r = test_curl_request('GET', 'list-game-types', array());
    check($r['response'] == HttpCode::SERVICE_UNAVAILABLE, $tn);

    sleep($CIRCUIT_BREAKER_REJECT_SEC);

    $tn = 'list-game-types';
    $r = test_curl_request('GET', 'list-game-types', array());
    check($r['response'] == HttpCode::OK, $tn);
}

function game_test_with_picked_numbers($game_type_id, $players, $tn)
{
    progress("-- $tn");
    global $GAME_TYPES, $BET_AMOUNT;

    $NP = $GAME_TYPES[$game_type_id]['num-players'];
    check($NP == count($players), $tn);

    $db = create_open_empty_test_db();
    $i = 100;
    foreach ($players as $k => $v) {
        $email = "email-$k";
        $amount = $i;
        $i = $i + 1;

        $players[$k]['email'] = $email;
        $players[$k]['balance'] = $amount;

        $r = test_curl_request('POST', 'register-player', array('email' => $email));
        check($r['response'] == HttpCode::CREATED, $tn);

        $r = test_curl_request(
            'POST', 'top-up-balance', array(
            'email' => $email, 'amount' => $amount)
        );
        check($r['response'] == HttpCode::OK, 'player/top-up');
        check_player_balance($email, $amount, $tn, array());
    }

    $numbers = array();
    $game_id = null;
    $num_players = 0;
    $winner_email = null;
    foreach ($players as $k => $v) {
        $picked_number = $v['picked-number'];
        $args = array(
        'email' => $v['email'],
        'game-type-id' => $game_type_id,
        'picked-number' => $picked_number);
        ++$num_players;
        if (array_key_exists($picked_number, $numbers)) {
            $numbers[$picked_number] = $numbers[$picked_number] + 1;
        } else {
            $numbers[$picked_number] = 1;
        }
        $r = test_curl_request('POST', 'join-game', $args);
        check($r['response'] == HttpCode::OK, $tn);
        $jr = json_decode($r['transfer'], true);
        check(array_key_exists('game-id', $jr), $tn);
        if (is_null($game_id)) {
            $game_id = $jr['game-id'];
        } else {
            check($jr['game-id'] == $game_id, $tn);
        }

        $r = test_curl_request(
            'GET', 'query-game', array(
            "game-id" => $game_id
            )
        );
        check($r['response'] == HttpCode::OK, $tn);
        $jr = json_decode($r['transfer'], true);
        check($jr['game-type-id'] == $game_type_id, $tn . '/invalid game-type-id');
        check($jr['num-players'] == $num_players, $tn . '/invalid num-players');

        if ($num_players == $NP) {
            check($jr['finished'] == 1, $tn . '/invalid finished');
            ksort($numbers);
            $winner_number = null;
            foreach ($numbers as $k => $v) {
                if ($v == 1) {
                    $winner_number = $k;
                    break;
                }
            }
            if (is_null($winner_number)) {
                check(!array_key_exists('winner_number', $jr), $tn . '/winner number set');
                check(!array_key_exists('winner_email', $jr), $tn . '/winner email set');
            } else {
                foreach ($players as $k => $v) {
                    if ($v['picked-number'] == $winner_number) {
                        $winner_email = $v['email'];
                        break;
                    }
                }
                check(!is_null($winner_email), $tn . '/no winner email');
                check(
                    $winner_email == $jr['winner-email'],
                    $tn . '/invalid winner email'
                );
                check(
                    $winner_number == $jr['winner-number'],
                    $tn . '/invalid winner number'
                );
            }
        } else {
            check($num_players < $NP, $tn . '/invalid num-players');
            check($jr['finished'] == 0, $tn . '/invalid finished');
        }
    }
    foreach ($players as $k => $v) {
        if (is_null($winner_email) || $v['email'] != $winner_email) {
            $expected_balance = $v['balance'] - $BET_AMOUNT;
        } else {
            $expected_balance = $v['balance'] + $BET_AMOUNT * ($NP - 1);
        }
        check_player_balance($v['email'], $expected_balance, $tn, array());
    }
}

function game_test_exhaustive_3_and_corners()
{
    global $GAME_TYPES;

    $NP = $GAME_TYPES[0]['num-players'];
    check($NP == 3, 'game-test-exhaustive-3');
    $i = 0;

    for ($a = 1; $a <= 3; ++$a) {
        for ($b = 1; $b <= 3; ++$b) {
            for ($c = 1; $c <= 3; ++$c) {
                game_test_with_picked_numbers(
                    0, array(
                    0 => array('picked-number' => $a),
                    1 => array('picked-number' => $b),
                    2 => array('picked-number' => $c)),
                    "Exhaustive 3-player game test/$i"
                );
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

function game_test($game_type_id)
{
    global $GAME_TYPES, $MAX_PICKED_NUMBER;
    $NUM_GAMES_PER_TYPE = 4;
    $NP = $GAME_TYPES[$game_type_id]['num-players'];
    srand(1);
    for ($j = 0; $j < $NUM_GAMES_PER_TYPE; ++$j) {
        $players = array();
        for ($i = 0; $i < $NP; ++$i) {
            $picked_number = rand(1, $NP);
            $players[$i] = array('picked-number' => $picked_number);
        }
        game_test_with_picked_numbers(
            $game_type_id, $players,
            "Random game test $NP players #$j"
        );
    }
}

error_log("START TEST.");

header('Content-type: text/html; charset=utf-8');

progress("START TEST.");

create_and_init_test();
player_crud_test();
static_queries();
circuit_breaker_test();
game_test_exhaustive_3_and_corners();

foreach ($GAME_TYPES as $k => $v) {
    game_test($k);
}

progress("TEST DONE.");
error_log("TEST DONE.");

?>
