<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires sign-in before username onboarding', function () {
    $this->get(route('userzone.username.edit'))->assertRedirect(route('login'));
    $this->post(route('userzone.username.store'), ['username' => 'sam-example'])->assertRedirect(route('login'));
});

it('directs new accounts to onboarding before the workspace', function () {
    $this->actingAs(User::factory()->withoutUsername()->create())
        ->get(route('dashboard'))->assertRedirect(route('userzone.username.edit'));
});

it('normalizes a username and only changes the permitted field', function () {
    $owner = User::factory()->withoutUsername()->create();

    $this->actingAs($owner)->post(route('userzone.username.store'), ['username' => '  Sam-Example  ', 'google_id' => 'spoofed', 'default_portfolio_id' => 123])
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('users', ['id' => $owner->id, 'username' => 'sam-example', 'google_id' => $owner->google_id, 'default_portfolio_id' => null]);
});

it('rejects invalid usernames with feedback', function (mixed $username, string $message) {
    $owner = User::factory()->withoutUsername()->create();

    $this->actingAs($owner)->from(route('userzone.username.edit'))
        ->post(route('userzone.username.store'), ['username' => $username])
        ->assertRedirect(route('userzone.username.edit'))
        ->assertSessionHasErrors(['username' => $message]);

    expect($owner->fresh()->username)->toBeNull();
})->with([
    'missing' => ['', 'The username field is required.'],
    'too short' => ['ab', 'The username field must be at least 3 characters.'],
    'too long' => [str_repeat('a', 31), 'The username field must not be greater than 30 characters.'],
    'invalid characters' => ['sam_example', 'Start with a letter and use lowercase letters, numbers, and single hyphens.'],
    'numeric start' => ['123sam', 'Start with a letter and use lowercase letters, numbers, and single hyphens.'],
    'reserved' => ['DASHBOARD', 'This username is reserved. Please choose another.'],
]);

it('rejects usernames already belonging to another account', function () {
    User::factory()->create(['username' => 'sam-example']);
    $owner = User::factory()->withoutUsername()->create();

    $this->actingAs($owner)->post(route('userzone.username.store'), ['username' => 'SAM-EXAMPLE'])
        ->assertSessionHasErrors(['username' => 'This username is already taken.']);
    expect($owner->fresh()->username)->toBeNull();
});

it('does not expose username changes after onboarding', function () {
    $owner = User::factory()->create(['username' => 'sam-example']);
    $this->actingAs($owner)->get(route('userzone.username.edit'))->assertRedirect(route('dashboard'));
    $this->post(route('userzone.username.store'), ['username' => 'different'])->assertForbidden();
    expect($owner->fresh()->username)->toBe('sam-example');
});
