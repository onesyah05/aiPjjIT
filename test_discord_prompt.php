<?php

use App\Models\Conversation;
use App\Models\User;
use App\Services\AI\GeminiService;
use App\Services\CredentialPoolService;
use App\Services\Knowledge\KnowledgeLinkExtractor;
use App\Services\Knowledge\RetrievalService;
use App\Services\PromptBuilderService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dummyUser = User::first() ?? new User(['id' => 1]);
$dummyConversation = new Conversation(['course_id' => null, 'user_id' => $dummyUser->id]);

$question = 'info group AIK dong';

$retrievalService = app(RetrievalService::class);
$promptBuilder = app(PromptBuilderService::class);
$linkExtractor = app(KnowledgeLinkExtractor::class);

$retrieved = $retrievalService->retrieve($dummyUser, $dummyConversation, $question, 15);

$prompt = $promptBuilder->build(
    $question,
    $retrieved->map(fn ($r) => [
        'content' => $r['content'],
        'score' => $r['score'],
        'links' => $linkExtractor->extract($r['chunk']->version?->content ?? $r['content']),
    ])->all(),
    'general',
    [],
);

$geminiService = app(GeminiService::class);
$pool = app(CredentialPoolService::class);
$credential = $pool->getAvailableCredentials(6)->first();

echo 'RETRIEVED COUNT: '.$retrieved->count()."\n";
echo "PROMPT:\n".$prompt."\n";
echo "================\nGEMINI GENERATING...\n================\n";

if ($credential) {
    $result = $geminiService->generate($credential->encrypted_secret, $prompt);
    echo $result;
} else {
    echo 'NO CREDENTIALS';
}
