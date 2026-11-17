<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\Services\GenerationSettler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, GenerationSettler $settler): Response
    {
        $payload = $request->getContent();
        $header = $request->header('Pixelforge-Signature', '');
        abort_unless(preg_match('/\At=(\d+),v1=([a-fA-F0-9]{64})\z/', $header, $matches) === 1, 400);
        $secret = config('services.pixelforge.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '', 400);
        $expected = hash_hmac('sha256', $matches[1].'.'.$payload, $secret);
        abort_unless(abs(time() - (int) $matches[1]) <= 300 && hash_equals($expected, $matches[2]), 400);

        $event = json_decode($payload, true);
        abort_unless(is_array($event) && isset($event['id'], $event['type']) && is_string($event['id']) && $event['id'] !== '' && is_string($event['type']) && $event['type'] !== '', 400);

        DB::transaction(function () use ($event, $payload, $request, $settler): void {
            $inserted = WebhookEvent::query()->insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'provider_generation_id' => $event['type'] === 'ping' ? null : data_get($event, 'data.object.id'),
                'delivery_attempt' => $request->header('Pixelforge-Delivery-Attempt'),
                'payload' => $payload,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted === 0) {
                return;
            }

            $outcome = match (true) {
                $event['type'] === 'ping' => 'ping',
                Str::startsWith($event['type'], 'generation.') => $settler->apply(data_get($event, 'data.object')),
                default => 'ignored',
            };
            WebhookEvent::query()->where('event_id', $event['id'])->update(['outcome' => $outcome]);
        });

        return response()->noContent();
    }
}
