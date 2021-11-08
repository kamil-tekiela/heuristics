<?php

declare(strict_types=1);

use Dharman\ChatAPI;
use Dharman\StackAPI;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use ParagonIE\EasyDB\EasyDB;

class AnswerAPI {
	private EasyDB $db;
	private Client $client;

	/**
	 * Timestamp
	 *
	 * @var mixed
	 */
	private $lastRequestTime;

	/**
	 * Time of last auto-flagging
	 */
	private ?\DateTime $lastFlagTime = null;

	private array $questions = [];

	private const AUTOFLAG_TRESHOLD = 6;
	private const NATTY_FLAG_TRESHOLD = 7;
	private const CHAT_TRESHOLD = 4;

	/**
	 * Token for Dharman user. Secret!
	 */
	private string $userToken = '';

	/**
	 * My app key. Not secret
	 */
	private $app_key = null;

	private ChatAPI $chatAPI;
	private StackAPI $stackAPI;
	private int $logRoomId;
	private ?int $personalRoomId = null;
	private bool $logEdits = false;
	private int $soboticsRoomId = 111347;
	private string $pingOwner = '';
	private bool $autoflagging = false;
	private bool $autoediting = false;

	public function __construct(EasyDB $db, \GuzzleHttp\Client $client, StackAPI $stackAPI, ChatAPI $chatAPI, DotEnv $dotEnv) {
		$this->db = $db;
		$this->client = $client;
		$this->chatAPI = $chatAPI;
		$this->stackAPI = $stackAPI;
		$this->lastRequestTime = $this->db->single('SELECT `time` FROM lastRequest');
		if (!$this->lastRequestTime) {
			$this->lastRequestTime = strtotime('15 minutes ago');
		}
		if (DEBUG_OLD) {
			$this->lastRequestTime = strtotime(DEBUG_OLD);
		}

		$this->pingOwner = $dotEnv->get('pingOwner');

		$this->autoflagging = $dotEnv->get('autoflagging');

		$this->autoediting = $dotEnv->get('autoediting');

		$this->app_key = $dotEnv->get('app_key');

		$this->userToken = $dotEnv->get('key');
		if (!$this->userToken) {
			throw new \Exception('Please login first and provide valid user token!');
		}
		$this->logRoomId = (int) $dotEnv->get('logRoomId');
		if (!$this->logRoomId) {
			throw new \Exception('Please provide valid room ID!');
		}
		$this->personalRoomId = (int) $dotEnv->get('trackRoomId');
		$this->logEdits = (bool) $dotEnv->get('logEdits');

		// Say hello
		$this->chatAPI->sendMessage($this->logRoomId, 'v.'.\VERSION.' Started on '.gethostname());
	}

	/**
	 * Entry point. Fetches a bunch of answers and their questions and then parses them.
	 *
	 * @return void
	 */
	public function fetch() {
		$apiEndpoint = 'answers';
		$url = "https://api.stackexchange.com/2.2/" . $apiEndpoint;
		if (DEBUG) {
			$url .= '/'.DEBUG;
		}
		$args = [
			'key' => $this->app_key,
			'todate' => strtotime('5 minutes ago'),
			'site' => 'stackoverflow',
			'order' => 'asc',
			'sort' => 'creation',
			'filter' => 'Ds7AAhmsA*_R*_GN_PLRT2uskVNwru'
		];
		if (DEBUG) {
			$args['fromdate'] = strtotime('20 years ago');
		} else {
			$args['fromdate'] = $this->lastRequestTime + 1;
		}

		echo(date_create_from_format('U', (string) $args['fromdate'])->format('Y-m-d H:i:s')). ' to '.(date_create_from_format('U', (string) $args['todate'])->format('Y-m-d H:i:s')).PHP_EOL;

		// Request answers
		$contents = $this->stackAPI->request('GET', $url, $args);

		// prepare blacklist
		$blacklist = new \Blacklists\Blacklist();
		$whitelist = new \Blacklists\Whitelist();
		$reBlacklist = new \Blacklists\ReBlacklist();

		// Collect question Ids
		$questions = [];
		foreach ($contents->items as $postJSON) {
			$questions[] = $postJSON->question_id;
		}
		$questions = array_unique($questions);
		if ($questions) {
			$this->loadQuestions($questions);
		}

		foreach ($contents->items as $postJSON) {
			$post = new \Post($postJSON);
			$h = new \Heuristics($post);
			$reasons = [];
			$triggers = [];
			$score = 0;

			if ($m = $h->CompareAgainstRegexList($blacklist)) {
				$reasons[] = 'Blacklisted phrase:"'.implode('","', array_column($m, 'Word')).'"';
				$score += array_sum(array_column($m, 'Weight'));
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Blacklisted phrase', 'value' => $bl['Word'], 'weight' => $bl['Weight']];
				}
			}

			if ($m = $h->CompareAgainstRegexList($whitelist)) {
				$reasons[] = 'Whitelisted phrase:"'.implode('","', array_column($m, 'Word')).'"';
				$score += array_sum(array_column($m, 'Weight'));
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Whitelisted phrase', 'value' => $bl['Word'], 'weight' => $bl['Weight']];
				}
			}

