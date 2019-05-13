<?php

$request_method = $_SERVER['REQUEST_METHOD'];
$content_type = $_SERVER['CONTENT_TYPE'];

print "request_method: $request_method<br>";
print "content_type: $content_type<br>";
print_r($_POST);
print "<br>";
print_r($_GET);
print "<br>";

?>
