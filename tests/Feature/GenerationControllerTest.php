<?php

use App\Models\Generation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('provides the supported generation options and limits', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('generations.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('generations/Create')
            ->where('sizes', ['1024x1024', '1536x1024', '1024x1536'])
            ->where('qualities', ['low', 'medium', 'high'])
            ->where('maxPromptLength', 4000)
            ->where('maxReferenceImages', 4)
        );
});

test('uses the default generation size and quality in factories', function () {
    $generation = Generation::factory()->make();

    expect($generation->size)->toBe('1024x1024');
    expect($generation->quality)->toBe('medium');
});
