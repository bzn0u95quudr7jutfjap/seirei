<?php

const map = 0;
const filter = 3;
const join = 6;
const values = 7;

function stream($collection, $pipeline) {
  $ris = [];
  foreach ($collection as $k => $c) {
    foreach ($pipeline as $line) {
      [$op, $arg] = $line;
      switch ($op) {
        case map:
          $c = $arg($k, $c);
          break;
        case filter:
          if ($arg($k, $c)) {
            break;
          } else {
            continue 3;
          }
        default:
          break;
      }
    }
    $ris[$k] = $c;
  }
  [$op, $arg] = $pipeline[array_key_last($pipeline)];
  return match ($op) {
    join => implode($arg, $ris),
    values => array_values($ris),
    default => $ris,
  };
}


var_dump(
  stream([0, 1, 2, 3, 4, 5, 6], [
    [filter, fn ($_, $x) => $x % 2 == 0],
    [map, fn ($_, $x) => 2 ** $x],
    [join, " - "],
    //[values, null],
  ])
);
