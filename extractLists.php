<?php

define('BASE_DIR', realpath(__DIR__.'/.'));

include BASE_DIR.'/vendor/autoload.php';

// $dotEnv = new DotEnv();
// $dotEnv->load(BASE_DIR.'/config.ini');
// define('DEBUG', (bool) $dotEnv->get('DEBUG'));

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/data/db.db'
]);


$bl = $db->run("SELECT * FROM blacklist WHERE Type<>'whitelist' AND Type<>'regex' ");
file_put_contents(BASE_DIR.'/data/blacklists/blacklist.json', json_encode($bl, JSON_PRETTY_PRINT));

$bl = $db->run("SELECT * FROM blacklist WHERE Type='whitelist'");
file_put_contents(BASE_DIR.'/data/blacklists/whitelist.json', json_encode($bl, JSON_PRETTY_PRINT));

$bl = $db->run("SELECT * FROM blacklist WHERE Type='regex'");
file_put_contents(BASE_DIR.'/data/blacklists/regex.json', json_encode($bl, JSON_PRETTY_PRINT));