<?php

namespace App\Services;

use LogicException;

class CostCalculator
{
    public function providerCost(array $usage, array $rates): int
    {
        throw new LogicException('Implemented in a later phase');
    }

    public function charge(int $providerCostNano): int
    {
        throw new LogicException('Implemented in a later phase');
    }

    public function estimate(array $pricing, int $promptChars, string $size, string $quality, int $referenceCount): array
    {
        throw new LogicException('Implemented in a later phase');
    }
}
