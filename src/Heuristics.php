<?php

use ParagonIE\EasyDB\EasyDB;

class Heuristics {
	/**
	 * The answer
	 *
	 * @var \Post
	 */
	private $item;

	/**
	 * DB link
	 *
	 * @var EasyDB
	 */
	private $db;

	public function __construct(EasyDB $db, \Post $post) {
		$this->db = $db;
		$this->item = $post;
	}

	public function PostLengthUnderThreshold(): float {
		$text = strip_tags(preg_replace('#\s*<a.*?>.*?<\/a>\s*#s', '', $this->item->body));
		$bodyLength = mb_strlen($text);
		if ($bodyLength < 40) {
			return 2;
		}
		if ($bodyLength < 60) {
			return 1.75;
		}
		if ($bodyLength < 90) {
			return 1.5;
		}
		if ($bodyLength < 130) {
			return 1.25;
		}
		if ($bodyLength < 180) {
			return 1;
		}
		if ($bodyLength < 240) {
			return 0.75;
		}
		if ($bodyLength < 310) {
			return 0.5;
		}
		if ($bodyLength < 500) {
			return 0.0;
		}
		if ($bodyLength < 1000) {
			return -0.5;
		}
		return -1.0;
	}

	public function hasNoCode() {
		return stripos($this->item->body, '<code>') === false;
	}

	public function HighLinkProportion(): bool {
		$proportionThreshold = 0.55;     // as in, max 55% of the answer can be links

		$linkRegex = '#<a\shref="([^"]*)"(.*?)>(.*?)</a>#i';
		preg_match_all($linkRegex, $this->item->bodyWithoutCode, $matches, PREG_SET_ORDER);

		if ($matches) {
			// var_dump("[AWC.HighLinkProportion] id " . $this->item->id . " has matches:");
			// var_dump($matches);
			$linkLength = 0;

			foreach ($matches as $link) {    // This only matches link titles, not the entire HTML.
				$linkLength += mb_strlen($link[0]);
			}

			return ($linkLength / mb_strlen($this->item->bodyWithoutCode)) >= $proportionThreshold;
		} else {
			return false;
		}
	}

	public function ContainsSignature() {
		return mb_stripos($this->item->bodyWithoutCode, $this->item->owner->display_name) !== false;
	}

	public function MeTooAnswer() {
		// https://regex101.com/r/xEn0Rc/5
		$r1 = '(\b(?:i\s+(?:am\s+)?|i\'m\s+)?(?:also\s+)?(?:(?<!was\s)(?:face?|have?|get+)(?:ing)?\s+)?)(?:had|faced|solved|was\s(?:face?|have?|get+)(?:ing))\s+((?:exactly\s+)?(?:the\s+|a\s+)?(?:exact\s+)?(?:same\s+|similar\s+)(?:problem|question|issue|error))(*SKIP)(*F)|(\b(?1)(?2))';
	
		$m = [];
		if (preg_match_all('#'.$r1.'#i', $this->item->body, $m1, PREG_SET_ORDER)) {
			foreach (array_unique(array_column($m1, 0)) as $e) {
				$m[] = ['Word' => $e, 'Type' => 'MeTooAnswer'];
			}
		}

		return $m;
	}

	public function userMentioned() {
		$m = [];
		if (preg_match_all('#(?:^@\S{3,})|(?:(?<!\S)@\S{3,})|(?:\buser\d+\b)#i', strip_tags(preg_replace('#\s*<a.*?>.*?<\/a>\s*#s', '', $this->item->bodyWithoutCode)), $matches, PREG_SET_ORDER)) {
			foreach ($matches as $e) {
				$m[] = ['Word' => $e[0], 'Type' => 'userMentioned'];
			}
		}
		return $m;
	}

	public function CompareAgainstBlacklist(ListOfWordsInterface $bl) {
		$matches = [];
		foreach ($bl->list as $word) {
			if (stripos($this->item->body, $word['Word']) !== false) {
				$matches[] = $word;
			}
		}
		return $matches;
	}

