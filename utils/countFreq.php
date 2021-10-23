<?php

ini_set('memory_limit', '5048M');
$text = file_get_contents('stripped.txt');

$words = preg_match_all('|(?:\b[\w\']+(?=[^\w\'])\h*){3,}(?<! )|u', $text, $m);
unset($text);


$counts = array_count_values($m[0]);
$counts = array_filter($counts, function($a){return $a>1;});
arsort($counts);
$out = [];
foreach($counts as $ph => $fr){
	$out[] = $fr."\t".$ph;
}
file_put_contents('phrases.txt', implode(PHP_EOL, $out));
// var_dump($counts);