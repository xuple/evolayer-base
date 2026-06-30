<?php

use Illuminate\Support\Facades\Route;
use Xuple\EvoLayer\Base\Tests\Fixtures\TestUser;

test('marketing routes point at the published evolayer page components', function () {
    expect(Route::getRoutes()->getByName('evolayer.base.about')->defaults['component'] ?? null)
        ->toBe('evolayer/about')
        ->and(Route::getRoutes()->getByName('evolayer.base.home')->defaults['component'] ?? null)
        ->toBe('evolayer/home');
});

test('authenticated home renders evolayer/home with a server-computed greetingHour prop', function () {
    $user = TestUser::factory()->create(['email_verified_at' => now()]);

    // An Inertia XHR request returns the page object as JSON, so the prop
    // contract is asserted without needing a root Blade view in testbench.
    $page = $this->actingAs($user)
        ->get('/home', ['X-Inertia' => 'true'])
        ->assertOk()
        ->json();

    expect($page['component'])->toBe('evolayer/home')
        ->and($page['props'])->toHaveKey('greetingHour')
        ->and($page['props']['greetingHour'])->toBeInt();
});
