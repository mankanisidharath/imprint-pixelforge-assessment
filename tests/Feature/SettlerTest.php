<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\GenerationSettler;

function settlementSnapshot(string $id, string $status = 'succeeded', array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $id, 'status' => $status, 'metadata' => [],
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'usage' => ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184],
        'image_url' => 'https://example.com/image.png', 'image_url_expires_at' => '2026-11-18T00:00:00Z',
        'started_at' => '2026-11-17T00:00:00Z', 'completed_at' => '2026-11-17T00:01:00Z',
    ], $overrides);
}

test('settles processing and success exactly once and preserves final state', function () {
    $generation = Generation::factory()->accepted()->create();
    $settler = app(GenerationSettler::class);
    expect($settler->apply(settlementSnapshot($generation->provider_id, 'processing')))->toBe('applied');
    $snapshot = settlementSnapshot($generation->provider_id);
    expect($settler->apply($snapshot))->toBe('applied');
    $before = $generation->fresh()->getAttributes();
    expect($settler->apply($snapshot))->toBe('final')
        ->and($settler->apply(settlementSnapshot($generation->provider_id, 'processing')))->toBe('final')
        ->and($settler->apply(settlementSnapshot($generation->provider_id, 'failed')))->toBe('final')
        ->and($generation->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'succeeded', 'provider_cost_nano' => 305510000, 'charge_nano' => 381887500]);
});

test('provider failure is final and costs nothing', function () {
    $generation = Generation::factory()->accepted()->create();
    $settler = app(GenerationSettler::class);
    expect($settler->apply(settlementSnapshot($generation->provider_id, 'failed')))->toBe('applied')
        ->and($settler->apply(settlementSnapshot($generation->provider_id)))->toBe('final');
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'status' => 'failed', 'charge_nano' => 0, 'error_code' => 'generation_failed']);
});

test('matches a local id and recovers a local failure', function () {
    $generation = Generation::factory()->queued()->create(['status' => GenerationStatus::Failed, 'error_code' => 'submit_timeout']);
    expect(app(GenerationSettler::class)->apply(settlementSnapshot('gen_recovered', 'succeeded', ['metadata' => ['local_id' => $generation->id]])))->toBe('applied');
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'provider_id' => 'gen_recovered', 'status' => 'succeeded', 'error_code' => null]);
});

test('ignores invalid local ids and mismatched provider ids', function () {
    $generation = Generation::factory()->accepted()->create();
    $settler = app(GenerationSettler::class);
    expect($settler->apply(settlementSnapshot('gen_unknown', 'processing', ['metadata' => ['local_id' => 'not-a-uuid']])))->toBe('unknown_generation')
        ->and($settler->apply(settlementSnapshot('gen_other', 'processing', ['metadata' => ['local_id' => $generation->id]])))->toBe('unknown_generation');
});

test('rolls back a success without usage', function () {
    $generation = Generation::factory()->queued()->create();
    $snapshot = settlementSnapshot('gen_missing', 'succeeded', ['metadata' => ['local_id' => $generation->id]]);
    $snapshot['usage'] = null;
    try {
        app(GenerationSettler::class)->apply($snapshot);
        $this->fail('Expected missing usage to throw.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('usage');
    }
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'provider_id' => null, 'status' => 'queued', 'rates' => null]);
});

test('uses snapshot rates instead of stored rates', function () {
    $generation = Generation::factory()->accepted()->create(['rates' => ['input_text_tokens' => '999.00']]);
    $snapshot = settlementSnapshot($generation->provider_id, 'succeeded', ['rates' => ['output_tokens' => '20.00']]);
    expect(app(GenerationSettler::class)->apply($snapshot))->toBe('applied');
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'provider_cost_nano' => 176510000]);
});
