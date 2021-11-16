<?php

namespace Blacklists;

class Blacklist implements \ListOfWordsInterface {
	private $list = [];

	public function __construct() {
		$this->list = json_decode(file_get_contents(BASE_DIR.'/data/blacklists/blacklist.json'), true);
	}

	public function getList(): array {
		return $this->list;
	}
}
