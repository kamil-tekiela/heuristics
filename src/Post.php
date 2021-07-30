<?php

class Post {
	public int $id;

	public int $question_id;

	public string $bodySafe;

	public string $body;

	public string $bodyMarkdown;

	public string $bodyWithoutCode;

	public string $bodyStripped;

	public bool $is_accepted;

	public int $score;

	public \DateTime $creation_date;

	public string $link;

	public string $title;

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
		$this->owner = new Owner($json->owner);

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
