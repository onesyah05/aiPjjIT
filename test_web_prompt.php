<?php

use App\Models\Conversation;
use App\Models\User;
use App\Services\Knowledge\KnowledgeLinkExtractor;
use App\Services\Knowledge\RetrievalService;
use App\Services\PromptBuilderService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// We need a web conversation that has course_id = null (Jawaban umum)
$dummyUser = User::first() ?? new User(['id' => 1]);
$dummyConversation = new Conversation(['course_id' => null, 'user_id' => $dummyUser->id, 'mode' => 'general']);

$question = 'hari ini ada agenda apa?';

$retrievalService = app(RetrievalService::class);
$promptBuilder = app(PromptBuilderService::class);
$linkExtractor = app(KnowledgeLinkExtractor::class);

// Web uses default limit (5)
$retrieved = $retrievalService->retrieve($dummyUser, $dummyConversation, $question); // default is 5

// Web passes an empty array for new conversation
$recentMessages = [];

$prompt = $promptBuilder->build(
    $question,
    $retrieved->map(fn ($r) => [
        'content' => $r['content'],
        'score' => $r['score'],
        'links' => $linkExtractor->extract($r['chunk']->version?->content ?? $r['content']),
    ])->all(),
    $dummyConversation->mode,
    $recentMessages,
);

echo 'RETRIEVED COUNT: '.$retrieved->count()."\n";
echo 'PROMPT LENGTH: '.strlen($prompt)."\n";
echo "PROMPT:\n";
echo "====================================\n";
echo $prompt;
echo "\n====================================\n";
