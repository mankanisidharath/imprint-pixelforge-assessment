<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    private const USD_SCALE = 9;

    public static function rateToNanoPerToken(string $usdPerMillion): int
    {
        return self::parseDecimal($usdPerMillion, 3);
    }

    public static function usdToNano(string $usd): int
    {
        return self::parseDecimal($usd, self::USD_SCALE);
    }

    public static function format(int $nano): string
    {
        $digits = str_pad(ltrim((string) $nano, '-'), self::USD_SCALE + 1, '0', STR_PAD_LEFT);
        $sign = $nano < 0 ? '-' : '';

        return $sign.substr($digits, 0, -self::USD_SCALE).'.'.substr($digits, -self::USD_SCALE);
    }

    private static function parseDecimal(string $decimal, int $scale): int
    {
        if (preg_match('/\A([0-9]+)(?:\.([0-9]+))?\z/', $decimal, $parts) !== 1) {
            throw new InvalidArgumentException('Expected a non-negative decimal string.');
        }

        $fraction = rtrim($parts[2] ?? '', '0');

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException('The decimal has more precision than the scale allows.');
        }

        $digits = ltrim($parts[1].str_pad($fraction, $scale, '0'), '0');
        $max = (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            throw new InvalidArgumentException('The decimal is too large to store as an integer.');
        }

        return (int) $digits;
    }
}
