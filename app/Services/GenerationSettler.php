<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Features\Pixelforge\TokenType;
use App\Models\Generation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GenerationSettler
{
    public function __construct(private CostCalculator $calculator) {}

    public function apply(array $snapshot): string
    {
        return DB::transaction(function () use ($snapshot): string {
            $localId = data_get($snapshot, 'metadata.local_id');
            $generation = Generation::query()->where(function (Builder $query) use ($snapshot, $localId): void {
                $query->where('provider_id', $snapshot['id']);
                if (is_string($localId) && Str::isUuid($localId)) {
                    $query->orWhere('id', $localId);
                }
            })->lockForUpdate()->first();

            if (! $generation || ($generation->provider_id !== null && $generation->provider_id !== $snapshot['id'])) {
                return 'unknown_generation';
            }

            $localFailure = $generation->status === GenerationStatus::Failed && $generation->provider_id === null;
            if ($generation->status->isFinal() && ! $localFailure) {
                return 'final';
            }

            $generation->provider_id = $snapshot['id'];
            $generation->rates ??= $snapshot['rates'] ?? null;
            if ($generation->started_at === null && ! empty($snapshot['started_at'])) {
                $generation->started_at = Carbon::parse($snapshot['started_at'])->utc();
            }

            $status = GenerationStatus::from($snapshot['status']);
            if (! $localFailure && $status->rank() <= $generation->status->rank()) {
                $generation->save();

                return 'stale';
            }

            $generation->status = $status;
            $generation->status_note = null;
            $generation->error_code = null;
            $generation->error_message = null;

            if ($status === GenerationStatus::Succeeded) {
                $usage = $snapshot['usage'] ?? null;
                if (! is_array($usage)) {
                    throw new RuntimeException('Succeeded generation has no usage.');
                }
                $cost = $this->calculator->providerCost($usage, $snapshot['rates']);
                foreach (TokenType::BILLABLE_KEYS as $key) {
                    $generation->{$key} = $usage[$key];
                }
                $generation->provider_cost_nano = $cost;
                $generation->charge_nano = $this->calculator->charge($cost);
                $generation->image_url = $snapshot['image_url'] ?? null;
                $generation->image_url_expires_at = isset($snapshot['image_url_expires_at']) ? Carbon::parse($snapshot['image_url_expires_at'])->utc() : null;
                $generation->completed_at = isset($snapshot['completed_at']) ? Carbon::parse($snapshot['completed_at'])->utc() : null;
                // Balances (later phase): debit the user here, inside this transaction.
            } elseif ($status === GenerationStatus::Failed) {
                $generation->provider_cost_nano = 0;
                $generation->charge_nano = 0;
                $generation->error_code = data_get($snapshot, 'error.code', 'generation_failed');
                $generation->error_message = data_get($snapshot, 'error.message', 'Generation failed.');
                $generation->completed_at = isset($snapshot['completed_at']) ? Carbon::parse($snapshot['completed_at'])->utc() : null;
            }

            $generation->save();

            return 'applied';
        });
    }
}
