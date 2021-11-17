<?php

declare(strict_types=1);

namespace Entities;

class Natty {
	public ?float $score = null;
	public ?string $type = null;

	public function __construct(?float $score = null, ?string $type = null) {
		$this->score = $score;
		$this->type = $type;
	}
}
