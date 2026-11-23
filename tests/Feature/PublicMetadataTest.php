<?php

test('public pages include canonical and social metadata', function (string $route) {
    $url = route($route);
    $this->get($url)->assertOk()->assertSee('<link rel="canonical" href="'.$url.'"', false)
        ->assertSee('name="description"', false)->assertSee('property="og:title"', false);
})->with(['home', 'features', 'pricing']);

test('sitemap lists public pages', function () {
    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');
    foreach (['home', 'features', 'pricing'] as $route) {
        $response->assertSee('<loc>'.route($route).'</loc>', false);
    }
});
