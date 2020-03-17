<?php

class Comment {
	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var string
	 */
	public $body;

	/**
	 * @var string
	 */
	public $bodyWithoutCode;

	/**
	 * @var \DateTime
	 */
	public $creation_date;

	/**
	 * @var string
	 */
	public $link;

	public $reply_to_user = null;

	public function __construct(\stdClass $json) {
		$this->id = $json->comment_id;
		$this->creation_date = date_create_from_format('U', $json->creation_date);
		$this->link = $json->link;
		$this->body = htmlspecialchars_decode($json->body, ENT_QUOTES | ENT_HTML401);
		$this->reply_to_user = $json->reply_to_user ?? null;

		$this->bodyWithoutCode = preg_replace('#\s*(?:<pre>)?<code>.*?<\/code>(?:<\/pre>)?\s*#s', '', $this->body);
	}

	public function getGratitude(): array {
		if (preg_match_all('#(?:(?:big\s+)?thank(?:s|\s*you|\s*u)?(?:\s+a lot|\s+very much|\s+so much|\s+a million|\s+)?(?:\s*for (?:your|the)?(?:\s+help)?)?|thanx|thx|cheers)[!\.,:()\s]*(?:\w+[!\.,:()\s]*)?#i', $this->bodyWithoutCode, $matches, PREG_SET_ORDER)) {
			return array_column($matches, 0);
		}
		return [];
	}

	public function userMentioned() {
		$m = [];
		if (preg_match_all('#(?:^@\S*)|(?:(?<!\S)@\S*)|(?:\buser\d+\b)#i', strip_tags(preg_replace('#\s*<a.*?>.*?<\/a>\s*#s', '', $this->bodyWithoutCode)), $matches, PREG_SET_ORDER)) {
			$m = array_column($matches, 0);
		}
		// if($this->reply_to_user){
		// 	$m = [$this->reply_to_user->display_name];
		// }
		return $m;
	}

	public function itWorked(): array {
		if (preg_match_all('#(?:this\s+|that\s+|it\s+)?(?:solution\s+)?work(?:ed|s)?\s*(?:now|perfectly|great|for me)?[!\.:()\s]*#i', $this->bodyWithoutCode, $matches, PREG_SET_ORDER)) {
			return array_column($matches, 0);
		}
		return [];
	}

	public function yourWelcome(): array {
		if (preg_match_all('#(?:(?:your\s+|you\'re\s+|you are\s+)?welcome)+[!\.:()\s]*#i', $this->bodyWithoutCode, $matches, PREG_SET_ORDER)) {
			return array_column($matches, 0);
		}
		return [];
	}

	public function youHelpedMe(): array {
		if (preg_match_all('#(?:(?:I\s+)?(?:hope\s+)?(?:your\s+|(?:this\s+|that\s+|it\s+)(?:was\s+|is\s+)?)?(?:very\s+)?help(?:ful|ed|s)+(?:\s+a lot|\s+very much|\s+so much)?)+[!\.:()\s]*#i', $this->bodyWithoutCode, $matches, PREG_SET_ORDER)) {
			return array_column($matches, 0);
		}
		return [];
	}

	public function updated(): array {
		if (preg_match_all('#(?:I\s+)?(?:updated|edited|fixed)+\s*(?:my|the|a)?\s*(?:answer|question|it|that|this)?[!\.:()\s]*#i', $this->bodyWithoutCode, $matches, PREG_SET_ORDER)) {
			return array_column($matches, 0);
		}
		return [];
	}

	public function excitement(): array {
		if (preg_match_all('#(?:wonderful|Excellent|Marvelous|awesome|(?:You )?saved my\s+\w+)+[!\.:()\s]*#i', $this->bodyWithoutCode, $matches, PREG_SET_ORDER)) {
			return array_column($matches, 0);
		}
		return [];
	}

	public function lifeSaver(): array {
		if (preg_match_all('#(?:You(?:\'re|\s*are) )?a life saver[!\.:()d=\s]*#i', $this->bodyWithoutCode, $matches, PREG_SET_ORDER)) {
			return array_column($matches, 0);
		}
		return [];
	}

	public function noiseToSize(array $reasons) {
		$size = array_reduce($reasons, function ($carry, $item) {return $carry + strlen($item); });
		$totalLenght = strlen($this->body);
		return $size / $totalLenght;
	}
}
