<?php

use ParagonIE\EasyDB\EasyDB;

class Blacklist implements ListOfWordsInterface {
	public $list = [];

	public function __construct(EasyDB $db) {
		$this->list = $db->run("SELECT * FROM blacklist WHERE Type<>'whitelist'");
	}
}
