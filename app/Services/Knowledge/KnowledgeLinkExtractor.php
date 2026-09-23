<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Str;

class KnowledgeLinkExtractor
{
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
}
