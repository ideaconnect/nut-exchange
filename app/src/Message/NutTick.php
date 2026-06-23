<?php

declare(strict_types=1);

namespace App\Message;

final class NutTick
{
    public function __construct(
        public string $nut,
        public float $price,
        public int $ts,
    ) {
    }
}
