<?php

$game_types = array(
  array(
    "name" => "threesome",
    "players" => 3,
    "description" => "Quick game against 2 opponents."
  ),
  array(
    "name" => "longdozen",
    "players" => 13,
    "description" => "Serious game, serious prize against 12 opponents."
  ),
  array(
    "name" => "bigwin",
    "players" => 101,
    "description" => "Win big against a hundred!"
  )
);

$MAX_PICKED_NUMBER = 9999;
$BET_AMOUNT = 1.0;

function is_valid_picked_number($picked_number) {
  return isint($picked_number) && 1 <= $picked_number && $picked_number <= $MAX_PICKED_NUMBER;
}

?>
