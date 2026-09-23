<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KnowledgeReviewUpdated extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $knowledgeId,
        public string $knowledgeTitle,
        public string $status,
        public ?string $reviewNote = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $content = match ($this->status) {
            'approved' => 'Knowledge Anda telah disetujui dan dapat digunakan sebagai sumber belajar.',
            'rejected' => 'Knowledge Anda ditolak oleh reviewer.',
            'draft' => 'Reviewer meminta perbaikan pada knowledge Anda.',
            default => 'Status review knowledge Anda telah diperbarui.',
        };

        return [
            'kind' => 'knowledge_review',
            'title' => $this->knowledgeTitle,
            'message' => $this->reviewNote ? $content.' Catatan: '.$this->reviewNote : $content,
            'status' => $this->status,
            'url' => route('knowledge.show', $this->knowledgeId),
        ];
    }
}
