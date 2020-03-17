<?php

session_start();

include './vendor/autoload.php';

$domain = '.stackoverflow.com';

$siteUrl = 'https://stackoverflow.com';

$goutte = new \Goutte\Client();

$sessionCookieJar = new \GuzzleHttp\Cookie\FileCookieJar('chatAPI');
$client = new GuzzleHttp\Client([
	'cookies' => $sessionCookieJar
]);

foreach ($sessionCookieJar->toArray() as $cookie) {
	$goutte->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie($cookie['Name'], $cookie['Value'], null, $cookie['Path'], $cookie['Domain']));
}

$redirectedTo = '';
$rq = $client->request('GET', $siteUrl.'/users/login', [
	'cookies' => $sessionCookieJar,
	'on_stats' => function (GuzzleHttp\TransferStats $stats) {
		global $redirectedTo;
		$redirectedTo = $stats->getEffectiveUri()->__toString();
	}
]);
var_dump($redirectedTo, $siteUrl);
if ($redirectedTo !== $siteUrl.'/') {
	$crawler = $goutte->request('GET', $siteUrl.'/users/login');
	$elements = $crawler->filter('input[name="fkey"]')->each(function (Symfony\Component\DomCrawler\Crawler $node) {
		return $node->attr('value');
	});
	$fkey = $elements[0];

	$cookieJarG = $goutte->getCookieJar();
	$cookies = $cookieJarG->allValues($siteUrl);
	// $goutte->getCookieJar()->allValues($siteUrl);

	// $sessionCookieJar = \GuzzleHttp\Cookie\SessionCookieJar::fromArray($cookies, $domain);


	foreach ($cookies as $name => $value) {
		$sessionCookieJar->setCookie(new GuzzleHttp\Cookie\SetCookie([
			'Domain'  => $domain,
			'Name'    => $name,
			'Value'   => $value,
			'Discard' => true
		]));
	}

	$rq = $client->request('POST', $siteUrl.'/users/login', [
		'form_params' => [
			'fkey' => $fkey,
			'email' => '9tagbot9@gmail.com',
			'password' => 'Dharman246'
		]
	]);
	// echo $rq->getStatusCode();
	var_dump('Logged in!');
	// $rq->text();
	// var_dump($rq->getBody()->getContents());
	$sessionCookieJar->save('chatAPI');
}

///
// $rq = $client->request('GET', $siteUrl.'/users/login', [
// 	'cookies' => $sessionCookieJar,
// 	'on_stats' => function (GuzzleHttp\TransferStats $stats) {
// 		global $redirectedTo;
// 		$redirectedTo = $stats->getEffectiveUri()->__toString();
// 	}
// ]);
// var_dump($redirectedTo, $siteUrl);


/**
 *
 */
$crawler = $goutte->request('GET', 'https://chat.stackoverflow.com/rooms/209366');
$elements = $crawler->filter('#fkey')->each(function (Symfony\Component\DomCrawler\Crawler $node) {
	return $node->attr('value');
});
$fkey = $elements[0];
// var_dump('Chat: '.$fkey);

// Send message

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

$s = $sessionCookieJar->toArray();
foreach($s as $k=>$e)
$s[$k] = $e['Name'].'='.$e['Value'];
echo implode('; ', $s);

try {
	$rq = $client->request('POST', 'https://chat.stackoverflow.com/chats/209366/messages/new', [
		'cookies' => $sessionCookieJar,
		'headers' => [
			'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36',
			'Origin' => 'https://chat.stackoverflow.com',
			'Referer' => 'https://chat.stackoverflow.com/rooms/209366/testing-naa-bot',
			'X-Requested-With' => 'XMLHttpRequest',
			'Cookie' => 'chatusr=t=mXJxYem4jp5zqeg%2bePe9HyHKN8aM3p7P; uauth=true; rawr=78459b50fdcb02b18083c5d51da7d7d3806cb1342a83d8ef67684fc74e0630cc; acct=t=NAwD7QOm3ykX07TcTQlafF3aT%2fZX60U5&s=YY%2f59IB%2bJ%2f9LZ%2fDIkhC7DB69mOgKUDcR; prov=cb8e7c2e-2fd3-102f-6925-d35c56ce9a3c',
			'Host'=> 'chat.stackoverflow.com',
			'Accept' => 'application/json, text/javascript, */*; q=0.01',
			'Accept-Encoding' => 'gzip, deflate, br',
			'Accept-Language' => 'en-US,en;q=0.9',
			'Connection' => 'keep-alive'

		],
		'form_params' => [
			'text' => 'test',
			'fkey' => $fkey
		]
	]);
	echo $rq->getBody()->getContents();
} catch (RequestException $e) {
	file_put_contents('headers.txt', print_r($e->getRequest()->getHeaders(), 1));
	// file_put_contents('headers.txt', print_r($rq->getHeaders(),1));
	// echo $e->getResponse();
	throw new Exception(Psr7\str($e->getResponse()));
}
