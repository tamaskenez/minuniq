<?php

require_once '../common/game_config.php';
require_once '../common/util.php';

add_get_headers();
http_response_code(HttpCode::OK);
print json_encode($GAME_TYPES);

?>
