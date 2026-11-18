<?php

use App\Models\Generation;
use App\Services\Pixelforge;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.key' => 'test-api-key',
    ]);
});

test('sends the generation request with authentication and idempotency headers', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([], 202),
    ]);

    $generation = Generation::factory()->create([
        'prompt' => 'A misty mountain lake',
        'size' => '1536x1024',
        'quality' => 'high',
    ]);

    (new Pixelforge)->create($generation);

    Http::assertSent(function (Request $request) use ($generation): bool {
        return $request->url() === 'https://pixelforge.test/v1/generations'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request->hasHeader('Idempotency-Key', $generation->id)
            && $request->data() === [
                'prompt' => 'A misty mountain lake',
                'size' => '1536x1024',
                'quality' => 'high',
                'metadata' => [
                    'local_id' => $generation->id,
                ],
            ];
    });
});

test('includes reference images only when they are present', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([], 202),
    ]);

    $generation = Generation::factory()->create([
        'reference_images' => ['https://example.test/reference.png'],
    ]);

    (new Pixelforge)->create($generation);

    Http::assertSent(fn (Request $request): bool => $request->data()['reference_images'] === ['https://example.test/reference.png']);
});

test('caches the pricing response for ten minutes', function () {
    Cache::forget('pixelforge.pricing');
    Http::preventStrayRequests();
    Http::fake([
        'https://pixelforge.test/v1/pricing' => Http::response(['object' => 'pricing']),
    ]);

    $client = new Pixelforge;
    $firstPricing = $client->pricing();
    $secondPricing = $client->pricing();

    expect($firstPricing)->toBe(['object' => 'pricing']);
    expect($secondPricing)->toBe(['object' => 'pricing']);
    Http::assertSentCount(1);
});

test('uses shared request settings with independent headers for each read', function (string $method, array $arguments, string $url) {
    Cache::forget('pixelforge.pricing');
    Http::preventStrayRequests();
    Http::fake([
        'https://pixelforge.test/v1/generations' => Http::response([], 202),
        $url => Http::response([], 200),
    ]);
    $generation = Generation::factory()->make();
    $client = new Pixelforge;
    $client->create($generation);

    $client->{$method}(...$arguments);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === $url
        && $request->hasHeader('Authorization', 'Bearer test-api-key')
        && $request->hasHeader('Accept', 'application/json')
        && ! $request->hasHeader('Idempotency-Key')
    );
    Http::assertSentCount(2);
})->with([
    'generation lookup' => ['find', ['gen_existing'], 'https://pixelforge.test/v1/generations/gen_existing'],
    'usage report' => ['usage', ['2026-11-01', '2026-11-18'], 'https://pixelforge.test/v1/usage?start_date=2026-11-01&end_date=2026-11-18'],
    'pricing' => ['pricing', [], 'https://pixelforge.test/v1/pricing'],
]);
