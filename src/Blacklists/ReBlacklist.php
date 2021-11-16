<?php

namespace Blacklists;

class ReBlacklist implements \ListOfWordsInterface {
	private array $list = [];

	public function __construct() {
		$this->list = json_decode(file_get_contents(BASE_DIR.'/data/blacklists/regex.json'), true);
	}

	public function getList(): array {
		return $this->list;
	}
}
