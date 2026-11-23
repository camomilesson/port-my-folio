<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_missing_configuration_gives_a_useful_message(): void
    {
        config(['services.google.client_id' => null]);
        $this->get('/login')->assertRedirect('/')->assertSessionHas('status');
    }

    public function test_callback_creates_a_google_account_and_logs_in(): void
    {
        $this->mockIdentity('google-123', 'owner@example.com');
        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs(User::where('google_id', 'google-123')->firstOrFail());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_returning_identity_is_matched_by_google_id_even_if_email_changes(): void
    {
        $owner = User::factory()->create(['google_id' => 'google-123']);
        $this->mockIdentity('google-123', 'changed@example.com');
        $this->get('/auth/google/callback')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($owner);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_callback_does_not_link_accounts_by_email(): void
    {
        User::factory()->create(['email' => 'owner@example.com', 'google_id' => 'other-id']);
        $this->mockIdentity('google-123', 'owner@example.com');
        $this->get('/auth/google/callback')->assertRedirect('/')->assertSessionHas('status');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_oauth_state_does_not_authenticate(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        $this->get('/auth/google/callback')->assertRedirect('/')->assertSessionHas('status');
        $this->assertGuest();
    }

    public function test_cancelled_login_does_not_call_google(): void
    {
        Socialite::shouldReceive('driver')->never();
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_logout_invalidates_authentication(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    private function mockIdentity(string $id, string $email): void
    {
        $identity = (new GoogleUser)->map(['id' => $id, 'email' => $email, 'name' => 'Portfolio Owner']);
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($identity);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
