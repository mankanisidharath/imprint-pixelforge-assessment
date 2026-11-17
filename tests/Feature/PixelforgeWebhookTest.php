<?php

test('accepts Pixelforge webhooks without a CSRF token', function () {
    $response = $this->postJson('/webhooks/pixelforge', [
        'id' => 'evt_123',
        'object' => 'event',
    ]);

    $response->assertNoContent();
});
