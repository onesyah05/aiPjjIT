<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\ReasoningTagFilter;
use PHPUnit\Framework\TestCase;

class ReasoningTagFilterTest extends TestCase
{
    public function test_removes_reasoning_split_across_chunks(): void
    {
        $filter = new ReasoningTagFilter;

        $output = collect(['<th', 'ink>langkah ', 'berpikir</thi', 'nk>', "\n\nJawaban ", 'akhir.'])
            ->map(fn (string $chunk): string => $filter->push($chunk))
            ->join('').$filter->flush();

        $this->assertSame('Jawaban akhir.', $output);
    }

    public function test_passes_plain_text_and_lookalike_tags_through(): void
    {
        $filter = new ReasoningTagFilter;

        $output = $filter->push('Gunakan tag <th').$filter->push('ead> pada tabel HTML.').$filter->flush();

        $this->assertSame('Gunakan tag <thead> pada tabel HTML.', $output);
    }

    public function test_drops_unterminated_reasoning(): void
    {
        $filter = new ReasoningTagFilter;

        $output = $filter->push('Awal. <think>belum selesai').$filter->flush();

        $this->assertSame('Awal. ', $output);
    }
}
