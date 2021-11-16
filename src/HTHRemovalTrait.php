<?php

declare(strict_types=1);

use GuzzleHttp\Exception\RequestException;

trait HTHRemovalTrait {
	private function removeClutter(Post $post) {
		$editSummary = '';
		$count = 0;
		$bodyCleansed = $post->bodyMarkdown;

		$username = preg_quote($post->owner->display_name ?? '<BLANK USERNAME>', '/');
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
}
