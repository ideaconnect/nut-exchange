<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class NutTickInput
{
    public const NUTS = ['almond', 'walnut', 'cashew', 'pecan', 'hazelnut'];

    public function __construct(
        #[Assert\Choice(choices: self::NUTS)]
        public string $nut,

        #[Assert\Positive]
        public float $price,

        #[Assert\PositiveOrZero]
        public int $ts = 0,
    ) {
    }
}
