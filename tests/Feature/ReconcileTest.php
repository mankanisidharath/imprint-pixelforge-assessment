<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\Http;

test('polls old generations and times out lost submissions', function () {
    $this->freezeTime();
    $old = Generation::factory()->accepted()->create(['status' => GenerationStatus::Processing, 'created_at' => now()->subMinutes(3)]);
    $fresh = Generation::factory()->accepted()->create(['created_at' => now()]);
    $lost = Generation::factory()->queued()->create(['created_at' => now()->subMinutes(16)]);
    Http::preventStrayRequests();
    Http::fake(['*/generations/'.$old->provider_id => Http::response(['id' => $old->provider_id, 'status' => 'succeeded', 'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'], 'usage' => ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184]], 200)]);
    $this->artisan('generations:reconcile')->assertSuccessful();
    Http::assertSentCount(1);
    $this->assertDatabaseHas('generations', ['id' => $old->id, 'status' => 'succeeded', 'charge_nano' => 381887500]);
    $this->assertDatabaseHas('generations', ['id' => $fresh->id, 'status' => 'queued']);
    $this->assertDatabaseHas('generations', ['id' => $lost->id, 'status' => 'failed', 'error_code' => 'submit_timeout', 'charge_nano' => 0]);
});

test('stops polling after rate limiting', function () {
    $this->freezeTime();
    $first = Generation::factory()->accepted()->create(['created_at' => now()->subMinutes(5)]);
    Generation::factory()->accepted()->create(['created_at' => now()->subMinutes(4)]);
    Http::preventStrayRequests();
    Http::fake(['*/generations/'.$first->provider_id => Http::response([], 429)]);
    $this->artisan('generations:reconcile')->assertSuccessful();
    Http::assertSentCount(1);
});
