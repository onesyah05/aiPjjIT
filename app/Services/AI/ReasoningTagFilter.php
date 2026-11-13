<?php

namespace App\Services\AI;

/**
 * Strips inline `<think>…</think>` reasoning blocks from a streamed answer.
 *
 * Reasoning models (MiniMax-M2, DeepSeek-R1, …) may emit their chain of
 * thought inside the content itself, and a tag can be split across stream
 * chunks, so the filter keeps a small buffer between pushes.
 */
class ReasoningTagFilter
{
    private const OPEN_TAG = '<think>';

    private const CLOSE_TAG = '</think>';

    private string $buffer = '';

    private bool $insideReasoning = false;

    private bool $hasEmitted = false;

    public function push(string $chunk): string
    {
        $this->buffer .= $chunk;
        $output = '';

        while ($this->buffer !== '') {
            if ($this->insideReasoning) {
                $closePosition = strpos($this->buffer, self::CLOSE_TAG);

                if ($closePosition === false) {
                    $this->buffer = substr($this->buffer, -(strlen(self::CLOSE_TAG) - 1));

                    break;
                }

                $this->buffer = substr($this->buffer, $closePosition + strlen(self::CLOSE_TAG));
                $this->insideReasoning = false;

                continue;
            }

            $openPosition = strpos($this->buffer, self::OPEN_TAG);

            if ($openPosition !== false) {
                $output .= substr($this->buffer, 0, $openPosition);
                $this->buffer = substr($this->buffer, $openPosition + strlen(self::OPEN_TAG));
                $this->insideReasoning = true;

                continue;
            }

            $heldBackLength = $this->partialTagLength($this->buffer);
            $output .= substr($this->buffer, 0, strlen($this->buffer) - $heldBackLength);
            $this->buffer = substr($this->buffer, strlen($this->buffer) - $heldBackLength);

            break;
        }

        return $this->emit($output);
    }

    public function flush(): string
    {
        $remaining = $this->insideReasoning ? '' : $this->buffer;
        $this->buffer = '';

        return $this->emit($remaining);
    }

    /**
     * Length of the longest suffix of the buffer that could be the start of an opening tag.
     */
    private function partialTagLength(string $text): int
    {
        for ($length = min(strlen(self::OPEN_TAG) - 1, strlen($text)); $length > 0; $length--) {
            if (str_starts_with(self::OPEN_TAG, substr($text, -$length))) {
                return $length;
            }
        }

        return 0;
    }

    /**
     * Drop the whitespace a model leaves between its reasoning and the answer.
     */
    private function emit(string $text): string
    {
        if (! $this->hasEmitted) {
            $text = ltrim($text);
            $this->hasEmitted = $text !== '';
        }

        return $text;
    }
}
