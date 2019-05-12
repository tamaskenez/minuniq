<?php

require '../get_prelude.php';
require_once '../game_config.php';
require_once '../util.php';

http_response_code(HttpCode::OK);
print json_encode($game_types);

?>
