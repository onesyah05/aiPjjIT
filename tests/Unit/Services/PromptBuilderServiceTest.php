<?php

namespace Tests\Unit\Services;

use App\Services\PromptBuilderService;
use PHPUnit\Framework\TestCase;

class PromptBuilderServiceTest extends TestCase
{
    public function test_includes_source_links_and_requires_the_model_to_preserve_them(): void
    {
        $prompt = (new PromptBuilderService)->build('Di mana materi resminya?', [[
            'content' => 'Materi Laravel tersedia pada dokumentasi resmi.',
            'score' => 1.0,
            'links' => [[
                'url' => 'https://laravel.com/docs/13.x',
                'label' => 'Dokumentasi Laravel',
            ]],
        ]]);

        $this->assertStringContainsString('Tautan sumber:', $prompt);
        $this->assertStringContainsString('https://laravel.com/docs/13.x', $prompt);
        $this->assertStringContainsString('Jangan membuat, menebak, atau mengubah URL.', $prompt);
    }
}
