<?php

define('BASE_DIR', realpath(__DIR__.'/../..'));
include BASE_DIR.'/vendor/autoload.php';

$json = <<<'JSON'
    {
      "owner": {
        "reputation": 106,
        "user_id": 6148636,
        "user_type": "registered",
        "profile_image": "https://www.gravatar.com/avatar/98b855d6344276205a3d491997f7a17c?s=128&d=identicon&r=PG",
        "display_name": "Mahmoudi MohamedAmine",
        "link": "https://stackoverflow.com/users/6148636/mahmoudi-mohamedamine"
      },
      "is_accepted": false,
      "score": 0,
      "last_activity_date": 1605360104,
      "creation_date": 1605360104,
      "answer_id": 64834349,
      "question_id": 64833268,
      "body_markdown": "you must be add the web.config\r\n\r\n```\r\n<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<configuration>\r\n  <location path=\".\" inheritInChildApplications=\"false\">\r\n    <system.webServer>\r\n\t\t<modules>\r\n\t\t<remove name=\"WebDAVModule\" />\r\n\t\t</modules>\r\n\t\t<handlers>\r\n\t\t\t<remove name=\"WebDAV\" />\r\n\t\t\t<add name=\"aspNetCore\" path=\"*\" verb=\"*\" modules=\"AspNetCoreModule\" resourceType=\"Unspecified\" />\r\n\t\t</handlers>\r\n      <aspNetCore processPath=\".\\{{your_App}}.exe\" stdoutLogEnabled=\"false\" stdoutLogFile=\".\\logs\\stdout\" hostingModel=\"InProcess\" />\r\n    </system.webServer>\r\n  </location>\r\n</configuration>\r\n```",
      "link": "https://stackoverflow.com/questions/64833268/cannot-reach-netcore-api-on-iis/64834349#64834349",
      "title": "Cannot reach .NETCore API on IIS",
      "body": "<p>you must be add the web.config</p>\n<pre><code>&lt;?xml version=&quot;1.0&quot; encoding=&quot;utf-8&quot;?&gt;\n&lt;configuration&gt;\n  &lt;location path=&quot;.&quot; inheritInChildApplications=&quot;false&quot;&gt;\n    &lt;system.webServer&gt;\n        &lt;modules&gt;\n        &lt;remove name=&quot;WebDAVModule&quot; /&gt;\n        &lt;/modules&gt;\n        &lt;handlers&gt;\n            &lt;remove name=&quot;WebDAV&quot; /&gt;\n            &lt;add name=&quot;aspNetCore&quot; path=&quot;*&quot; verb=&quot;*&quot; modules=&quot;AspNetCoreModule&quot; resourceType=&quot;Unspecified&quot; /&gt;\n        &lt;/handlers&gt;\n      &lt;aspNetCore processPath=&quot;.\\{{your_App}}.exe&quot; stdoutLogEnabled=&quot;false&quot; stdoutLogFile=&quot;.\\logs\\stdout&quot; hostingModel=&quot;InProcess&quot; /&gt;\n    &lt;/system.webServer&gt;\n  &lt;/location&gt;\n&lt;/configuration&gt;\n</code></pre>\n"
    }
JSON;

$json = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

$post = new Post($json);

$expectDecoded = <<<'DECODED'
you must be add the web.config
<?xml version="1.0" encoding="utf-8"?>
<configuration>
  <location path="." inheritInChildApplications="false">
    <system.webServer>
        <modules>
        <remove name="WebDAVModule" />
        </modules>
        <handlers>
            <remove name="WebDAV" />
            <add name="aspNetCore" path="*" verb="*" modules="AspNetCoreModule" resourceType="Unspecified" />
        </handlers>
      <aspNetCore processPath=".\{{your_App}}.exe" stdoutLogEnabled="false" stdoutLogFile=".\logs\stdout" hostingModel="InProcess" />
    </system.webServer>
  </location>
</configuration>
DECODED;

assert($post->stripAndDecode($post->body) === $expectDecoded);

$h = new Heuristics($post);

assert($h->lowEntropy() === false);

echo 'PASSED';
