<?php

define('BASE_DIR', realpath(__DIR__.'/.'));

include BASE_DIR.'/vendor/autoload.php';

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/data/blacklists.db'
]);

$bl = $db->run("SELECT Word, Weight FROM blacklist WHERE Type<>'whitelist' AND Type<>'regex' ");
foreach ($bl as $key => $item) {
	$bl[$key]['Word'] = preg_quote($item['Word'], '#');
}
file_put_contents(BASE_DIR.'/data/blacklists/blacklist.json', json_encode($bl, JSON_PRETTY_PRINT));

$bl = $db->run("SELECT Word, Weight FROM blacklist WHERE Type='whitelist'");
file_put_contents(BASE_DIR.'/data/blacklists/whitelist.json', json_encode($bl, JSON_PRETTY_PRINT));

$bl = $db->run("SELECT Word, Weight FROM blacklist WHERE Type='regex'");
file_put_contents(BASE_DIR.'/data/blacklists/regex.json', json_encode($bl, JSON_PRETTY_PRINT));
