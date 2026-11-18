<?php

use App\Models\Generation;

function webhookSnapshot(string $id, string $status = 'succeeded'): array
{
    return ['id' => $id, 'status' => $status, 'metadata' => [], 'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'], 'usage' => ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184], 'completed_at' => '2026-11-17T00:01:00Z'];
}

beforeEach(function () {
    config(['services.pixelforge.webhook_secret' => 'test-secret']);
});

test('settles a signed event once and ignores late events', function () {
    $generation = Generation::factory()->accepted()->create();
    $event = ['id' => 'evt_one', 'type' => 'generation.succeeded', 'data' => ['object' => webhookSnapshot($generation->provider_id)]];
    signedWebhook($event)->assertNoContent();
    signedWebhook($event)->assertNoContent();
    signedWebhook(['id' => 'evt_two', 'type' => 'generation.processing', 'data' => ['object' => webhookSnapshot($generation->provider_id, 'processing')]])->assertNoContent();
    $this->assertDatabaseCount('webhook_events', 2);
    $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_one', 'outcome' => 'applied']);
    $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_two', 'outcome' => 'final']);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded', 'charge_nano' => 381887500]);
});

test('rejects invalid signatures timestamps and altered bodies', function () {
    $event = ['id' => 'evt_bad', 'type' => 'ping'];
    signedWebhook($event, 'wrong')->assertStatus(400);
    signedWebhook($event, 'test-secret', time() - 301)->assertStatus(400);
    signedWebhook($event, 'test-secret', null, '{}')->assertStatus(400);
    $this->postJson('/webhooks/pixelforge', $event)->assertStatus(400);
    $this->assertDatabaseCount('webhook_events', 0);
});

test('records ping and unknown generations', function () {
    signedWebhook(['id' => 'evt_ping', 'type' => 'ping'])->assertNoContent();
    signedWebhook(['id' => 'evt_unknown', 'type' => 'generation.processing', 'data' => ['object' => webhookSnapshot('gen_unknown', 'processing')]])->assertNoContent();
    $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_ping', 'outcome' => 'ping']);
    $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_unknown', 'outcome' => 'unknown_generation']);
});

test('rolls back the event when settlement throws', function () {
    $generation = Generation::factory()->accepted()->create();
    $snapshot = webhookSnapshot($generation->provider_id);
    $snapshot['usage'] = null;
    signedWebhook(['id' => 'evt_error', 'type' => 'generation.succeeded', 'data' => ['object' => $snapshot]])->assertStatus(500);
    $this->assertDatabaseCount('webhook_events', 0);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'queued']);
});
