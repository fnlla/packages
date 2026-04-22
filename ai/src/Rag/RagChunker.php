<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Rag;

final class RagChunker
{
    public function __construct(private int $chunkSize, private int $overlap)
    {
        $this->chunkSize = max(200, $this->chunkSize);
        $this->overlap = max(0, min($this->overlap, (int) floor($this->chunkSize / 2)));
    }

    /**
     * @return string[]
     */
    public function chunk(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $length = strlen($text);
        if ($length <= $this->chunkSize) {
            return [$text];
        }

        $chunks = [];
        $step = max(1, $this->chunkSize - $this->overlap);
        for ($pos = 0; $pos < $length; $pos += $step) {
            $chunk = substr($text, $pos, $this->chunkSize);
            $chunk = trim($chunk);
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }
}


