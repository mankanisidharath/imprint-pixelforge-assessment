<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\GenerationSettler;
use App\Services\Pixelforge;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $maxExceptions = 3;

    public function __construct(public Generation $generation) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    public function handle(Pixelforge $pixelforge, GenerationSettler $settler): void
    {
        $generation = Generation::find($this->generation->getKey());
        if (! $generation || $generation->provider_id !== null || $generation->status !== GenerationStatus::Queued) {
            return;
        }

        try {
            $response = $pixelforge->create($generation);
        } catch (ConnectionException $exception) {
            $this->retryFailure(2);

            return;
        }

        if ($response->status() === 202) {
            $settler->apply($response->json());

            return;
        }

        $code = $response->json('error.code');
        $retryAfter = max(1, (int) $response->header('Retry-After'));
        if ($response->status() === 429 && $code === 'concurrency_limit_exceeded') {
            $this->updateQueued(['status_note' => 'Waiting for a free slot']);
            $this->release(min($retryAfter * $this->attempts(), 30) + random_int(0, 2));
        } elseif ($response->status() === 429 && $code === 'rate_limit_exceeded') {
            $this->updateQueued(['status_note' => 'Waiting (provider rate limit)']);
            $this->release($retryAfter + random_int(0, 2));
        } elseif ($response->serverError()) {
            $this->retryFailure($code === 'overloaded' && $response->status() === 503 ? $retryAfter : 2);
        } elseif ($response->status() === 429 && $code === 'daily_limit_exceeded') {
            $this->reject($code, 'Daily generation capacity reached. Try again after 00:00 UTC.');
        } elseif ($response->status() === 400 && $code === 'content_policy_violation') {
            $this->reject($code, 'Your prompt was rejected by the content policy.');
        } elseif ($response->status() === 422 && $code === 'validation_failed') {
            $this->reject($code, (string) $response->json('error.message'));
        } else {
            Log::error('Pixelforge rejected generation submission', ['code' => $code]);
            $this->reject($code ?? 'unknown', 'The image provider rejected the request.');
        }
    }

    public function failed(?Throwable $e): void
    {
        $this->reject('submit_timeout', 'The image provider was busy for too long.');
    }

    private function retryFailure(int $base): void
    {
        $updated = Generation::query()->whereKey($this->generation->getKey())
            ->where('status', GenerationStatus::Queued->value)->whereNull('provider_id')
            ->increment('submit_failures', 1, ['status_note' => 'Provider busy, retrying']);
        if (! $updated) {
            return;
        }
        $failures = Generation::find($this->generation->getKey())?->submit_failures;
        if ($failures === null) {
            return;
        }
        if ($failures >= 5) {
            $this->reject('provider_unavailable', 'The image provider is overloaded. Please try again.');

            return;
        }
        $this->release($base * 2 ** ($failures - 1) + random_int(0, 2));
    }

    private function updateQueued(array $values): void
    {
        Generation::query()->whereKey($this->generation->getKey())
            ->where('status', GenerationStatus::Queued->value)->whereNull('provider_id')->update($values);
    }

    private function reject(string $code, string $message): void
    {
        $this->updateQueued([
            'status' => GenerationStatus::Failed->value,
            'status_note' => null,
            'error_code' => $code,
            'error_message' => $message.' You were not charged.',
            'provider_cost_nano' => 0,
            'charge_nano' => 0,
        ]);
    }
}
