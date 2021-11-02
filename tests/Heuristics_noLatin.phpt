<?php

define('BASE_DIR', realpath(__DIR__.'/..'));
include BASE_DIR.'/vendor/autoload.php';


// false case
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
      "body_markdown": "Did you ever find a good solution for this issue? I am facing the same problem.",
      "body": "<p>Did you ever find a good solution for this issue? I am facing the same problem.<\/p>"
    
    
}
JSON;

$json = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

$post = new Post($json);
$h = new Heuristics($post);

assert($h->noLatinLetters() === 0);

// 2 case
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

assert($h->noLatinLetters() === 2);

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
    "body_markdown": "\u0411\u041B\u042F\u0422\u042C, \u041A\u0415\u041D\u0442 \u0421 \u042D\u0422\u0418\u041C \u041A\u041E\u0414\u041E\u041C\r\n\r\n    X_train = array[:,0:22]\r\n    Y_train = array[:,22]\r\n    \r\n    X_test = testarray[:,0:22]\r\n    Y_test = testarray[:,22]\r\n\r\n\u0422\u042B \u0411\u041B\u042F\u0422\u042C \u041A\u0420\u0410\u0421\u0410\u0412\u0410 \u041D\u0410\u0425\u0423\u0419 \u041F\u0418\u0417\u0414\u0415\u0426 \u0411\u041B\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u0422\u042C\r\n\u0412\u0421\u0401 \u0417\u0410\u0420\u0410\u0411\u041E\u0422\u0410\u041B\u041E \u041D\u0410\u0425\u0423\u0419\r\n\u0415\u0411\u0410\u041D\u042B\u0419 \u041F\u0418\u0422\u041E\u041D, \u042F \u0415\u0413\u041E \u0412\u0422\u041E\u0420\u041E\u0419 \u0414\u0415\u041D\u042C \u0422\u041E\u041B\u042C\u041A\u041E \u0423\u0427\u0423, \u0410 \u0422\u0423\u0422 \u0410\u041D\u0410\u041B\u0418\u0417 \u0414\u0410\u041D\u041D\u042B\u0425 \u041F\u0418\u0414\/\u0417\u0414\u0410 \u0411\u041B\u042F\u0422\u042C",
      "body": "<p>\u0422\u042B \u0411\u041B\u042F\u0422\u042C \u041A\u0420\u0410\u0421\u0410\u0412\u0410 \u041D\u0410\u0425\u0423\u0419 \u041F\u0418\u0417\u0414\u0415\u0426 \u0411\u041B\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u042F\u0422\u042C\r\n\u0412\u0421\u0401 \u0417\u0410\u0420\u0410\u0411\u041E\u0422\u0410\u041B\u041E \u041D\u0410\u0425\u0423\u0419\r\n\u0415\u0411\u0410\u041D\u042B\u0419 \u041F\u0418\u0422\u041E\u041D, \u042F \u0415\u0413\u041E \u0412\u0422\u041E\u0420\u041E\u0419 \u0414\u0415\u041D\u042C \u0422\u041E\u041B\u042C\u041A\u041E \u0423\u0427\u0423, \u0410 \u0422\u0423\u0422 \u0410\u041D\u0410\u041B\u0418\u0417 \u0414\u0410\u041D\u041D\u042B\u0425 \u041F\u0418\u0414\/\u0417\u0414\u0410 \u0411\u041B\u042F\u0422\u042C<\/p>"
    
    
}
JSON;

$json = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

$post = new Post($json);
$h = new Heuristics($post);

assert($h->noLatinLetters() === 3);

echo 'PASSED';
