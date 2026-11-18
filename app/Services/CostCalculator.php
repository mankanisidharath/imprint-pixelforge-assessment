<?php

namespace App\Services;

use App\Features\Pixelforge\TokenType;
use App\Support\Money;
use InvalidArgumentException;
use OverflowException;

class CostCalculator
{
    public function providerCost(array $usage, array $rates): int
    {
        $cost = 0;

        foreach (TokenType::BILLABLE_KEYS as $key) {
            $tokens = (int) $usage[$key];
            $rate = Money::rateToNanoPerToken($rates[$key]);

            if ($tokens < 0) {
                throw new InvalidArgumentException('Token counts must be non-negative.');
            }

            if ($rate > 0 && $tokens > intdiv(PHP_INT_MAX - $cost, $rate)) {
                throw new OverflowException('The provider cost exceeds the integer range.');
            }

            $cost += $tokens * $rate;
        }

        return $cost;
    }

    public function charge(int $providerCostNano): int
    {
        $percent = (int) config('services.pixelforge.markup_percent');

        if ($providerCostNano < 0 || $percent < 0) {
            throw new InvalidArgumentException('The provider cost and markup must be non-negative.');
        }

        // Apply markup to whole hundreds and the remainder to keep intermediate values in range.
        $wholeHundreds = intdiv($providerCostNano, 100);
        $remainingNano = $providerCostNano % 100;
        $remainingMarkup = $remainingNano * intdiv($percent, 100)
            + intdiv($remainingNano * ($percent % 100) + 99, 100);
        $available = PHP_INT_MAX - $providerCostNano;

        if ($remainingMarkup > $available || ($percent > 0 && $wholeHundreds > intdiv($available - $remainingMarkup, $percent))) {
            throw new OverflowException('The marked-up charge exceeds the integer range.');
        }

        return $providerCostNano + $wholeHundreds * $percent + $remainingMarkup;
    }

    /** @return array{min: int, max: int} */
    public function estimate(array $pricing, int $promptChars, string $size, string $quality, int $referenceCount): array
    {
        $rates = $pricing['rates'];
        $tokens = $pricing['tokens'];
        $charsPerToken = $tokens['characters_per_text_token'];
        $text = intdiv($promptChars + $charsPerToken - 1, $charsPerToken);
        $variance = (int) round($tokens['text_token_variance'] * 100);
        $textMin = intdiv($text * (100 - $variance), 100);
        $textMax = intdiv($text * (100 + $variance) + 99, 100);
        $reasoning = $tokens['reasoning'][$quality];
        $fixedUsage = [
            TokenType::INPUT_IMAGE => $referenceCount * $tokens['per_reference_image'],
            TokenType::OUTPUT => $tokens['output'][$quality][$size],
        ];

        return [
            'min' => $this->providerCost($fixedUsage + [
                TokenType::INPUT_TEXT => $textMin,
                TokenType::REASONING => $reasoning['min'],
            ], $rates),
            'max' => $this->providerCost($fixedUsage + [
                TokenType::INPUT_TEXT => $textMax,
                TokenType::REASONING => $reasoning['max'],
            ], $rates),
        ];
    }
}
