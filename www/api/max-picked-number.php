<?php

require '../common/circuit_breaker.php';

require_once '../common/game_config.php';
require_once '../common/util.php';

add_get_headers();

http_response_code(HttpCode::OK);
print json_encode($MAX_PICKED_NUMBER);

circuit_breaker_epilog();

?>