			if ($m = $h->CompareAgainstRegexList($reBlacklist)) {
				$reasons[] = 'RegEx Blacklisted phrase:"'.implode('","', array_column($m, 'Word')).'"';
				$score += array_sum(array_column($m, 'Weight'));
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'RegEx Blacklisted phrase', 'value' => $bl['Word'], 'weight' => $bl['Weight']];
				}
			}

			if ($h->HighLinkProportion()) {
				$reasons[] = 'Probably link only';
				$score += 1;
				$triggers[] = ['type' => 'Probably link only', 'weight' => 1];
			}

			if ($h->ContainsSignature()) {
				$reasons[] = 'Contains signature';
				$score += 1;
				$triggers[] = ['type' => 'Contains signature', 'weight' => 1];
			}

			if ($lenFactor = $h->PostLengthUnderThreshold()) {
				$lenFactor = floor($lenFactor * 2) / 2; // round to nearest half
				if ($lenFactor > 0) {
					$reasons[] = 'Low length ('.$lenFactor.')';
					$triggers[] = ['type' => 'Low length', 'weight' => $lenFactor];
				} else {
					$reasons[] = 'Long answer ('.$lenFactor.')';
					$triggers[] = ['type' => 'Long answer', 'weight' => $lenFactor];
				}
				$score += $lenFactor;
			}

			if ($h->hasNoCode()) {
				$reasons[] = 'No code block';
				$score += 0.5;
				$triggers[] = ['type' => 'No code block', 'weight' => 0.5];
			} else {
				$score += -0.5;
				$triggers[] = ['type' => 'Has code block', 'weight' => -0.5];
			}

			if ($m = $h->MeTooAnswer()) {
				$reasons[] = 'Me too answer:"'.implode('","', array_column($m, 'Word')).'"';
				$weight = 2;
				$score += $weight;
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Me too answer', 'value' => $bl['Word'], 'weight' => $weight];
					$weight = 0;
				}
			}

			if ($m = $h->endsInQuestion()) {
				$reasons[] = 'Ends in question mark';
				$score += 2.0;
				$triggers[] = ['type' => 'Ends in question mark', 'weight' => 2];
			} elseif ($m = $h->containsQuestion()) {
				$reasons[] = 'Contains question mark';
				$score += 0.5;
				$triggers[] = ['type' => 'Contains question mark', 'weight' => 0.5];
			}

			if ($post->owner->user_type === 'unregistered') {
				$reasons[] = 'Unregistered user';
				$score += 0.5;
				$triggers[] = ['type' => 'Unregistered user', 'weight' => 0.5];
			}

			if ($m = $h->userMentioned()) {
				$reasons[] = 'User mentioned:"'.implode('","', array_column($m, 'Word')).'"';
				$weight = 1;
				$score += $weight;
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'User mentioned', 'value' => $bl['Word'], 'weight' => $weight];
					$weight = 0;
				}
			}

			if (isset($this->questions[$post->question_id]['owner']) && $this->questions[$post->question_id]['owner'] === $post->owner->user_id) {
				$reasons[] = 'Self-answer';
				$score += 0.5;
				$triggers[] = ['type' => 'Self-answer', 'weight' => 0.5];
			}

			if ($h->containsNoWhiteSpace()) {
				$reasons[] = 'Has no white space';
				$score += 0.5;
				$triggers[] = ['type' => 'Has no white space', 'weight' => 0.5];
			}

			if ($bw = $h->badStart()) {
				$reasons[] = 'Starts with a question:"'.$bw['Word'].'"';
				$score += 0.5;
				$triggers[] = ['type' => 'Starts with a question', 'value' => $bw['Word'], 'weight' => 0.5];
			}

			if ($m = $h->noLatinLetters()) {
				$reasons[] = 'No latin characters';
				$score += $m;
				$triggers[] = ['type' => 'No latin characters', 'weight' => $m];
			}

			if ($m = $h->hasRepeatingChars()) {
				$reasons[] = 'Filler text:"'.implode('","', array_column($m, 'Word')).'"';
				$weight = 0.5;
				$score += $weight;
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Filler text', 'value' => $bl['Word'], 'weight' => $weight];
					$weight = 0;
				}
			}

			if ($m = $h->lowEntropy()) {
				$reasons[] = 'Low entropy';
				$score += 1;
				$triggers[] = ['type' => 'Low entropy', 'weight' => 1];
			}

			if ($m = $h->looksLikeComment()) {
				$reasons[] = 'Looks like a comment';
				$score += 1;
				$triggers[] = ['type' => 'Looks like a comment', 'weight' => 1];
			}

			if ($reasons) {
				if ($repFactor = $h->OwnerRepFactor()) {
					if ($repFactor > 0) {
						$reasons[] = 'Low reputation';
						$triggers[] = ['type' => 'Low reputation', 'weight' => $repFactor];
					} else {
						$triggers[] = ['type' => 'High reputation', 'weight' => $repFactor];
					}
					$score += $repFactor;
				}
				if ($score > 0) {
					try {
						$this->reportAndLog($reasons, $score, $post, $triggers);
					} catch (\Exception $e) {
						ErrorHandler::handler($e);
					}
				}
			}

			// while we are at it check if there is fluff to be removed
			if ($score < self::AUTOFLAG_TRESHOLD) {
				try {
					$this->removeClutter($post);
				} catch (\Exception $e) {
					ErrorHandler::handler($e);
				}
			}

			// set last request
			$this->lastRequestTime = $post->creation_date->format('U');
		}

		// end processing
		echo 'Processing finished at: '.date_create()->format('Y-m-d H:i:s').PHP_EOL.PHP_EOL;
		// save request time
		if (!DEBUG) {
			$this->db->run('UPDATE lastRequest SET `time` = ? WHERE rowid=1', $this->lastRequestTime);
		}
	}

	private function loadQuestions(array $questions) {
		if ($questions) {
			$questionList = implode(';', $questions);
			$url = "https://api.stackexchange.com/2.2/questions/" . $questionList;
			$args = [
				'key' => $this->app_key,
				'site' => 'stackoverflow',
				'order' => 'desc',
				'sort' => 'creation',
				'filter' => '4b*l8uK*lxO_LpAroX(a'
			];

			// Get questions
			$questionsJSON = $this->stackAPI->request('GET', $url, $args);

			$this->questions = [];
			foreach ($questionsJSON->items as $postJSON) {
				if (isset($postJSON->owner->user_id)) {
					$this->questions[$postJSON->question_id]['owner'] = $postJSON->owner->user_id;
				}
				$this->questions[$postJSON->question_id]['creation_date'] = $postJSON->creation_date;
			}
		}
	}

	/**
	 * Log the report into a file and chat room.
	 * Flag the post if score is higher or equal to the threshold
	 */
	private function reportAndLog(array $reasons, float $score, \Post $post, array $triggers): void {
		$summary = implode(';', $reasons);
		$line = $post->link.PHP_EOL;
		$line .= $score."\t".$summary.PHP_EOL;
		if (DEBUG) {
			echo $line;
		}

		$shouldBeReportedByNatty = date_create_from_format('U', (string) $this->questions[$post->question_id]['creation_date'])->modify('+ 30 days') < $post->creation_date;
		$natty_score = ($shouldBeReportedByNatty && $score >= self::CHAT_TRESHOLD) ? $this->isReportedByNatty($post->id) : null;

		// Report to file
		$this->reportToFile($score, $shouldBeReportedByNatty, $line);

		// report to DB
		if (!DEBUG) {
			$report_id = $this->logToDB($post, $score, $summary, $natty_score, $triggers);
		} else {
			$report_id = '0';
		}

		if ($score < self::CHAT_TRESHOLD) {
			return;
		}

		// report to Chat
		$chatLine = '[tag:'.$score.'] [Link to Post]('.$post->link.') [ [Report]('.REPORT_URL.'?id='.$report_id.') ]'."\t".implode('; ', $reasons);
		[$flagIcon, $actionTaken] = ['', ''];
		if ($score >= self::AUTOFLAG_TRESHOLD) {
			if (!$shouldBeReportedByNatty) {
				['icon' => $flagIcon, 'action' => $actionTaken] = $this->flagPost($post->id);
			} elseif ($natty_score >= self::NATTY_FLAG_TRESHOLD) {
				// If Natty flagged it, then do nothing. The post was not handled yet...
				[$flagIcon, $actionTaken] = ['🐶', 'Flagged by Natty'];
			} elseif ($natty_score >= 4) {
				// If score is above 7 and Natty was not confident to autoflag then let us flag it unless it is weekend.
				if ($score >= 7 || date('N') >= 6) {
					['icon' => $flagIcon, 'action' => $actionTaken] = $this->flagPost($post->id);
				} else {
					[$flagIcon, $actionTaken] = ['🏳️', 'Not flagged'];
				}
			} else {
				try {
					if (!DEBUG && !DEBUG_OLD) {
						// Natty missed it, report to Natty in SOBotics and flag the answer
						$reportNatty = "@Natty report https://stackoverflow.com/a/{$post->id}";
						$this->chatAPI->sendMessage($this->soboticsRoomId, $reportNatty);
						$reportLink = REPORT_URL.'?id='.$report_id;
						$reportNatty = "[Report link]({$reportLink})";
						if ($this->pingOwner) {
							$reportNatty .= ' @'.$this->pingOwner;
						}
						$this->chatAPI->sendMessage($this->soboticsRoomId, $reportNatty);
					}
				} finally {
					// flag it ourselves
					['icon' => $flagIcon, 'action' => $actionTaken] = $this->flagPost($post->id);
				}
			}
		}

		if ($flagIcon) {
			$chatLine = $flagIcon.' '.$chatLine;
		}
		if ($actionTaken) {
			$chatLine .= ' — '.$actionTaken;
		}
		$this->chatAPI->sendMessage($this->logRoomId, $chatLine);
	}

	private function logToDB(Post $post, float $score, string $summary, ?float $natty_score, array $triggers, int $retries = 0): string {
		try {
			$this->db->beginTransaction();
			$report_id = $this->db->insertReturnId(
				'reports',
				[
					'answer_id' => $post->id,
					'body' => $post->bodySafe,
					'score' => $score,
					'natty_score' => $natty_score,
					'summary' => $summary,
					'reported_at' => date('Y-m-d H:i:s'),
					'user_id' => $post->owner->user_id,
					'username' => $post->owner->display_name,
				]
			);

			foreach ($triggers as $trigger) {
				$this->db->insert('reasons', [
					'report_id' => $report_id,
					'type' => $trigger['type'] ?? null,
					'value' => $trigger['value'] ?? null,
					'weight' => $trigger['weight'] ?? null
				]);
			}
			$this->db->commit();
		} catch (PDOException $e) {
			$this->db->rollBack();
			if ($retries === 5) {
				throw $e;
			}
			return $this->logToDB($post, $score, $summary, $natty_score, $triggers, $retries + 1);
		}

		return $report_id;
	}

	/**
	 * Calls Natty API to see if Natty has a report for this answer.
	 * If there is no report or the score was lower than Natty's threshold then it means it was not reported.
	 */
	private function isReportedByNatty(int $answerId): ?float {
		$rq = $this->client->get('https://logs.sobotics.org/napi/api/feedback/'.$answerId);
		$nattyJSON = json_decode($rq->getBody()->getContents());
		return $nattyJSON->items[0]->naaValue ?? null;
	}

	/**
	 * Calls Stack API to get possible flag options and then cast NAA flag
	 *
	 * @return string[]
	 */
	private function flagPost(int $question_id): array {
		if (!$this->autoflagging) {
			return ['icon' => '🏳️', 'action' => '@Dharman Autoflagging is switched off'];
		}

		// throttle
		if ($this->lastFlagTime && $this->lastFlagTime >= ($now = date_create('5 seconds ago'))) {
			sleep($now->diff($this->lastFlagTime)->s + 1);
			echo 'Slept: '. $now->diff($this->lastFlagTime)->s .' seconds'.PHP_EOL;
		}
		$this->lastFlagTime = new DateTime();

		$url = 'https://api.stackexchange.com/2.2/answers/'.$question_id.'/flags/options';

		$args = [
			'key' => $this->app_key,
			'site' => 'stackoverflow',
			'access_token' => $this->userToken, // Dharman
		];

		// Get flag options
		try {
			$contentsJSON = $this->stackAPI->request('GET', $url, $args);
		} catch (ClientException $e) {
			ErrorHandler::handler($e);
			return ['icon' => '🏳️', 'action' => 'Error calling API'];
		}

		$option_id = null;
		foreach ($contentsJSON->items as $option) {
			if ($option->title == 'not an answer') {
				$option_id = $option->option_id;
				break;
			}
		}

		if (!$option_id) {
			return ['icon' => '🏳️', 'action' => '@Dharman Flagging as NAA not possible'];
		}

		$url = 'https://api.stackexchange.com/2.2/answers/'.$question_id.'/flags/add';

		$args += [
			'option_id' => $option_id,
			'preview' => true
		];

		// Cast NAA flag
		try {
			$this->stackAPI->request('POST', $url, $args);
		} catch (ClientException $e) {
			$response = $e->getResponse();
			if ($response && false !== strpos(json_decode((string) $response->getBody())->error_message, 'already flagged')) {
				return ['icon' => '', 'action' => 'Already manually flagged'];
			} else {
				ErrorHandler::handler($e);
				return ['icon' => '🏳️', 'action' => 'Error calling API'];
			}
		}

		return ['icon' => '🚩', 'action' => 'Post auto-flagged'];
	}

	private function removeClutter(Post $post) {
		$editSummary = '';
		$count = 0;
		$bodyCleansed = $post->bodyMarkdown;

		$username = preg_quote($post->owner->display_name, '/');
		$re = '/(*ANYCRLF)						# $ matches both \r and \n
			((?<=\.)|\s*^)\s*					# space before
			[*]*								# Optional bolding in markdown
			(?:									# Alternative HTH
				(I\h)?hope\h(it|this|that)
				(\hwill\b|\hcan\b|\hmay\b)?
				\hhelps?
				(\h(you|someone(?:\h*else)?)\b)?
				|HTH
				|HIH
			)
			[*]*								# Optional bolding in markdown
			(\s*(:-?\)|🙂️|[!.;,\h]))*			# punctuation and emoji
			# sometimes appears on the same line or next, so we catch the newline before
			(\s*(cheers|good\h?luck|thank(?:s|\hyou))(\s*(:-?\)|🙂️|[!.;,])\h*)*)?
			(?:[-~\s]*'.$username.')?
			$/mix';
		$bodyCleansed = preg_replace($re, '', $bodyCleansed, -1, $count);
		if ($count) {
			$editSummary .= 'Stack Overflow is like an encyclopedia, so we prefer to omit these types of phrases. It is assumed that everyone here is trying to be helpful. ';
		}

		$count = 0;
		$re = '/^Welcome to (SO|Stack\h*(Overflow|exchange))[!.\h]*\v+/i';
		$bodyCleansed = preg_replace($re, '', $bodyCleansed, -1, $count);
		if ($count) {
			$editSummary .= 'Please, do not add unnecessary fluff. ';
		}

		$count = 0;
		$re = '/((?<=\.)|\s*^)\s*(good ?luck)([!,. ]*)?\h*$/mi';
		$bodyCleansed = preg_replace($re, '', $bodyCleansed, -1, $count);
		if ($count) {
			$editSummary = 'https://meta.stackoverflow.com/questions/402167/are-superfluous-comments-in-an-answer-such-as-good-luck-discouraged ';
		}

		if ($bodyCleansed !== $post->bodyMarkdown) {
			// 'Something changed.'
			$this->performEdit($post, $bodyCleansed, $editSummary);
		}
	}

	private function performEdit(Post $post, string $bodyCleansed, string $editSummary) {
		if (!$this->autoediting) {
			$this->chatAPI->sendMessage($this->personalRoomId, "Please edit this answer: [Post link]({$post->link})");
			return;
		}

		$apiEndpoint = 'answers';
		$url = "https://api.stackexchange.com/2.2/" . $apiEndpoint;
		$url .= '/'.$post->id;
		$url .= '/edit';
		$args = [
			'key' => $this->app_key,
			'site' => 'stackoverflow',
			'filter' => 'Ds7AAhmsA*_R*_GN_PLRT2uskVNwru',
			'preview' => 'false',
			'access_token' => $this->userToken,
			'comment' => trim($editSummary),
		];

		$args['body'] = $bodyCleansed;

		if (mb_strlen(trim($bodyCleansed)) < 30) {
			$this->chatAPI->sendMessage($this->personalRoomId, "Please edit this answer: [Post link]({$post->link})");
			return;
		}

		try {
			$this->stackAPI->request('POST', $url, $args);
		} catch (RequestException $e) {
			$response = $e->getResponse();
			if ($response) {
				$jsonResponse = json_decode((string) $response->getBody());
				if ($jsonResponse->error_id == 407) {
					$this->chatAPI->sendMessage($this->personalRoomId, "Please edit this answer: [Post link]({$post->link})");
				}
			}
			throw $e;
		}

		if ($this->logEdits) {
			$this->chatAPI->sendMessage($this->personalRoomId, "Answer edited: [Post link]({$post->link})");
		}
	}

	private function reportToFile(float $score, bool $shouldBeReportedByNatty, string $line): void {
		if ($score >= self::AUTOFLAG_TRESHOLD) {
			if ($shouldBeReportedByNatty) {
				file_put_contents(BASE_DIR . '/logs/log_NATTY.txt', $line, FILE_APPEND);
			} else {
				file_put_contents(BASE_DIR . '/logs/log_autoflagged.txt', $line, FILE_APPEND);
			}
		} elseif ($score >= self::CHAT_TRESHOLD) {
			file_put_contents(BASE_DIR . '/logs/log_3.txt', $line, FILE_APPEND);
		} elseif ($score >= 2.5) {
			file_put_contents(BASE_DIR . '/logs/log_2_5.txt', $line, FILE_APPEND);
		} elseif ($score >= 1) {
			file_put_contents(BASE_DIR . '/logs/log_low.txt', $line, FILE_APPEND);
		} else {
			file_put_contents(BASE_DIR . '/logs/log_lessthan1.txt', $line, FILE_APPEND);
		}
	}
}
