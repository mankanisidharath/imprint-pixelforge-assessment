<?php

use App\Services\CostCalculator;
use App\Support\Money;
use Tests\TestCase;

pest()->extend(TestCase::class);

function pixelforgePricing(): array
{
    return [
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}

test('calculates provider cost and marked-up charge for the documented usage', function () {
    $calculator = new CostCalculator;
    $usage = [
        'input_text_tokens' => '12',
        'input_image_tokens' => 765,
        'output_tokens' => 6450,
        'reasoning_tokens' => 3184,
        'total_tokens' => 999_999,
    ];

    $cost = $calculator->providerCost($usage, pixelforgePricing()['rates']);

    expect($cost)->toBeInt()->toBe(305_510_000);
    expect($calculator->charge($cost))->toBeInt()->toBe(381_887_500);
});

test('calculates and formats the documented cost for usage without images', function () {
    $usage = [
        'input_text_tokens' => 41,
        'input_image_tokens' => 0,
        'output_tokens' => 3300,
        'reasoning_tokens' => 2950,
    ];

    $cost = (new CostCalculator)->providerCost($usage, pixelforgePricing()['rates']);

    expect($cost)->toBeInt()->toBe(169_080_000);
    expect(Money::format($cost))->toBe('0.169080000');
});

test('estimates the documented provider cost range for high quality with two references', function () {
    $calculator = new CostCalculator;

    $estimate = $calculator->estimate(pixelforgePricing(), 400, '1536x1024', 'high', 2);

    expect($estimate)->toHaveKeys(['min', 'max'])->toHaveCount(2);
    expect($estimate['min'])->toBeInt()->toBe(286_225_000);
    expect($estimate['max'])->toBeInt()->toBe(373_875_000);
    expect($calculator->charge($estimate['min']))->toBeInt()->toBe(357_781_250);
    expect($calculator->charge($estimate['max']))->toBeInt()->toBe(467_343_750);
});

test('estimates low quality without references for empty and partial prompts', function (int $promptChars, int $max) {
    $estimate = (new CostCalculator)->estimate(pixelforgePricing(), $promptChars, '1024x1024', 'low', 0);

    expect($estimate['min'])->toBeInt()->toBe(11_200_000);
    expect($estimate['max'])->toBeInt()->toBe($max);
})->with([
    'no text' => [0, 17_450_000],
    'one character' => [1, 17_460_000],
]);

test('rounds the marked-up charge up to a whole nanodollar', function (int $cost, int $expected) {
    config(['services.pixelforge.markup_percent' => 25]);

    expect((new CostCalculator)->charge($cost))->toBeInt()->toBe($expected);
})->with([
    'fractional charge' => [1, 2],
    'exact charge' => [4, 5],
    'zero charge' => [0, 0],
]);

test('uses the configured markup when it changes', function () {
    config(['services.pixelforge.markup_percent' => 150]);

    expect((new CostCalculator)->charge(3))->toBeInt()->toBe(8);
});

test('calculates a large charge when the final amount fits in an integer', function () {
    $fifthOfIntMax = intdiv(PHP_INT_MAX, 5);

    expect((new CostCalculator)->charge($fifthOfIntMax * 4))->toBeInt()->toBe($fifthOfIntMax * 5);
});

test('rejects a provider cost outside the integer range', function () {
    $usage = [
        'input_text_tokens' => PHP_INT_MAX,
        'input_image_tokens' => 0,
        'output_tokens' => 0,
        'reasoning_tokens' => 0,
    ];

    expect(fn () => (new CostCalculator)->providerCost($usage, pixelforgePricing()['rates']))->toThrow(OverflowException::class);
});

test('rejects a marked-up charge outside the integer range', function () {
    expect(fn () => (new CostCalculator)->charge(PHP_INT_MAX))->toThrow(OverflowException::class);
});
