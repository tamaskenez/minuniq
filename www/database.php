<?php

require_once 'util.php';

class DbConfig {
  const HOST = "localhost";
  const USERNAME = "root";
  const PASSWORD = "root";
  const DB_NAME = "minuniq";
  const TEST_DB_NAME = "minuniq_test";
}

function open_db($test) {
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
  $commands = file_get_contents("create_tables.sql");
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
  assert_or_die($r,
    HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]);

  $row = $stmt->fetch(PDO::FETCH_NUM);
  if (!$row) {
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
  assert_or_die_msg($r,
    HttpCode::SERVICE_UNAVAILABLE, "Can't execute query.", $stmt->errorInfo()[2]);
}

function db_table_size($db, $table) {
  $stmt = $db->query("SELECT COUNT(1) FROM $table");
  $row = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : FALSE;
  if (!$row) {
    die("Can't query table size for " . $table);
  }
  return $row[0];
}

?>
