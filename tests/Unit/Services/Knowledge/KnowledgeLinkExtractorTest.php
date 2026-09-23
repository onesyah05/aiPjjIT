<?php

namespace Tests\Unit\Services\Knowledge;

use App\Services\Knowledge\KnowledgeLinkExtractor;
use PHPUnit\Framework\TestCase;

class KnowledgeLinkExtractorTest extends TestCase
{
    public function test_extracts_unique_http_links_with_readable_labels(): void
    {
        $content = <<<'MARKDOWN'
Pelajari [panduan resmi Laravel](https://laravel.com/docs/13.x).
Video tersedia di https://www.youtube.com/watch?v=abc123.
Duplikat: https://laravel.com/docs/13.x
Tautan tidak aman: javascript:alert(1)
MARKDOWN;

        $links = (new KnowledgeLinkExtractor)->extract($content);

        $this->assertSame([
            ['url' => 'https://laravel.com/docs/13.x', 'label' => 'panduan resmi Laravel'],
            ['url' => 'https://www.youtube.com/watch?v=abc123', 'label' => 'www.youtube.com'],
        ], $links);
    }

    public function test_builds_an_appendix_only_for_links_missing_from_the_response(): void
    {
        $links = [
            ['url' => 'https://laravel.com/docs/13.x', 'label' => 'Dokumentasi Laravel'],
            ['url' => 'https://php.net/manual/en/', 'label' => 'Manual PHP'],
        ];

        $appendix = (new KnowledgeLinkExtractor)->appendixFor(
            'Baca dokumentasi di https://laravel.com/docs/13.x.',
            $links,
        );

        $this->assertSame("\n\n### Tautan terkait\n- [Manual PHP](https://php.net/manual/en/)", $appendix);
    }
}
