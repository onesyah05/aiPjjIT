<?php

namespace App\Console\Commands;

use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Knowledge;
use App\Models\User;
use App\Services\DiscordService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
    protected $description = 'Sync knowledge from configured Discord channels and threads with proper forum grouping';

    /**
     * Execute the console command.
     */
    public function handle(DiscordService $discord): int
    {
        $channelIds = config('services.discord.channel_ids', []);
        $guildId = config('services.discord.guild_id');

        if (empty($channelIds)) {
            $this->warn('No Discord channels configured for syncing.');

            return self::SUCCESS;
        }

        // Get or create a system user for these knowledges
        $systemUser = User::firstOrCreate(
            ['email' => 'discord-bot@pjj.ai'],
            ['name' => 'Discord System Bot', 'password' => bcrypt(Str::random(16)), 'role' => 'admin']
        );

        $this->info('Starting Discord knowledge sync...');

        $activeThreads = $discord->getActiveThreads($guildId);

        foreach ($channelIds as $channelId) {
            $this->info("Fetching info for channel: {$channelId}");
            $channel = $discord->getChannel($channelId);

            if (! $channel) {
                $this->warn("Skipping channel {$channelId}, not found or inaccessible.");

                continue;
            }

            $type = $channel['type'] ?? 0;
            $channelName = $channel['name'] ?? 'Unknown Channel';

            // 15 = GUILD_FORUM
            if ($type === 15) {
                $this->info("Processing Forum Channel: {$channelName}");

                // Get threads for this forum
                $archivedThreads = $discord->getArchivedThreads($channelId);
                $forumThreads = array_filter($activeThreads, fn ($t) => ($t['parent_id'] ?? null) == $channelId);

                $allThreads = array_merge($forumThreads, $archivedThreads);

                foreach ($allThreads as $thread) {
                    $this->processThread($discord, $systemUser, $thread['id'], "Forum: {$channelName} - {$thread['name']}");
                }

            } else {
                // Regular channel
                $this->info("Processing Regular Text Channel: {$channelName}");
                $this->processRegularChannel($discord, $systemUser, $channelId, "Channel: {$channelName}");
            }
        }

        $this->info('Discord knowledge sync completed.');

        return self::SUCCESS;
    }

    private function processThread(DiscordService $discord, User $systemUser, string $threadId, string $title)
    {
        $this->info("Fetching messages for thread: {$title}");
        $messages = $discord->getChannelMessages($threadId, 1000);

        if (empty($messages)) {
            return;
        }

        // Discord returns messages from newest to oldest. Reverse to chronological.
        $messages = array_reverse($messages);

        $content = '';
        foreach ($messages as $msg) {
            $text = trim($msg['content'] ?? '');

            $attachments = '';
            if (! empty($msg['attachments'])) {
                foreach ($msg['attachments'] as $attachment) {
                    if (! empty($attachment['url'])) {
                        $url = $attachment['url'];
                        $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', parse_url($url, PHP_URL_PATH));
                        if ($isImage) {
                            $attachments .= "\n\n![Attachment]({$url})";
                        } else {
                            $attachments .= "\n\n[Attachment file: {$attachment['filename']}]({$url})";
                        }
                    }
                }
            }
            if (! empty($msg['embeds'])) {
                foreach ($msg['embeds'] as $embed) {
                    if (! empty($embed['image']['url'])) {
                        $attachments .= "\n\n![Embed Image]({$embed['image']['url']})";
                    } elseif (! empty($embed['thumbnail']['url'])) {
                        $attachments .= "\n\n![Embed Thumbnail]({$embed['thumbnail']['url']})";
                    }
                    if (! empty($embed['url'])) {
                        $embedTitle = $embed['title'] ?? 'Link';
                        $attachments .= "\n\n[{$embedTitle}]({$embed['url']})";
                    }
                }
            }

            if (empty($text) && empty($attachments)) {
                continue;
            }

            $author = $msg['author']['username'] ?? 'Unknown';
            $content .= "**{$author}**: {$text}{$attachments}\n\n";
        }

        if (empty(trim($content))) {
            return;
        }

        $this->saveKnowledge($systemUser, $title, 'Auto-synced forum thread', $content, $threadId);
    }

    private function processRegularChannel(DiscordService $discord, User $systemUser, string $channelId, string $title)
    {
        $this->info("Fetching messages for channel: {$title}");
        $messages = $discord->getChannelMessages($channelId, 1000);

        if (empty($messages)) {
            return;
        }

        // Discord returns messages from newest to oldest. Reverse to chronological.
        $messages = array_reverse($messages);

        $content = '';
        foreach ($messages as $msg) {
            $text = trim($msg['content'] ?? '');

            $attachments = '';
            if (! empty($msg['attachments'])) {
                foreach ($msg['attachments'] as $attachment) {
                    if (! empty($attachment['url'])) {
                        $url = $attachment['url'];
                        $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', parse_url($url, PHP_URL_PATH));
                        if ($isImage) {
                            $attachments .= "\n\n![Attachment]({$url})";
                        } else {
                            $attachments .= "\n\n[Attachment file: {$attachment['filename']}]({$url})";
                        }
                    }
                }
            }
            if (! empty($msg['embeds'])) {
                foreach ($msg['embeds'] as $embed) {
                    if (! empty($embed['image']['url'])) {
                        $attachments .= "\n\n![Embed Image]({$embed['image']['url']})";
                    } elseif (! empty($embed['thumbnail']['url'])) {
                        $attachments .= "\n\n![Embed Thumbnail]({$embed['thumbnail']['url']})";
                    }
                    if (! empty($embed['url'])) {
                        $embedTitle = $embed['title'] ?? 'Link';
                        $attachments .= "\n\n[{$embedTitle}]({$embed['url']})";
                    }
                }
            }

            if (empty($text) && empty($attachments)) {
                continue;
            }

            $author = $msg['author']['username'] ?? 'Unknown';
            $content .= "**{$author}**: {$text}{$attachments}\n\n";
        }

        if (empty(trim($content))) {
            return;
        }

        $this->saveKnowledge($systemUser, $title, 'Auto-synced regular channel', $content, $channelId);
    }

    private function saveKnowledge(User $systemUser, string $title, string $description, string $content, string $discordId)
    {
        $uniqueTitle = "[Discord {$discordId}] {$title}";

        // Identify the knowledge by the immutable Discord channel/thread id so
        // renaming the source never forks it into a duplicate.
        $knowledge = Knowledge::where('discord_source_id', $discordId)->first()
            ?? Knowledge::where('title', $uniqueTitle)->first();

        if ($knowledge) {
            // Update if content changed
            $activeContent = $knowledge->activeVersion?->content;
            if ($activeContent !== $content) {
                $version = $knowledge->versions()->create([
                    'version' => ((int) $knowledge->versions()->max('version')) + 1,
                    'content' => $content,
                    'source_type' => 'discord',
                    'original_filename' => null,
                    'status' => 'approved',
                    'submitted_at' => now(),
                    'reviewed_at' => now(),
                ]);
                $knowledge->update(['active_version_id' => $version->id]);
                ProcessKnowledgeEmbedding::dispatch($version);
                $this->line("Updated knowledge: {$uniqueTitle}");
            } else {
                $this->line("Knowledge unchanged: {$uniqueTitle}");
            }
        } else {
            // Create new
            $knowledge = Knowledge::create([
                'user_id' => $systemUser->id,
                'course_id' => null,
                'title' => $uniqueTitle,
                'discord_source_id' => $discordId,
                'description' => $description,
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
            $this->line("Created knowledge: {$uniqueTitle}");
        }
    }
}
