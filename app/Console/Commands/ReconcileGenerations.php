<?php

namespace App\Console\Commands;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\GenerationSettler;
use App\Services\Pixelforge;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class ReconcileGenerations extends Command
{
    protected $signature = 'generations:reconcile';

    protected $description = 'Reconcile unfinished Pixelforge generations';

    public function handle(Pixelforge $pixelforge, GenerationSettler $settler): int
    {
        $polled = 0;
        $generations = Generation::query()->whereNotNull('provider_id')
            ->whereIn('status', [GenerationStatus::Queued->value, GenerationStatus::Processing->value])
            ->where('created_at', '<', now()->subMinutes(2))->oldest()->limit(20)->get();

        foreach ($generations as $generation) {
            try {
                $response = $pixelforge->find($generation->provider_id);
                $polled++;
                if ($response->status() === 429) {
                    break;
                }
                if ($response->status() === 200) {
                    $settler->apply($response->json());
                } else {
                    Log::warning('Pixelforge reconciliation failed.', ['provider_id' => $generation->provider_id, 'status' => $response->status()]);
                }
            } catch (ConnectionException $exception) {
                Log::warning('Pixelforge reconciliation connection failed.', ['provider_id' => $generation->provider_id, 'error' => $exception->getMessage()]);
            }
        }

        $timedOut = Generation::query()->whereNull('provider_id')->where('status', GenerationStatus::Queued->value)
            ->where('created_at', '<', now()->subMinutes(15))->update([
                'status' => GenerationStatus::Failed->value,
                'error_code' => 'submit_timeout',
                'error_message' => 'We could not reach the image provider. You were not charged.',
                'provider_cost_nano' => 0,
                'charge_nano' => 0,
            ]);

        $this->info("Polled {$polled} generations; timed out {$timedOut} submissions.");

        return self::SUCCESS;
    }
}
