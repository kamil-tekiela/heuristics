<?php

namespace Blacklists;

use ParagonIE\EasyDB\EasyDB;

class Whitelist implements \ListOfWordsInterface {
	public $list = [];

	public function __construct() {
		$this->list = json_decode(file_get_contents(BASE_DIR.'/data/blacklists/whitelist.json'), true);
	}
}
