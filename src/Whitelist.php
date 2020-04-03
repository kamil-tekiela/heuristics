<?php

use ParagonIE\EasyDB\EasyDB;

class Whitelist implements ListOfWordsInterface {
	public $list = [];

	public function __construct(EasyDB $db) {
		$this->list = $db->run("SELECT * FROM blacklist WHERE Type='whitelist'");
	}
}
