<?php

use App\Services\DiscordService;

$discord = app(DiscordService::class);
$threads = $discord->getActiveThreads('1542836975386624130');
if (empty($threads)) {
    echo "No threads found";
    exit;
}

foreach ($threads as $t) {
    $msgs = $discord->getChannelMessages($t['id']);
    foreach ($msgs as $m) {
        if (!empty($m['attachments'])) {
            echo "Thread: " . $t['name'] . "\n";
            echo json_encode(['content' => $m['content'], 'attachments' => $m['attachments']], JSON_PRETTY_PRINT) . "\n";
            exit;
        }
    }
}
echo "No attachments found in active threads.\n";
