<?php

use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function generationPricing(): array
{
    return [
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'tokens' => [
            'characters_per_text_token' => 4, 'text_token_variance' => 0.15, 'per_reference_image' => 765,
            'output' => ['high' => ['1536x1024' => 6450]],
            'reasoning' => ['high' => ['min' => 1000, 'max' => 8000]],
        ],
    ];
}

function generationInput(): array
{
    return ['prompt' => str_repeat('a', 400), 'size' => '1536x1024', 'quality' => 'high', 'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png']];
}

beforeEach(function () {
    config()->set('services.pixelforge.base_url', 'https://pixelforge.test/v1');
});

test('generation routes require authentication', function () {
    $this->get(route('generations.create'))->assertRedirect(route('login'));
    $this->get(route('generations.index'))->assertRedirect(route('login'));
    $this->get(route('generations.estimate'))->assertRedirect(route('login'));
    $this->post(route('generations.store'))->assertRedirect(route('login'));
});

test('validates generation inputs', function (array $changes, string $field) {
    $this->actingAs(User::factory()->create())->post(route('generations.store'), array_replace(generationInput(), $changes))->assertSessionHasErrors($field);
})->with([
    [['prompt' => ''], 'prompt'],
    [['prompt' => str_repeat('a', 4001)], 'prompt'],
    [['size' => 'invalid'], 'size'],
    [['reference_images' => array_fill(0, 5, 'https://example.com/a')], 'reference_images'],
    [['reference_images' => ['ftp://example.com/a']], 'reference_images.0'],
]);

test('creates a queued generation with the estimated charge and dispatches submission', function () {
    Queue::fake();
    Http::fake(['*' => Http::response(generationPricing())]);
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('generations.store'), generationInput())->assertRedirect(route('generations.index'));
    $generation = $user->generations()->sole();
    expect($generation->status->value)->toBe('queued')->and($generation->estimated_charge_min_nano)->toBe(357781250)->and($generation->estimated_charge_max_nano)->toBe(467343750);
    Queue::assertPushed(SubmitGeneration::class, fn ($job) => $job->generation->is($generation));
    Http::assertSentCount(1);
});

test('returns the estimate', function () {
    Http::fake(['*' => Http::response(generationPricing())]);
    $this->actingAs(User::factory()->create())->getJson(route('generations.estimate', ['chars' => 400, 'size' => '1536x1024', 'quality' => 'high', 'references' => 2]))
        ->assertOk()->assertExactJson(['min_nano' => 357781250, 'max_nano' => 467343750]);
});

test('returns a prompt error when pricing is unavailable', function () {
    Queue::fake();
    Http::fake(['*' => Http::response([], 503)]);
    $this->actingAs(User::factory()->create())->post(route('generations.store'), generationInput())
        ->assertSessionHasErrors(['prompt' => 'Pricing is temporarily unavailable. Please try again in a moment.']);
    expect(Generation::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('returns only the current users generations with the history contract', function () {
    $user = User::factory()->create();
    $generation = Generation::factory()->create(['user_id' => $user->id, 'charge_nano' => 123, 'image_url_expires_at' => now()->subMinute()]);
    Generation::factory()->create();
    $this->actingAs($user)->get(route('generations.index'))->assertInertia(fn (Assert $page) => $page
        ->component('generations/Index')->has('generations.data', 1)
        ->where('generations.data.0.id', $generation->id)
        ->where('generations.data.0.charge_exact', '0.000000123')
        ->where('generations.data.0.image_expired', true)
        ->has('generations.data.0', fn (Assert $item) => $item->hasAll(['id', 'prompt', 'size', 'quality', 'reference_images', 'status', 'status_note', 'estimated_charge_min_nano', 'estimated_charge_max_nano', 'charge_nano', 'charge_exact', 'image_url', 'image_expired', 'error_message', 'created_at', 'completed_at'])));
});
