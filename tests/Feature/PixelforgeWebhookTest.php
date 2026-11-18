<?php

test('accepts signed Pixelforge webhooks without a CSRF token', function () {
    $secret = 'test-secret';
    config(['services.pixelforge.webhook_secret' => $secret]);
    $payload = json_encode(['id' => 'evt_123', 'object' => 'event', 'type' => 'ping']);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    $response = $this->call('POST', '/webhooks/pixelforge', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $payload);

    $response->assertNoContent();
    $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_123', 'outcome' => 'ping']);
});
