<?php

class Post {
	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var int
	 */
	public $question_id;

	/**
	 * @var string
	 */
	public $bodySafe;

	/**
	 * @var string
	 */
	public $body;

	/**
	 * @var string
	 */
	public $bodyMarkdown;

	/**
	 * @var string
	 */
	public $bodyWithoutCode;

	/**
	 * @var string
	 */
	public $bodyStripped;

	/**
	 * @var bool
	 */
	public $is_accepted;

	/**
	 * @var int
	 */
	public $score;

	/**
	 * @var \DateTime
	 */
	public $creation_date;

	/**
	 * @var string
	 */
	public $link;

	/**
	 * @var string
	 */
	public $title;

	public $owner;

	public function __construct(\stdClass $json) {
		$this->id = $json->answer_id;
		$this->question_id = $json->question_id;
		$this->is_accepted = $json->is_accepted;
		$this->score = $json->score;
		$this->creation_date = date_create_from_format('U', $json->creation_date);
		$this->link = $json->link;
		$this->title = $json->title;
		/** HTML ready version of the post */
		$this->bodySafe = $json->body;
		$this->body = $json->body;
		/** Used in automatic edits */
		$this->bodyMarkdown = $json->body_markdown;
		$this->owner = $json->owner;

		$this->bodyWithoutCode = preg_replace('#\s*(?:<pre>)?<code>.*?<\/code>(?:<\/pre>)?\s*#s', '', $this->body);
		$this->bodyStripped = $this->stripAndDecode($this->removeLinks($this->bodyWithoutCode));
	}

	public function stripAndDecode(string $str) {
		return trim(htmlspecialchars_decode(strip_tags($str)));
	}

	public function removeLinks(string $str) {
		return preg_replace('#\s*<a.*?>.*?<\/a>\s*#s', '', $str);
	}
}
