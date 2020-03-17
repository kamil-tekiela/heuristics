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
	public $body;

	/**
	 * @var string
	 */
	public $bodyWithoutCode;

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
		$this->body = $json->body;
		$this->owner = $json->owner;

		$this->bodyWithoutCode = preg_replace('#\s*(?:<pre>)?<code>.*?<\/code>(?:<\/pre>)?\s*#s', '', $this->body);
	}
}
