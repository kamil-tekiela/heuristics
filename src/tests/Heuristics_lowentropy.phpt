<?php

define('BASE_DIR', realpath(__DIR__.'/../..'));
include BASE_DIR.'/vendor/autoload.php';

$json = <<<'JSON'
{
    "owner": {
    "reputation": 1,
    "user_id": 14570844,
    "user_type": "unregistered",
    "profile_image": "https://www.gravatar.com/avatar/d4e403f79e47035b0351e119f50e7e13?s=128&d=identicon&r=PG",
    "display_name": "Yura",
    "link": "https://stackoverflow.com/users/14570844/yura"
    },
    "is_accepted": false,
    "score": -1,
    "creation_date": 1604409410,
    "answer_id": 64663596,
    "question_id": 61782798,
    "link": "https://stackoverflow.com/questions/61782798/gas-error-typeerror-cannot-read-property-getlastrow-of-null-line-4-file-c/64663596#64663596",
    "title": "GAS error: TypeError: Cannot read property 'getLastRow' of null (line 4, file \"Code2\")",
      "body_markdown": "\r\n\r\n&lt;!-- begin snippet: js hide: false console: true babel: false --&gt;\r\n\r\n&lt;!-- language: lang-html --&gt;\r\n\r\n    &lt;button&gt;x&lt;sup&gt;3&lt;/sup&gt;&lt;/button&gt;\r\n\r\n&lt;!-- end snippet --&gt;\r\n\r\n",
      "body": "<p>看日志，redis服务器的终止应该是受到了父进程的SIGTERM信号，导致主动关闭。SIGTERM信号的产生一般有几种情况，reboot，内存不足。建议实时看看内存使用情况调节配置参数。</p>"
    
    
}
JSON;

$json = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

$post = new Post($json);
$h = new Heuristics($post);

assert($h->lowEntropy() === false);

// True case

$json = <<<'JSON'
{
    "owner": {
    "reputation": 1,
    "user_id": 14570844,
    "user_type": "unregistered",
    "profile_image": "https://www.gravatar.com/avatar/d4e403f79e47035b0351e119f50e7e13?s=128&d=identicon&r=PG",
    "display_name": "Yura",
    "link": "https://stackoverflow.com/users/14570844/yura"
    },
    "is_accepted": false,
    "score": -1,
    "creation_date": 1604409410,
    "answer_id": 64663596,
    "question_id": 61782798,
    "link": "https://stackoverflow.com/questions/61782798/gas-error-typeerror-cannot-read-property-getlastrow-of-null-line-4-file-c/64663596#64663596",
    "title": "GAS error: TypeError: Cannot read property 'getLastRow' of null (line 4, file \"Code2\")",
      "body_markdown": "\r\n\r\n&lt;!-- begin snippet: js hide: false console: true babel: false --&gt;\r\n\r\n&lt;!-- language: lang-html --&gt;\r\n\r\n    &lt;button&gt;x&lt;sup&gt;3&lt;/sup&gt;&lt;/button&gt;\r\n\r\n&lt;!-- end snippet --&gt;\r\n\r\n",
      "body": "<p>yeryeryeretrregererergretertertertertert4rteretrettreer5tretertertertretetreetrr yteerrrrrrrr</p>"
    
    
}
JSON;


$json = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

$post = new Post($json);
$h = new Heuristics($post);

assert($h->lowEntropy() === true);