<?php

class Owner {
	public int $user_id;
	public string $user_type;
	public string $display_name;
	public int $reputation;

	public function __construct(stdClass $json) {
		$this->user_id = $json->user_id;
		$this->user_type = $json->user_type;
		$this->display_name = $json->display_name;
		$this->reputation = $json->reputation;
	}
}
