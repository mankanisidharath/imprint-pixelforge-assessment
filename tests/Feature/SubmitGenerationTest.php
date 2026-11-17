<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Services\GenerationSettler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.pixelforge.base_url', 'https://pixelforge.test/v1');
    Http::preventStrayRequests();
});

function runSubmission(Generation $generation): SubmitGeneration
{
    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    return $job;
}

test('passes an accepted snapshot to the settler', function () {
    $generation = Generation::factory()->queued()->create();
    $body = ['id' => 'gen_123', 'status' => 'queued'];
    Http::fake(['*' => Http::response($body, 202)]);
    $this->mock(GenerationSettler::class)->shouldReceive('apply')->once()->with($body)->andReturn('applied');
    runSubmission($generation);
});

test('does not submit an accepted generation', function () {
    Http::fake();
    $this->mock(GenerationSettler::class)->shouldNotReceive('apply');
    runSubmission(Generation::factory()->accepted()->create());
    Http::assertNothingSent();
});

test('waits for provider limits without recording a failure', function (string $code, int $delay, string $note) {
    $generation = Generation::factory()->queued()->create();
    Http::fake(['*' => Http::response(['error' => ['code' => $code]], 429, ['Retry-After' => $delay])]);
    $this->mock(GenerationSettler::class)->shouldNotReceive('apply');
    $job = runSubmission($generation);
    expect($job->job->releaseDelay)->toBeBetween($delay, $delay + 2);
    $job->assertReleased($job->job->releaseDelay);
    expect($generation->fresh()->status)->toBe(GenerationStatus::Queued)
        ->and($generation->fresh()->status_note)->toBe($note)
        ->and($generation->fresh()->submit_failures)->toBe(0);
})->with([
    ['concurrency_limit_exceeded', 5, 'Waiting for a free slot'],
    ['rate_limit_exceeded', 17, 'Waiting (provider rate limit)'],
]);

test('retries overload and rejects the fifth failure', function () {
    $generation = Generation::factory()->queued()->create();
    Http::fake(['*' => Http::response(['error' => ['code' => 'overloaded']], 503, ['Retry-After' => 2])]);
    $this->mock(GenerationSettler::class)->shouldNotReceive('apply');
    $job = runSubmission($generation);
    $job->assertReleased($job->job->releaseDelay);
    expect($job->job->releaseDelay)->toBeBetween(2, 4);
    expect($generation->fresh()->submit_failures)->toBe(1);
    $generation->update(['submit_failures' => 4]);
    runSubmission($generation);
    expect($generation->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->fresh()->error_code)->toBe('provider_unavailable')
        ->and($generation->fresh()->charge_nano)->toBe(0)
        ->and($generation->fresh()->error_message)->toEndWith('You were not charged.');
});

test('rejects permanent provider errors', function (int $status, string $code, string $message) {
    $generation = Generation::factory()->queued()->create();
    Http::fake(['*' => Http::response(['error' => ['code' => $code, 'message' => 'Invalid input']], $status)]);
    $this->mock(GenerationSettler::class)->shouldNotReceive('apply');
    runSubmission($generation);
    expect($generation->fresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->fresh()->error_code)->toBe($code)
        ->and($generation->fresh()->error_message)->toBe($message.' You were not charged.');
})->with([
    [400, 'content_policy_violation', 'Your prompt was rejected by the content policy.'],
    [429, 'daily_limit_exceeded', 'Daily generation capacity reached. Try again after 00:00 UTC.'],
    [422, 'validation_failed', 'Invalid input'],
]);

test('retries a connection failure', function () {
    $generation = Generation::factory()->queued()->create();
    Http::fake(fn () => throw new ConnectionException('timeout'));
    $this->mock(GenerationSettler::class)->shouldNotReceive('apply');
    $job = runSubmission($generation);
    $job->assertReleased($job->job->releaseDelay);
    expect($generation->fresh()->submit_failures)->toBe(1)->and($generation->fresh()->status)->toBe(GenerationStatus::Queued);
});

test('does not overwrite provider acceptance that arrives during submission', function () {
    $generation = Generation::factory()->queued()->create();
    Http::fake(function () use ($generation) {
        $generation->update(['provider_id' => 'gen_accepted']);

        return Http::response(['error' => ['code' => 'content_policy_violation']], 400);
    });
    $this->mock(GenerationSettler::class)->shouldNotReceive('apply');
    runSubmission($generation);
    expect($generation->fresh()->status)->toBe(GenerationStatus::Queued)->and($generation->fresh()->provider_id)->toBe('gen_accepted');
});

test('marks a timed out submission as failed', function () {
    $generation = Generation::factory()->queued()->create();
    (new SubmitGeneration($generation))->failed(null);
    expect($generation->fresh()->error_code)->toBe('submit_timeout')->and($generation->fresh()->charge_nano)->toBe(0);
});
