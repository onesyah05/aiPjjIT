<?php

use App\Models\Conversation;
use App\Models\User;
use App\Services\Knowledge\RetrievalService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$webUser = User::first();
$webConv = new Conversation(['course_id' => null, 'user_id' => $webUser->id]);

$discordUser = User::first() ?? new User(['id' => 1]);
$discordConv = new Conversation(['course_id' => null, 'user_id' => $discordUser->id]);

$retriever = app(RetrievalService::class);
$webRes = $retriever->retrieve($webUser, $webConv, 'hari ini ada agenda apa?', 5);
$discordRes = $retriever->retrieve($discordUser, $discordConv, 'hari ini ada agenda apa?', 15); // limit is 15 on discord now

echo 'WEB RETRIEVAL COUNT: '.$webRes->count()."\n";
foreach ($webRes as $i => $r) {
    echo 'Web '.($i + 1).' Score: '.$r['score'].' Title: '.$r['chunk']->version->knowledge->title."\n";
}

echo "\nDISCORD RETRIEVAL COUNT: ".$discordRes->count()."\n";
foreach ($discordRes as $i => $r) {
    echo 'Discord '.($i + 1).' Score: '.$r['score'].' Title: '.$r['chunk']->version->knowledge->title."\n";
}
