<?php

use App\Models\User;

test('the root redirects visitors to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('the root redirects authenticated users to the dashboard', function () {
    $this->actingAs(User::factory()->create())->get(route('home'))->assertRedirect(route('dashboard'));
});
