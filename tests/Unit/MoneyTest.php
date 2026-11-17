<?php

use App\Support\Money;

test('converts USD per million tokens to exact nanodollars per token', function (string $rate, int $expected) {
    expect(Money::rateToNanoPerToken($rate))->toBeInt()->toBe($expected);
})->with([
    'reasoning' => ['12.50', 12_500],
    'text' => ['5.00', 5_000],
    'image' => ['10.00', 10_000],
    'output' => ['40.00', 40_000],
    'leading and excess trailing zeroes' => ['000.001000', 1],
]);

test('formats nine decimal places and converts back to the same nanodollars', function (int $nano, string $usd) {
    $formatted = Money::format($nano);

    expect($formatted)->toBe($usd);
    expect(Money::usdToNano($formatted))->toBeInt()->toBe($nano);
})->with([
    'zero' => [0, '0.000000000'],
    'one nanodollar' => [1, '0.000000001'],
    'provider example' => [169_080_000, '0.169080000'],
    'one dollar' => [1_000_000_000, '1.000000000'],
    'with whole and fractional dollars' => [12_345_678_901, '12.345678901'],
]);

test('parses whole dollars and accepts excess zeroes in USD amounts', function (string $usd) {
    expect(Money::usdToNano($usd))->toBeInt()->toBe(1_000_000_000);
})->with(['1', '1.00000000000']);

test('preserves the integer limit in a round trip', function () {
    expect(Money::usdToNano(Money::format(PHP_INT_MAX)))->toBeInt()->toBe(PHP_INT_MAX);
});

test('preserves the sign when formatting a negative nanodollar', function () {
    expect(Money::format(-1))->toBe('-0.000000001');
});

test('rejects malformed USD decimal strings', function (string $usd) {
    expect(fn () => Money::usdToNano($usd))->toThrow(InvalidArgumentException::class);
})->with([
    'letters' => 'abc',
    'negative' => '-1',
    'exponent' => '1e3',
    'trailing newline' => "1\n",
]);

test('rejects decimals that would lose precision at the target scale', function () {
    expect(fn () => Money::rateToNanoPerToken('0.0001'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Money::usdToNano('1.0000000001'))->toThrow(InvalidArgumentException::class);
});

test('rejects a USD amount outside the integer range', function () {
    expect(fn () => Money::usdToNano((string) PHP_INT_MAX))->toThrow(InvalidArgumentException::class);
});
