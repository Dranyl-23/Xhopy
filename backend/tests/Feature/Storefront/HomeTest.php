<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('storefront home renders clean shell without fabricated products by default', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('isDemoPreview', false)
            ->where('announcement', null)
            ->where('dailyInterest', null)
            ->has('categories', 0)
            ->has('topProducts', 0)
            ->where('cartSummary.itemCount', 0)
            ->has('hero', fn (Assert $hero) => $hero
                ->where('badgeText', 'XHopy Official Store')
                ->etc()
            )
        );
});

test('storefront home supports explicitly labeled demo preview in non-production environments', function () {
    $response = $this->get(route('home', ['preview_demo' => 1]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('isDemoPreview', true)
            ->has('categories', 4)
            ->has('topProducts', 4)
            ->has('dailyInterest.featured')
            ->has('dailyInterest.tiles', 2)
        );
});

test('storefront home filters demo products by search query and category allowlist', function () {
    $response = $this->get(route('home', [
        'preview_demo' => 1,
        'category' => 'audio-acoustics',
        'q' => 'DAC',
    ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('searchQuery', 'DAC')
            ->where('selectedCategory', 'audio-acoustics')
            ->has('topProducts', 1)
            ->where('topProducts.0.sku', 'DEMO-AUD-004')
        );
});

test('storefront home rejects invalid category parameter format at server boundary', function () {
    $response = $this->get(route('home', [
        'category' => 'invalid category with spaces & symbols!',
    ]));

    $response->assertSessionHasErrors(['category']);
});
