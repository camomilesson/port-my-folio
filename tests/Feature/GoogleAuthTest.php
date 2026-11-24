<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

it('missing configuration gives a useful message', function () {
    config(['services.google.client_id' => null]);
    $this->get('/login')->assertRedirect('/')->assertSessionHas('status');
});

it('callback creates a google account and logs in', function () {
    mockGoogleIdentity('google-123', 'owner@example.com');
    $this->get('/auth/google/callback')->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs(User::where('google_id', 'google-123')->firstOrFail());
    $this->assertDatabaseCount('users', 1);
});

it('returning identity is matched by google id even if email changes', function () {
    $owner = User::factory()->create(['google_id' => 'google-123']);
    mockGoogleIdentity('google-123', 'changed@example.com');
    $this->get('/auth/google/callback')->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($owner);
    $this->assertDatabaseCount('users', 1);
});

it('callback does not link accounts by email', function () {
    User::factory()->create(['email' => 'owner@example.com', 'google_id' => 'other-id']);
    mockGoogleIdentity('google-123', 'owner@example.com');
    $this->get('/auth/google/callback')->assertRedirect('/')->assertSessionHas('status');
    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
});

it('invalid oauth state does not authenticate', function () {
    $provider = Mockery::mock();
    $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    $this->get('/auth/google/callback')->assertRedirect('/')->assertSessionHas('status');
    $this->assertGuest();
});

it('cancelled login does not call google', function () {
    Socialite::shouldReceive('driver')->never();
    $this->get('/auth/google/callback?error=access_denied')->assertRedirect('/');
    $this->assertGuest();
});

it('logout invalidates authentication', function () {
    $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

function mockGoogleIdentity(string $id, string $email): void
{
    $identity = (new GoogleUser)->map(['id' => $id, 'email' => $email, 'name' => 'Portfolio Owner']);
    $provider = Mockery::mock();
    $provider->shouldReceive('user')->once()->andReturn($identity);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}
