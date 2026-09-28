<?php

use App\Models\Conversation;
use App\Models\DiscordBotConversation;
use App\Models\User;
use App\Services\Knowledge\KnowledgeLinkExtractor;
use App\Services\Knowledge\RetrievalService;
use App\Services\PromptBuilderService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dummyUser = User::first() ?? new User(['id' => 1]);
$dummyConversation = new Conversation(['course_id' => null, 'user_id' => $dummyUser->id]);

// Assuming channelId from the screenshot (e.g. general-ai)
// We will just find the most recent channel in DiscordBotConversation
$lastChat = DiscordBotConversation::latest('id')->first();
$channelId = $lastChat ? $lastChat->discord_channel_id : '123456';
$question = 'hari ini ada agenda apa?';

$retrievalService = app(RetrievalService::class);
$promptBuilder = app(PromptBuilderService::class);
$linkExtractor = app(KnowledgeLinkExtractor::class);

$retrieved = $retrievalService->retrieve($dummyUser, $dummyConversation, $question, 15);

$recentHistory = DiscordBotConversation::query()
    ->where('discord_channel_id', $channelId)
    ->where('status', 'completed')
    ->latest('id')
    ->limit(6)
    ->get()
    ->reverse()
    ->flatMap(fn ($conv) => [
        ['role' => 'user', 'content' => $conv->question],
        ['role' => 'assistant', 'content' => $conv->answer ?? ''],
    ])
    ->toArray();

$prompt = $promptBuilder->build(
    $question,
    $retrieved->map(fn ($r) => [
        'content' => $r['content'],
        'score' => $r['score'],
        'links' => $linkExtractor->extract($r['chunk']->version?->content ?? $r['content']),
    ])->all(),
    'general',
    $recentHistory,
);

echo 'RETRIEVED COUNT: '.$retrieved->count()."\n";
echo 'PROMPT LENGTH: '.strlen($prompt)."\n";
echo "PROMPT:\n";
echo "====================================\n";
echo $prompt;
echo "\n====================================\n";
