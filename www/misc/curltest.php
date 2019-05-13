<?php

require_once 'util.php';

$http_host = $_SERVER['HTTP_HOST'];
print "http_host: $http_host<br>";
curl_request("$http_host/rest_debug.php", 'POST', array('key' => 'the_value'));

?>
