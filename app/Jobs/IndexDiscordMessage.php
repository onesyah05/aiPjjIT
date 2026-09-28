<?php

namespace App\Jobs;

use App\Models\Knowledge;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexDiscordMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public function __construct(
        public readonly string $channelId,
        public readonly string $authorUsername,
        public readonly string $messageContent,
        public readonly array $attachments = [],
        public readonly array $embeds = [],
    ) {}

    public function handle(): void
    {
        $formattedContent = $this->formatMessage();

        if (trim($formattedContent) === '') {
            return;
        }

        $systemUser = User::firstOrCreate(
            ['email' => 'discord-bot@pjj.ai'],
            ['name' => 'Discord System Bot', 'password' => bcrypt(Str::random(16)), 'role' => 'admin']
        );

        $titlePrefix = "[Discord {$this->channelId}]";

        $knowledge = Knowledge::where('title', 'LIKE', $titlePrefix.'%')->first();

        if ($knowledge) {
            $existingContent = $knowledge->activeVersion?->content ?? '';
            $newContent = $existingContent."\n\n".$formattedContent;

            $version = $knowledge->versions()->create([
                'version' => ((int) $knowledge->versions()->max('version')) + 1,
                'content' => $newContent,
                'source_type' => 'discord',
                'original_filename' => null,
                'status' => 'approved',
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]);
            $knowledge->update(['active_version_id' => $version->id]);
            ProcessKnowledgeEmbedding::dispatch($version);
        } else {
            $knowledge = Knowledge::create([
                'user_id' => $systemUser->id,
                'course_id' => null,
                'title' => "{$titlePrefix} Channel: {$this->channelId}",
                'description' => 'Auto-indexed Discord channel (realtime)',
                'visibility' => 'community',
                'status' => 'approved',
            ]);

            $version = $knowledge->versions()->create([
                'version' => 1,
                'content' => $formattedContent,
                'source_type' => 'discord',
                'original_filename' => null,
                'status' => 'approved',
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ]);

            $knowledge->update(['active_version_id' => $version->id]);
            ProcessKnowledgeEmbedding::dispatch($version);
        }
    }

    private function formatMessage(): string
    {
        $text = trim($this->messageContent);
        $attachmentText = '';

        foreach ($this->attachments as $attachment) {
            $url = $attachment['url'] ?? '';
            if ($url === '') {
                continue;
            }

            $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', parse_url($url, PHP_URL_PATH) ?? '');
            $attachmentText .= $isImage
                ? "\n\n![Attachment]({$url})"
                : "\n\n[Attachment file: ".($attachment['filename'] ?? 'file')."]({$url})";
        }

        foreach ($this->embeds as $embed) {
            if (! empty($embed['image']['url'])) {
                $attachmentText .= "\n\n![Embed Image]({$embed['image']['url']})";
            } elseif (! empty($embed['thumbnail']['url'])) {
                $attachmentText .= "\n\n![Embed Thumbnail]({$embed['thumbnail']['url']})";
            }
            if (! empty($embed['url'])) {
                $embedTitle = $embed['title'] ?? 'Link';
                $attachmentText .= "\n\n[{$embedTitle}]({$embed['url']})";
            }
        }

        if ($text === '' && $attachmentText === '') {
            return '';
        }

        return "**{$this->authorUsername}**: {$text}{$attachmentText}";
    }
}
