<?php

namespace App\Support;

class QueryMetrics
{
    private int $count = 0;

    private float $totalMs = 0.0;

    public function reset(): void
    {
        $this->count = 0;
        $this->totalMs = 0.0;
    }

    public function add(float $durationMs): void
    {
        $this->count++;
        $this->totalMs += $durationMs;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function totalMs(): float
    {
        return $this->totalMs;
    }
}
