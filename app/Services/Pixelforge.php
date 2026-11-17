<?php

namespace App\Services;

use App\Models\Generation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Pixelforge
{
    public function create(Generation $generation): Response
    {
        $referenceImages = $generation->reference_images ?? [];

        $payload = [
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'metadata' => [
                'local_id' => $generation->getKey(),
            ],
        ];

        if ($referenceImages !== []) {
            $payload['reference_images'] = $referenceImages;
        }

        return Http::baseUrl((string) config('services.pixelforge.base_url'))
            ->withToken((string) config('services.pixelforge.key'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->withHeader('Idempotency-Key', $generation->getKey())
            ->post('/generations', $payload);
    }

    public function find(string $providerId): Response
    {
        return Http::baseUrl((string) config('services.pixelforge.base_url'))
            ->withToken((string) config('services.pixelforge.key'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->get('/generations/'.$providerId);
    }

    public function usage(string $startDate, string $endDate): Response
    {
        return Http::baseUrl((string) config('services.pixelforge.base_url'))
            ->withToken((string) config('services.pixelforge.key'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->get('/usage', [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
    }

    public function pricing(): array
    {
        return Cache::remember('pixelforge.pricing', 600, function (): array {
            return Http::baseUrl((string) config('services.pixelforge.base_url'))
                ->withToken((string) config('services.pixelforge.key'))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->get('/pricing')
                ->throw()
                ->json();
        });
    }
}
