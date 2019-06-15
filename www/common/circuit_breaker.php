<?php

$SCRIPT_START_TIME = time();
$CIRCUIT_BREAKER_LONGEST_REJECT_SEC = 20;
$MAX_SCRIPT_TIME_SEC = 3;
$CIRCUIT_BREAKER_PATH = '/tmp/minuniq_circuit_breaker_file';
$CIRCUIT_BREAKER_REJECT_SEC = 10;

if (file_exists($CIRCUIT_BREAKER_PATH)) {
  $cbtime = fileatime($CIRCUIT_BREAKER_PATH);
  if ($cbtime !== false && $cbtime > $SCRIPT_START_TIME) {
    if ($cbtime > $SCRIPT_START_TIME + $CIRCUIT_BREAKER_LONGEST_REJECT_SEC) {
      // Reject time is far in the future, reset.
      unlink($CIRCUIT_BREAKER_PATH);
    } else {
      error_log('Rejecting request, server overload.');
      http_response_code(503);
      die(json_encode(array("error" => 'Server overload')));
    }
  }
}

function circuit_breaker_epilog() {
  global $SCRIPT_START_TIME, $MAX_SCRIPT_TIME_SEC, $CIRCUIT_BREAKER_REJECT_SEC,
    $CIRCUIT_BREAKER_PATH;
  $now = time();
  if ($now - $SCRIPT_START_TIME > $MAX_SCRIPT_TIME_SEC) {
    // Script took too long to execute, reject request for a while.
    $t = $now + $CIRCUIT_BREAKER_REJECT_SEC;
    touch($CIRCUIT_BREAKER_PATH, $t, $t);
  }
}

?>