	public function regexBlacklist(ListOfWordsInterface $bl) {
		$m = [];

		foreach ($bl->list as ['Word' => $regex, 'Weight' => $weight]) {
			if (preg_match_all('#'.$regex.'#i', $this->item->body, $matches, PREG_SET_ORDER)) {
				foreach (array_unique(array_column($matches, 0)) as $e) {
					$m[] = ['Word' => $e, 'Type' => 'RegEx Blacklist', 'Weight' => $weight];
				}
			}
		}

		return $m;
	}

	public function OwnerRepFactor() {
		if (!isset($this->item->owner->reputation) || $this->item->owner->reputation < 50) {
			return 1;
		}
		if ($this->item->owner->reputation < 1000) {
			return 0.5;
		}
		if ($this->item->owner->reputation < 2000) {
			return 0;
		}
		return -1;
	}

	public function endsInQuestion() {
		return preg_match('#\?(?:[.\s<\/p>]|Thanks|Thank you|thx|thanx|Thanks in Advance)*$#', $this->item->body)
			|| preg_match('#\?\s*(?:\w+[!\.,:()\s]*){0,3}$#', strip_tags(preg_replace('#\s*<a.*?>.*?<\/a>\s*#s', '', $this->item->bodyWithoutCode)));
	}

	public function containsQuestion() {
		return mb_stripos(preg_replace('#\s*<a.*?>.*?<\/a>\s*#s', '', $this->item->bodyWithoutCode), '?') !== false;
	}

	public function containsNoWhiteSpace() {
		$matches = [];
		$body = trim(strip_tags($this->item->body));
		preg_match_all('#(\s)+#', $body, $matches, PREG_SET_ORDER);
		return count($matches) <= 3;
	}

	public function badStart() {
		$return = preg_match_all(
			'#^(?:(?:(?:Is|Was)(?:n\'?t)?\s+(?:there|a|this|that|it|the)|(?:can(?:\'t)?)\s*(?:there|here|it|this|that|you|u|I|one|(?:some|any)(?:\s*one|\s*body)?)?\s*|(?:In)?(?:What\b|waht\b|wat\b|How\b|Who\b|When\b|Where\b|Which\b|Why|Did)\s*(?:\'s|(?:is|was|were|do|did|does|are|would|has(?:\s+been)?)(?:n\'t)?|type|kind|if|can(?:\'t)?)?)\s*(?:(?:one|are|am|is|as|add|(?:some|any)(?:\s*one|\s*body)?|a(?:n(?:other)?)?\b|not|its|it|some|this|that|these|those|the|You|to|we|have|of|in|i\b|for|on|with|please|help|me|who|can|not|now|cos|share|post|give|code|also|use|find|solve|fix|answer)\s*)*|same\s*(?:here|problem|question|issue|doubt|for me|also)*)#i',
			strip_tags($this->item->bodyWithoutCode),
			$m1,
			PREG_SET_ORDER
		);

		$m = [];
		if ($return) {
			if (is_array($m1)) {
				foreach ($m1 as $e) {
					$m[] = ['Word' => $e[0], 'Type' => 'StartsWithAQuestion'];
				}
			}
		}
	
		return $m;
	}

	function noLatinLetters() {
		preg_match_all(
			'#[a-z]#iu',
			strip_tags($this->item->body),
			$m1,
		);

		return count(array_unique($m1[0])) <= 1;
	}

	function hasRepeatingChars() {
		$return = preg_match_all(
			'#(\S)\1{7,}#iu',
			strip_tags($this->item->bodyWithoutCode),
			$m1,
			PREG_SET_ORDER
		);

		$m = [];
		if ($return) {
			if (is_array($m1)) {
				foreach ($m1 as $e) {
					$m[] = ['Word' => $e[0], 'Type' => 'FillerText'];
				}
			}
		}
	
		return $m;
	}
}
