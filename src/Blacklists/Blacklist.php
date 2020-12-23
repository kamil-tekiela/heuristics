<?php

namespace Blacklists;

class Blacklist implements \ListOfWordsInterface {
	public $list = [];

	public function __construct() {
		$this->list = json_decode(file_get_contents(BASE_DIR.'/data/blacklists/blacklist.json'), true);
	}
}
