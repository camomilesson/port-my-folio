<?php

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('offers sign-in without redirecting or revealing a private portfolio', function () {
    $portfolio = Portfolio::factory()->create(['title' => 'Private draft title', 'focus' => 'Private draft focus']);

    $this->get(route('userzone.portfolios.show', $portfolio))->assertNotFound()
        ->assertSee("We couldn't find a public portfolio at this address.", false)
        ->assertSee('If this is your portfolio, sign in')
        ->assertSee(route('login'))
        ->assertDontSee($portfolio->title)->assertDontSee($portfolio->focus)
        ->assertHeaderMissing('Location');
});

it('offers the same sign-in page for a missing owner URL or public address', function (string $url) {
    $this->get($url)->assertNotFound()
        ->assertSee("We couldn't find a public portfolio at this address.", false)
        ->assertSee('Sign in with Google')->assertHeaderMissing('Location');
})->with(['/user/portfolios/999999', '/missing-user/missing-portfolio']);

it('offers the workspace without exposing another owners portfolio to a signed-in visitor', function () {
    $portfolio = Portfolio::factory()->create(['title' => 'Private other title']);

    $this->actingAs(User::factory()->create())->get(route('userzone.portfolios.show', $portfolio))
        ->assertNotFound()->assertSee('Go to workspace')->assertDontSee($portfolio->title)
        ->assertDontSee('Sign in with Google');
});

it('keeps the owner overview available and JSON authentication responses unchanged', function () {
    $portfolio = Portfolio::factory()->create();

    $this->getJson(route('userzone.portfolios.show', $portfolio))->assertUnauthorized();
    $this->actingAs($portfolio->user)->get(route('userzone.portfolios.show', $portfolio))
        ->assertOk()->assertSee($portfolio->title)->assertSee('Private draft');
});
