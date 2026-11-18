<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('analytics requires authentication', function () {
    $this->get(route('generations.analytics'))->assertRedirect(route('login'));
});

test('summarizes only the users finished generations in the last 30 UTC days', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-19 12:00:00', 'UTC'));
    $user = User::factory()->create();
    Generation::factory()->succeeded()->create(['user_id' => $user->id, 'completed_at' => '2026-11-19 00:00:00', 'charge_nano' => 123456789]);
    Generation::factory()->failed()->create(['user_id' => $user->id, 'completed_at' => '2026-11-19 10:00:00', 'charge_nano' => 0]);
    Generation::factory()->succeeded()->create(['user_id' => $user->id, 'completed_at' => '2026-10-21 00:00:00', 'charge_nano' => 11]);
    Generation::factory()->succeeded()->create(['user_id' => $user->id, 'completed_at' => '2026-10-20 23:59:59', 'charge_nano' => 500]);
    Generation::factory()->succeeded()->create(['completed_at' => '2026-11-19 11:00:00', 'charge_nano' => 500]);
    Generation::factory()->queued()->create(['user_id' => $user->id]);
    Generation::factory()->create(['user_id' => $user->id, 'status' => 'failed', 'completed_at' => null, 'updated_at' => '2026-11-18 20:00:00', 'charge_nano' => 0]);

    $this->actingAs($user)->get(route('generations.analytics'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('generations/Analytics')
        ->where('analytics.start', '2026-10-21')->where('analytics.end', '2026-11-19')
        ->where('analytics.total', 4)->where('analytics.succeeded', 2)->where('analytics.failed', 2)
        ->where('analytics.charged', '0.123456800')
        ->has('analytics.days', 3)
        ->where('analytics.days.0.date', '2026-11-19')->where('analytics.days.0.total', 2)
        ->where('analytics.days.0.succeeded', 1)->where('analytics.days.0.failed', 1)
        ->where('analytics.days.0.charged', '0.123456789')
        ->where('analytics.days.1.date', '2026-11-18')->where('analytics.days.1.failed', 1)
        ->where('analytics.days.2.date', '2026-10-21')
    );

    Carbon::setTestNow();
});

test('returns an empty overview when there are no finished generations', function () {
    $this->actingAs(User::factory()->create())->get(route('generations.analytics'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('analytics.total', 0)->where('analytics.charged', '0.000000000')->has('analytics.days', 0));
});
