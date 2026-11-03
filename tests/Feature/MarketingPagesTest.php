<?php

use App\Models\User;

test('marketing pages load with navigation to home sections', function (string $page, string $heading) {
    $response = $this->get(route($page))->assertOk()
        ->assertSee('<title>'.ucfirst($page).' | My Invoice Tracker</title>', false)
        ->assertSee($heading)
        ->assertSee('aria-current="page"', false);

    foreach (['home', 'features', 'pricing', 'login', 'register'] as $destination) {
        $url = in_array($destination, ['features', 'pricing']) ? route('home').'#'.$destination : route($destination);
        $response->assertSee('href="'.$url.'"', false);
    }
})->with([
    ['home', 'Less paperwork.'],
    ['features', 'Everything has its place.'],
    ['pricing', 'Start free. Grow with Annual.'],
]);

test('standalone pricing retains free and upcoming annual plans', function () {
    $this->get(route('pricing'))->assertOk()->assertSee('Free')->assertSee('$0')
        ->assertSee('Annual')->assertSee('$15')->assertSee('Coming soon')
        ->assertSee('PDF and Excel statement exports');
});

test('signed in users can open every public page and return to their dashboard', function (string $page) {
    $this->actingAs(User::factory()->create())->get(route($page))->assertOk()
        ->assertSee('href="'.route('dashboard').'"', false)
        ->assertDontSee('href="'.route('login').'"', false);
})->with(['home', 'features', 'pricing']);

test('home includes the hero features and pricing in scroll order', function () {
    $this->get(route('home'))->assertOk()
        ->assertSeeInOrder(['id="home-heading"', 'id="features"', 'id="pricing"'], false)
        ->assertSee('Everything has its place.')->assertSee('Start free. Grow with Annual.')
        ->assertSee('href="#features"', false)->assertSee('Coming soon')->assertSee('$15');
});
