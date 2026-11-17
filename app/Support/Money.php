<?php

namespace App\Support;

use LogicException;

class Money
{
    public static function rateToNanoPerToken(string $usdPerMillion): int
    {
        throw new LogicException('Implemented in a later phase');
    }

    public static function usdToNano(string $usd): int
    {
        throw new LogicException('Implemented in a later phase');
    }

    public static function format(int $nano): string
    {
        throw new LogicException('Implemented in a later phase');
    }
}
