<?php

use Flags\Flags;

define('BASE_DIR', realpath(__DIR__.'/..'));

define('PERPAGE', 100);
define('REPORT_URL', 'https://bot.dharman.net/reports.php');

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/data/db.db'
]);

$controller = new Flags($db);

$page = $_GET['page'] ?? 1;

$flags = $controller->fetch($page);
$flag_count = $controller->getCount();

$maxPage = ceil(($flag_count ?? 0) / PERPAGE);

$title = 'Flagged posts';

include 'views/header.phtml';
include 'views/flags.phtml';
include 'views/footer.phtml';
