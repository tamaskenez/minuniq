<?php

require_once 'util.php';

class DbConfig {
  const HOST = "localhost";
  const USERNAME = "root";
  const PASSWORD = "root";
  const DB_NAME = "minuniq";
  const TEST_DB_NAME = "minuniq_test";
  const USE_TEST_DB_FIELD = "use-test-db";
}

class MySql {
  const ER_DUP_ENTRY = 1062;
}

function open_db_1($test) {
  $name = $test ? DbConfig::TEST_DB_NAME : DbConfig::DB_NAME;

  $host = DbConfig::HOST;
  $username = DbConfig::USERNAME;
  $password = DbConfig::PASSWORD;

  try {
    return new PDO("mysql:host=$host;dbname=$name", $username, $password);
  } catch(Exception $exc) {
    http_response_code(503);
    die(json_encode(array(
      "error" => "Can't connect to database.",
      "message" => $exc->getMessage())));
  }
}

function open_db() {
  if (isset($_POST[DbConfig::USE_TEST_DB_FIELD])) {
    $test = $_POST[DbConfig::USE_TEST_DB_FIELD];
  } else if (isset($_GET[DbConfig::USE_TEST_DB_FIELD])) {
    $test = $_GET[DbConfig::USE_TEST_DB_FIELD];
  } else {
    $test = FALSE;
  }

  return open_db_1($test);
}

function open_connection_without_db() {
  $host = DbConfig::HOST;
  $username = DbConfig::USERNAME;
  $password = DbConfig::PASSWORD;

  try {
    return new PDO("mysql:host=$host", $username, $password);
  } catch(Exception $exc) {
    http_response_code(HttpCode::SERVICE_UNAVAILABLE);
    die(json_encode(array(
      "error" => "Can't connect to database.",
      "message" => $exc->getMessage())));
  }
}

function create_empty_test_db() {
  try {
    $db = open_connection_without_db();

    $name = DbConfig::TEST_DB_NAME;

    $db->query("DROP DATABASE $name"); // May fail.

    $stmt = $db->query(
      "CREATE DATABASE $name DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");

    assert_or_die($stmt,
      HttpCode::SERVICE_UNAVAILABLE, "Can't create test database.");
  } catch(Exception $exc) {
    http_response_code(HttpCode::SERVICE_UNAVAILABLE);
    die("Can't create test database: " . $exc->getMessage());
  }
}

function run_db_init_script($db) {
  $path = $_SERVER['DOCUMENT_ROOT'] . '/create_tables.sql';
  $commands = file_get_contents($path);
  if ($commands === FALSE) {
    die("Can't read file: $path");
  }
  try {
    $db->exec($commands);
  } catch (Exception $exc) {
    die($exc->getMessage());
  }
}

function select_player_for_update_or_null($db, $email) {
  $stmt = $db->prepare(
    "SELECT player_id, balance FROM player WHERE email=:email FOR UPDATE");
  $stmt->bindParam(':email', $email);

  $r = $stmt->execute();
  assert_or_die($r !== FALSE,
    HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]);

  $row = $stmt->fetch(PDO::FETCH_NUM);

  if ($row === FALSE) {
    return NULL;
  }
  $player_id = $row[0];
  $balance = $row[1];

  assert_or_die(is_numeric($balance),
    HttpCode::INTERNAL_SERVER_ERROR, "Player balance is not numeric in database.");

  return array(
    'player_id' => $player_id,
    'balance' => floatval($balance));
}

function select_player_balance_for_update_or_null($db, $email) {
  $bp = select_player_for_update_or_null($db, $email);
  return is_null($bp) ? NULL : $bp['balance'];
}

function checked_execute_query($stmt) {
  $r = $stmt->execute();
  if ($r === FALSE) {
    print "<br>DPB<br>";
    debug_print_backtrace();
    print "<br>";
    var_dump($stmt->errorInfo());
    print "<br>";
  }
  assert_or_die_msg($r !== FALSE,
    HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]);
}

function db_table_size($db, $table) {
  $stmt = $db->query("SELECT COUNT(1) FROM $table");
  $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : FALSE;
  if ($row === FALSE) {
    die("Can't query table size for " . $table);
  }
  return $row[0];
}

?>
