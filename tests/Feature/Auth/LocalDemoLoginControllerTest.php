<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('only signs into the seeded demo identity when explicitly enabled', function () {
    config(['portmyfolio.demo_login_enabled' => true]);
    $demo = User::factory()->create(['google_id' => 'demo-admin', 'email' => 'admin@example.com']);
    $other = User::factory()->create();

    $this->post(route('demo.login'), ['email' => $other->email])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($demo);
});

it('returns 404 when local demo login is disabled', function () {
    config(['portmyfolio.demo_login_enabled' => false]);
    $this->post(route('demo.login'))->assertNotFound();
    $this->assertGuest();
});

it('never permits demo login in production even if enabled', function () {
    config(['portmyfolio.demo_login_enabled' => true]);
    User::factory()->create(['google_id' => 'demo-admin', 'email' => 'admin@example.com']);
    $this->app->instance('env', 'production');

    $this->withSession(['_token' => 'demo-csrf-token'])->post(route('demo.login'), ['_token' => 'demo-csrf-token'])->assertNotFound();
    $this->get(route('home'))->assertOk()->assertDontSee('Explore the local demo');
    $this->assertGuest();
});

it('explains missing demo data without creating an account', function () {
    config(['portmyfolio.demo_login_enabled' => true]);
    $this->post(route('demo.login'))->assertRedirect(route('home'))->assertSessionHas('status');
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});
