<?php

use ParagonIE\EasyDB\EasyDB;

class Heuristics {
	/**
	 * The answer
	 *
	 * @var \Post
	 */
	private $item;

	public function __construct(\Post $post) {
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
		if (preg_match_all('#((?<!\S)@[[:alnum:]][-\'[:word:]]{2,})[[:punct:]]*(?!\S)|(\buser\d+\b)#iu', $this->item->bodyWithoutCodeAndWithoutLinks, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $e) {
				$m[] = ['Word' => $e[1] ?: $e[2]];
			}
		}
		return $m;
	}

	public function CompareAgainstBlacklist(ListOfWordsInterface $bl) {
		$m = [];

		// or regex method
		foreach ($bl->list as ['Word' => $regex, 'Weight' => $weight]) {
			if (preg_match_all('#'.$regex.'#i', $this->item->body, $matches, PREG_SET_ORDER)) {
				foreach (array_unique(array_column($matches, 0)) as $e) {
					$m[] = ['Word' => $e, 'Weight' => $weight];
				}
			}
		}

		return $m;
	}

	public function CompareAgainstRegexList(ListOfWordsInterface $bl) {
		$m = [];

		foreach ($bl->list as ['Word' => $regex, 'Weight' => $weight]) {
			if (preg_match_all('#'.$regex.'#i', $this->item->body, $matches, PREG_SET_ORDER)) {
				foreach (array_unique(array_column($matches, 0)) as $e) {
					$m[] = ['Word' => $e, 'Weight' => $weight];
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
		if ($this->item->owner->reputation < 10000) {
			return -1;
		}
		return -2;
	}

	public function endsInQuestion() {
		return preg_match('#\?(?:[.\s<\/p>]|Thanks|Thank you|thx|thanx|Thanks in Advance)*$#', $this->item->body)
			|| preg_match('#\?\s*(?:\w+[!\.,:()\s]*){0,3}$#', $this->item->bodyWithoutCodeAndWithoutLinks);
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

	public function badStart(): array {
		$return = preg_match_all(
			'#^(?:(?:Is|Was)(?:n\'?t)?\h+(?:there|a|this|that|it|the)\b|(?:can(?:\'t)?)\h*(?:there|here|it|this|that|you|u|I|one|(?:some|any)(?:\h*one|\h*body)?)?\h*|(?:In)?(?:What\b|wah?t\b|How\b|Who\b|When\b|Where\b|Which\b|Why|Did)\s*(?:\'s|(?:is|was|were|do|did|does|are|would|has(?:\s+been)?)(?:n\'t)?|type|kind|if|can(?:\'t)?)?)\h*(?:(?:one|are|am|is|as|add|(?:some|any)(?:\h*one|\h*body)?|a(?:n(?:other)?)?\b|not|its?|some|this|that|these|those|the|You|to|we|have|of|in|i\b|for|on|with|please|help|me|who|can|not|now|cos|share|post|give|code|also|use|find|solve|fix|answer|solution)\h*)*#i',
			strip_tags($this->item->bodyWithoutCode),
			$m1,
			PREG_SET_ORDER
		);

		$m = [];
		if ($return) {
			if (is_array($m1)) {
				foreach ($m1 as $e) {
					// there should only ever be one
					$m = ['Word' => $e[0]];
				}
			}
		}

		return $m;
	}

	public function noLatinLetters() {
		$subject = strip_tags($this->item->body);
		preg_match_all(
			'#[a-z]#iu',
			$subject,
			$m1,
		);

		$uniqueAZ = count(array_unique($m1[0]));

		return $uniqueAZ <= 1 || count($m1[0]) / mb_strlen($subject) < 0.08;
	}

	public function hasRepeatingChars() {
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
					$m[] = ['Word' => $e[0]];
				}
			}
		}

		return $m;
	}

	public function lowEntropy() {
		$prob = 0;
		$data = strip_tags($this->item->body);
		$len = mb_strlen($data);
		$chars = mb_str_split($data);
		$chc = array_count_values($chars);
		foreach ($chars as $char) {
			$prob -= log($chc[$char] / $len, 2);
		}

		return $prob / $len <= 2.2;
	}
}
