<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeChunk;
use Illuminate\Support\Str;

class KnowledgeLinkExtractor
{
    /**
     * @param  array<int, array{chunk: KnowledgeChunk, content: string, score: float}>  $retrieved
     * @return array<int, array{url: string, label: string}>
     */
    public function extractFromResults(array $retrieved, ?string $question = null): array
    {
        $subjectTerms = $this->subjectTerms($question);
        $groupLinkRequested = $this->groupLinkRequested($question);
        $links = collect($retrieved)
            ->flatMap(function (array $result, int $resultIndex) use ($subjectTerms): array {
                return collect($this->extract($result['content']))
                    ->map(function (array $link, int $linkIndex) use ($result, $resultIndex, $subjectTerms): array {
                        $link['relevance'] = $this->linkRelevance(
                            $result['content'],
                            $link['url'],
                            $subjectTerms,
                        );
                        $link['order'] = ($resultIndex * 100) + $linkIndex;

                        return $link;
                    })
                    ->all();
            });

        if ($groupLinkRequested) {
            $groupLinks = $links->filter(fn (array $link): bool => in_array(
                Str::lower((string) parse_url($link['url'], PHP_URL_HOST)),
                ['chat.google.com', 'chat.whatsapp.com'],
                true,
            ));

            if ($groupLinks->isNotEmpty()) {
                $links = $groupLinks;
            }
        }

        if ($subjectTerms !== []) {
            $highestRelevance = (int) $links->max('relevance');
            $relevantLinks = $links->filter(
                fn (array $link): bool => $highestRelevance > 0 && $link['relevance'] === $highestRelevance,
            );

            if ($relevantLinks->isNotEmpty()) {
                $links = $relevantLinks;
            }
        }

        return $links
            ->sortBy([
                ['relevance', 'desc'],
                ['order', 'asc'],
            ])
            ->unique('url')
            ->take(8)
            ->map(fn (array $link): array => [
                'url' => $link['url'],
                'label' => $link['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{url: string, label: string}>
     */
    public function extract(string $content): array
    {
        $links = [];

        preg_match_all('/\[([^\]\r\n]+)\]\((https?:\/\/[^\s]+)\)/iu', $content, $markdownMatches, PREG_SET_ORDER);

        foreach ($markdownMatches as $match) {
            $this->addLink($links, $match[2], $match[1]);
        }

        preg_match_all('/https?:\/\/[^\s<>"\']+/iu', $content, $urlMatches);

        foreach ($urlMatches[0] ?? [] as $url) {
            $this->addLink($links, $url);
        }

        return array_values($links);
    }

    /**
     * @param  array<int, array{url: string, label: string}>  $links
     */
    public function appendixFor(string $response, array $links, int $limit = 8): string
    {
        $missingLinks = collect($links)
            ->filter(fn (array $link): bool => ! str_contains($response, $link['url']))
            ->unique('url')
            ->take($limit)
            ->values();

        if ($missingLinks->isEmpty()) {
            return '';
        }

        $items = $missingLinks
            ->map(fn (array $link): string => '- ['.$link['label'].']('.$link['url'].')')
            ->join("\n");

        return "\n\n### Tautan terkait\n{$items}";
    }

    /**
     * @param  array<string, array{url: string, label: string}>  $links
     */
    private function addLink(array &$links, string $candidate, ?string $label = null): void
    {
        $url = $this->normalizeUrl($candidate);

        if ($url === null || isset($links[$url])) {
            return;
        }

        $links[$url] = [
            'url' => $url,
            'label' => $this->normalizeLabel($label, $url),
        ];
    }

    private function normalizeUrl(string $candidate): ?string
    {
        $url = html_entity_decode(trim($candidate), ENT_QUOTES | ENT_HTML5);
        $url = rtrim($url, '.,;:!?]}>');

        while (str_ends_with($url, ')') && substr_count($url, ')') > substr_count($url, '(')) {
            $url = substr($url, 0, -1);
        }

        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }

    private function normalizeLabel(?string $label, string $url): string
    {
        $normalizedLabel = Str::squish(strip_tags((string) $label));
        $normalizedLabel = str_replace(['[', ']'], '', $normalizedLabel);

        if ($normalizedLabel === '') {
            $normalizedLabel = (string) parse_url($url, PHP_URL_HOST);
        }

        return Str::limit($normalizedLabel !== '' ? $normalizedLabel : 'Buka sumber', 100);
    }

    /** @return array<int, string> */
    private function subjectTerms(?string $question): array
    {
        $genericTerms = [
            'dong',
            'gorup',
            'group',
            'gruop',
            'grup',
            'info',
            'link',
            'mohon',
            'please',
            'saya',
            'tautan',
            'tolong',
            'wa',
            'whatapp',
            'whatsap',
            'whatsapp',
        ];

        return collect(preg_split('/[^\pL\pN]+/u', Str::lower((string) $question)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->reject(fn (string $term): bool => in_array($term, $genericTerms, true))
            ->unique()
            ->values()
            ->all();
    }

    private function groupLinkRequested(?string $question): bool
    {
        return preg_match('/\b(?:gorup|group|gruop|grup|wa|whatapp|whatsap|whatsapp)\b/iu', (string) $question) === 1;
    }

    /** @param array<int, string> $subjectTerms */
    private function linkRelevance(string $content, string $url, array $subjectTerms): int
    {
        if ($subjectTerms === []) {
            return 0;
        }

        $lines = collect(preg_split('/\R/u', $content) ?: [])
            ->map(fn (string $line): string => Str::lower(trim($line)))
            ->filter()
            ->values();
        $normalizedUrl = Str::lower($url);

        return (int) $lines->map(function (string $line, int $index) use ($lines, $normalizedUrl, $subjectTerms): int {
            if (! str_contains($line, $normalizedUrl)) {
                return 0;
            }

            $directMatches = collect($subjectTerms)->filter(
                fn (string $term): bool => str_contains($line, $term),
            )->count();
            $previousLine = $index > 0 ? $lines->get($index - 1, '') : '';
            $previousMatches = collect($subjectTerms)->filter(
                fn (string $term): bool => str_contains($previousLine, $term),
            )->count();

            return ($directMatches * 2) + $previousMatches;
        })->max();
    }
}
