<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

$time = date('r');
$num = rand();
echo "data: The server time is: {$time}, $num\n\n";
flush();
?>
