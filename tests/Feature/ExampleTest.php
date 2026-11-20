<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home page returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});
