<?php

ini_set('memory_limit', '5048M');
define('BASE_DIR', realpath(__DIR__.''));

include BASE_DIR.'/vendor/autoload.php';

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/data/dbnew.db'
]);

file_put_contents('input.txt', implode(PHP_EOL, $db->column('SELECT Body FROM reports WHERE Score >=6')));


$text = file_get_contents('input.txt');
$text = preg_replace('#<pre.*?>.*?</pre>#s', "", $text);
$text = strip_tags($text);
$text = preg_replace('#[\n\r]{2,}#', "\n", $text);
$text = preg_replace('#\h{2,}#', " ", $text);
$text = mb_strtolower($text);
file_put_contents('stripped.txt', $text);