<?php

namespace App\Console\Commands;

use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Knowledge;
use App\Models\User;
use App\Services\DiscordService;
use Illuminate\Console\Command;

class SyncDiscordKnowledge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discord:sync-knowledge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync knowledge from configured Discord channels and threads';

    /**
     * Execute the console command.
     */
    public function handle(DiscordService $discord): int
    {
        $channelIds = config('services.discord.channel_ids', []);
        
        if (empty($channelIds)) {
            $this->warn('No Discord channels configured for syncing.');
            return self::SUCCESS;
        }

        // Get or create a system user for these knowledges
        $systemUser = User::firstOrCreate(
            ['email' => 'discord-bot@pjj.ai'],
            ['name' => 'Discord System Bot', 'password' => bcrypt(\Illuminate\Support\Str::random(16)), 'role' => 'admin']
        );

        $this->info('Starting Discord knowledge sync...');

        foreach ($channelIds as $channelId) {
            $this->info("Fetching messages from channel: {$channelId}");
            $messages = $discord->getChannelMessages($channelId);

            foreach ($messages as $message) {
                // Skip empty messages or bot messages if needed
                if (empty($message['content'])) {
                    continue;
                }

                // Simple check to avoid duplicates based on a unique title prefix
                $titleId = "Discord Msg #{$message['id']}";
                if (Knowledge::where('title', 'like', "{$titleId}%")->exists()) {
                    continue;
                }

                $author = $message['author']['username'] ?? 'Unknown';
                $title = "{$titleId} by {$author}";
                $content = $message['content'];

                $knowledge = Knowledge::create([
                    'user_id' => $systemUser->id,
                    'course_id' => null,
                    'title' => $title,
                    'description' => "Auto-synced from Discord channel {$channelId}",
                    'visibility' => 'community',
                    'status' => 'approved',
                ]);

                $version = $knowledge->versions()->create([
                    'version' => 1,
                    'content' => $content,
                    'source_type' => 'discord',
                    'original_filename' => null,
                    'status' => 'approved',
                    'submitted_at' => now(),
                    'reviewed_at' => now(),
                ]);

                $knowledge->update(['active_version_id' => $version->id]);
                ProcessKnowledgeEmbedding::dispatch($version);

                $this->line("Synced message: {$message['id']}");
            }
        }

        $this->info('Discord knowledge sync completed.');

        return self::SUCCESS;
    }
}
