<?php

use ParagonIE\EasyDB\EasyDB;

class Blacklist {
	public $list = [];

	public function __construct(EasyDB $db) {
		$this->list = $db->run('SELECT * FROM blacklist');
	}
}
