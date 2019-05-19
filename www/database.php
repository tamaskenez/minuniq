<?php

require_once 'util.php';

class DbConfig {
  const LOCAL_HOST = "localhost";
  const LOCAL_USERNAME = "root";
  const LOCAL_PASSWORD = "rootroot";
  const LOCAL_DB_NAME = "minuniq";
  const TEST_DB_NAME = "minuniq_test";
  const USE_TEST_DB_FIELD = "use-test-db";
}

class MySql {
  const ER_DUP_ENTRY = 1062;
}

function get_db_name($test) {
  if ($test) {
    return DbConfig::TEST_DB_NAME;
  } else {
    if (isset($_SERVER['RDS_HOSTNAME'])) {
      return $_SERVER['RDS_DB_NAME'];
    } else {
      return DbConfig::LOCAL_DB_NAME;
    }
  }
}

function get_db_dsn($dbname_selector) {
  $charset = 'utf8';
  if (isset($_SERVER['RDS_HOSTNAME'])) {
    $dbhost = $_SERVER['RDS_HOSTNAME'];
    $dbport = $_SERVER['RDS_PORT'];
  } else {
    $dbhost = DbConfig::LOCAL_HOST;
    $dbport = null;
  }

  if ($dbname_selector == 'none') {
    $dbname = null;
  } else {
    $dbname = get_db_name($dbname_selector == 'test');
  }

  $dsn = "mysql:host={$dbhost};charset={$charset}";
  if (!is_null($dbport)) {
    $dsn = $dsn . ";port={$dbport}";
  }
  if (!is_null($dbname)) {
    $dsn = $dsn . ";dbname={$dbname}";
  }
  return $dsn;
}

function new_pdo($dsn) {
  if (isset($_SERVER['RDS_HOSTNAME'])) {
    $username = $_SERVER['RDS_USERNAME'];
    $password = $_SERVER['RDS_PASSWORD'];
  } else {
    $username = DbConfig::LOCAL_USERNAME;
    $password = DbConfig::LOCAL_PASSWORD;
  }
  return new PDO($dsn, $username, $password);
}

function open_connection_without_db() {
  try {
    return new_pdo(get_db_dsn('none'));
  } catch(Exception $exc) {
    http_response_code(HttpCode::SERVICE_UNAVAILABLE);
    die(json_encode(array(
      "error" => "Can't connect to database.",
      "message" => $exc->getMessage())));
  }
}

function open_db_1($test) {
  try {
    return new_pdo(get_db_dsn($test ? 'test' : 'main'));
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

function drop_and_init_db($test) {
  try {
    $db = open_connection_without_db();


    $name = get_db_name($test);

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
