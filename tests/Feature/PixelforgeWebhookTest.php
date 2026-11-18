<?php

test('accepts Pixelforge webhooks without a CSRF token', function () {
    config(['services.pixelforge.webhook_secret' => 'test-secret']);

    $response = signedWebhook([
        'id' => 'evt_123',
        'object' => 'event',
        'type' => 'ping',
    ]);

    $response->assertNoContent();
});
