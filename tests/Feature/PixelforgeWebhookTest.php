<?php

test('accepts signed Pixelforge webhooks without a CSRF token', function () {
    config(['services.pixelforge.webhook_secret' => 'test-secret']);

    $response = signedWebhook([
        'id' => 'evt_123',
        'object' => 'event',
        'type' => 'ping',
    ]);

    $response->assertNoContent();
    $this->assertDatabaseHas('webhook_events', ['event_id' => 'evt_123', 'outcome' => 'ping']);
});
