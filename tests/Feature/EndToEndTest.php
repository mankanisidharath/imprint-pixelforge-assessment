<?php

use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.pixelforge.base_url' => 'https://pixelforge.test/v1', 'services.pixelforge.webhook_secret' => 'test-secret', 'queue.default' => 'sync']);
    Http::preventStrayRequests();
});

function integrationPricing(): array
{
    return ['rates' => integrationRates(), 'tokens' => [
        'characters_per_text_token' => 4, 'text_token_variance' => 0.15, 'per_reference_image' => 765,
        'output' => ['high' => ['1536x1024' => 6450]], 'reasoning' => ['high' => ['min' => 1000, 'max' => 8000]],
    ]];
}

function integrationRates(): array
{
    return ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'];
}

function integrationSnapshot(Generation $generation, string $status = 'succeeded'): array
{
    return ['id' => 'gen_test1', 'status' => $status, 'metadata' => ['local_id' => $generation->id],
        'rates' => integrationRates(), 'usage' => ['input_text_tokens' => 100, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 3184],
        'error' => ['code' => 'generation_failed', 'message' => 'Failed']];
}

function integrationWebhook(Generation $generation, string $status, string $eventId): void
{
    signedWebhook(['id' => $eventId, 'object' => 'event', 'type' => 'generation.'.$status, 'data' => ['object' => integrationSnapshot($generation, $status)]])->assertNoContent();
}

function integrationSubmit(Generation $generation): void
{
    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);
}

function assertIntegrationSettled(Generation $generation): void
{
    expect($generation->fresh()->status->value)->toBe('succeeded')
        ->and($generation->fresh()->provider_id)->toBe('gen_test1')
        ->and($generation->fresh()->provider_cost_nano)->toBe(313600000)
        ->and($generation->fresh()->charge_nano)->toBe(392000000);
}

test('submits and settles a generation through signed webhooks and history', function () {
    Http::fake(function ($request) {
        if (str_ends_with($request->url(), '/pricing')) {
            return Http::response(integrationPricing());
        }

        return Http::response(['id' => 'gen_test1', 'status' => 'queued', 'metadata' => $request['metadata'], 'rates' => integrationRates()], 202);
    });
    $user = User::factory()->create();
    $this->actingAs($user)->post('/generations', ['prompt' => str_repeat('a', 400), 'quality' => 'high', 'size' => '1536x1024', 'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png']])->assertRedirect('/generations');
    $generation = $user->generations()->sole();
    expect($generation->provider_id)->toBe('gen_test1');
    integrationWebhook($generation, 'processing', 'evt_processing');
    integrationWebhook($generation, 'succeeded', 'evt_success');
    assertIntegrationSettled($generation);
    $this->get('/generations')->assertInertia(fn (Assert $page) => $page->where('generations.data.0.status', 'succeeded')->where('generations.data.0.charge_exact', '0.392000000'));
});

test('accepts a webhook while the submission response is in flight', function () {
    $generation = Generation::factory()->queued()->create();
    Http::fake(function () use ($generation) {
        expect($generation->fresh()->provider_id)->toBeNull();
        integrationWebhook($generation, 'succeeded', 'evt_early');

        return Http::response(integrationSnapshot($generation, 'queued'), 202);
    });
    integrationSubmit($generation);
    assertIntegrationSettled($generation);
});

test('deduplicates events and ignores an older processing event', function () {
    $generation = Generation::factory()->accepted()->create(['provider_id' => 'gen_test1']);
    integrationWebhook($generation, 'succeeded', 'evt_success');
    integrationWebhook($generation, 'succeeded', 'evt_success');
    integrationWebhook($generation, 'processing', 'evt_processing');
    assertIntegrationSettled($generation);
    $this->assertDatabaseCount('webhook_events', 2);
});

test('records no charge when an accepted generation fails', function () {
    $generation = Generation::factory()->accepted()->create(['provider_id' => 'gen_test1']);
    integrationWebhook($generation, 'failed', 'evt_failed');
    expect($generation->fresh()->status->value)->toBe('failed')->and($generation->fresh()->charge_nano)->toBe(0);
});

test('settles a late success after five overload failures', function () {
    $generation = Generation::factory()->queued()->create();
    Http::fake(['*' => Http::response(['error' => ['code' => 'overloaded']], 503, ['Retry-After' => 2])]);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        integrationSubmit($generation);
    }
    expect($generation->fresh()->status->value)->toBe('failed')->and($generation->fresh()->charge_nano)->toBe(0);
    integrationWebhook($generation, 'succeeded', 'evt_late');
    assertIntegrationSettled($generation);
});

test('reconciles a lost webhook and ignores its late delivery', function () {
    $generation = Generation::factory()->accepted()->create(['provider_id' => 'gen_test1', 'created_at' => now()->subMinutes(3)]);
    Http::fake(['*/generations/gen_test1' => Http::response(integrationSnapshot($generation))]);
    $this->artisan('generations:reconcile')->assertSuccessful();
    assertIntegrationSettled($generation);
    integrationWebhook($generation, 'succeeded', 'evt_late');
    assertIntegrationSettled($generation);
});
