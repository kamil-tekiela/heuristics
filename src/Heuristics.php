<?php

class Heuristics {
	private \Post $item;

	public function __construct(\Post $post) {
		$this->item = $post;
	}

	public function PostLengthUnderThreshold(): float {
		$text = $this->item->stripAndDecode($this->item->removeLinks($this->item->body));
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
		$linkRegex = '#<a\shref="(?:[^"]*)"(?:[^>]*)>(.*?)</a>#i';
		preg_match_all($linkRegex, $this->item->bodyWithoutCode, $matches, PREG_SET_ORDER);

		if (!$matches) {
			return false;
		}

		$proportionThreshold = 0.33;     // as in, max 33% of the answer's text can be links
		$linkLength = 0;
		foreach ($matches as $link) {    // This only matches link titles, not the entire HTML.
			$linkLength += mb_strlen($link[1]);
		}

		return ($linkLength / mb_strlen($this->item->stripAndDecode($this->item->bodyWithoutCode)) ?: 1) >= $proportionThreshold;
	}

	public function ContainsSignature() {
		return mb_stripos($this->item->bodyWithoutCode, $this->item->owner->display_name) !== false;
	}

	public function MeTooAnswer() {
		// https://regex101.com/r/xEn0Rc/5
		$r1 = '(\b(?:i\s+(?:am\s+)?|i\'m\s+)?(?:also\s+)?(?:(?<!was\s)(?:face?|have?|get+)(?:ing)?\s+)?)(?:had|faced|solved|was\s(?:face?|have?|get+)(?:ing))\s+((?:exactly\s+)?(?:the\s+|a\s+)?(?:exact\s+)?(?:same\s+|similar\s+)(?:problem|question|issue|error))(*SKIP)(*F)|(\b(?1)(?2))';

		$m = [];
		if (preg_match_all('#'.$r1.'#i', $this->item->stripAndDecode($this->item->body), $m1, PREG_SET_ORDER)) {
			foreach (array_unique(array_column($m1, 0)) as $e) {
				$m[] = ['Word' => $e, 'Type' => 'MeTooAnswer'];
			}
		}

		return $m;
	}

	public function userMentioned() {
		$m = [];
		if (preg_match_all('#((?<!\S)@[[:alnum:]][-\'[:word:]]{2,})[[:punct:]]*(?!\S)|(\buser\d+\b)#iu', $this->item->bodyStripped, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $e) {
				$m[] = ['Word' => $e[1] ?: $e[2]];
			}
		}
		return $m;
	}

	public function CompareAgainstRegexList(ListOfWordsInterface $bl) {
		$m = [];

		$haystack = $this->item->stripAndDecode($this->item->body);
		foreach ($bl->list as ['Word' => $regex, 'Weight' => $weight]) {
			if (preg_match_all('#'.$regex.'#i', $haystack, $matches, PREG_SET_ORDER)) {
				foreach (array_unique(array_column($matches, 0)) as $e) {
					$m[] = ['Word' => $e, 'Weight' => $weight];
				}
			}
		}

		return $m;
	}

	public function OwnerRepFactor() {
		if (is_null($this->item->owner->reputation) || $this->item->owner->reputation < 50) {
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
		return preg_match('#\?(?:[.!\s]|Thanks( in Advance)?|Thank you|thx|thanx)*$#i', $this->item->stripAndDecode($this->item->body))
			|| preg_match('#\?\s*(?:\w+[!\.,:()\s]*){0,3}$#', $this->item->bodyStripped);
	}

	public function containsQuestion() {
		return mb_stripos($this->item->bodyStripped, '?') !== false;
	}

	public function containsNoWhiteSpace() {
		$matches = [];
		$body = $this->item->stripAndDecode($this->item->body);
		preg_match_all('#(\s)+#', $body, $matches, PREG_SET_ORDER);
		return count($matches) <= 3;
	}

	public function badStart(): array {
		$return = preg_match_all(
			'#^(?:(?:Is|Was)(?:n\'?t)?\h+(?:there|a|this|that|it|the)\b|(?:can(?:\'t)?)\h*(?:there|here|it|this|that|you|u|I|one|(?:some|any)(?:\h*one|\h*body)?)?\h*|(?:In)?(?:What\b|wah?t\b|How\b|Who\b|When\b|Where\b|Which\b|Why|Did)\s*(?:\'s|(?:is|was|were|do|did|does|are|would|has(?:\s+been)?)(?:n\'t)?|type|kind|if|can(?:\'t)?)?)\h*(?:(?:one|are|am|is|as|add|(?:some|any)(?:\h*one|\h*body)?|a(?:n(?:other)?)?\b|not|its?|some|this|that|these|those|the|You|to|we|have|of|in|i\b|for|on|with|please|help|me|who|can|not|now|cos|share|post|give|code|also|use|find|solve|fix|answer|solution)\h*)*#i',
			$this->item->bodyStripped,
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

	public function noLatinLetters(): float {
		preg_match_all(
			'#[a-z\d ]#iu',
			$this->item->stripAndDecode($this->item->body),
			$nonLatinLettersWithCode,
		);

		// For example nonsense in the code block or link only
		$uniqueAZ = count(array_unique($nonLatinLettersWithCode[0]));
		if ($uniqueAZ <= 1) {
			return 3.5;
		}

		// If the body without links and code has too little text to check then skip
		if (mb_strlen($this->item->bodyStripped) < 5) {
			return 0;
		}

		preg_match_all(
			'#[a-z\d ]#iu',
			$this->item->bodyStripped,
			$nonLatinLetters,
		);

		$uniqueAZ = count(array_unique($nonLatinLetters[0]));
		if ($uniqueAZ <= 1) {
			return 3.0;
		}

		$ratio = count($nonLatinLetters[0]) / mb_strlen($this->item->bodyStripped);
		if ($ratio < 0.1) {
			return 3.0;
		}
		if ($ratio < 0.3) {
			return 2.0;
		}
		if ($ratio < 0.4) {
			return 1.0;
		}
		return 0;
	}

	public function hasRepeatingChars() {
		$return = preg_match_all(
			'#(\S)\1{7,}#iu',
			$this->item->stripAndDecode($this->item->bodyWithoutCode),
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
		$data = $this->item->stripAndDecode($this->item->body);
		$len = mb_strlen($data);
		$chars = mb_str_split($data);
		$chc = array_count_values($chars);
		foreach ($chars as $char) {
			$prob -= log($chc[$char] / $len, 2);
		}

		return $prob / $len <= 2.2;
	}

	public function looksLikeComment(): bool {
		$body = $this->item->stripAndDecode($this->item->bodyWithoutCode);
		return 1 === preg_match('#(?s:((hi|hello|thanks|thank you)\h+)?(@|^\w+[,.]+).*?\?($|.*?(best|thank|regards)))|^((hi|hello|thanks|thank you)\h+)?@.+$#i', $body);
	}
}
