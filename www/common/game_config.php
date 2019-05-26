<?php

$GAME_TYPES = array(
  array(
    "name" => "triad",
    "num-players" => 3,
    "description" => "Quick game against 2 opponents."
  ),
  array(
    "name" => "longdozen",
    "num-players" => 13,
    "description" => "Serious game, serious prize against 12 opponents."
  ),
  array(
    "name" => "bigwin",
    "num-players" => 101,
    "description" => "Win big against a hundred!"
  )
);

$MAX_PICKED_NUMBER = 9999;
$BET_AMOUNT = 1.0;

function is_valid_picked_number($picked_number)
{
    global $MAX_PICKED_NUMBER;
    return is_numeric($picked_number)
      && intval($picked_number) == $picked_number
      && 1 <= $picked_number && $picked_number <= $MAX_PICKED_NUMBER
      && strval(intval($picked_number)) === "$picked_number";
}

?>
