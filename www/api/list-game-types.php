<?php

require '../common/get_prelude.php';
require_once '../common/game_config.php';
require_once '../common/util.php';

http_response_code(HttpCode::OK);
print json_encode($GAME_TYPES);

?>
