<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests to sign in', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders the owner workspace with an escaped account name', function () {
    $owner = User::factory()->create(['name' => '<script>alert(1)</script>']);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertViewIs('userzone.dashboard')
        ->assertSee($owner->name)
        ->assertDontSee($owner->name, false);
});
