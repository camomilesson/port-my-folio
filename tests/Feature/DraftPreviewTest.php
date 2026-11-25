<?php

use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews every project in order and keeps the home personal information minimal', function () {
    $portfolio = Portfolio::factory()->create();
    $first = Project::factory()->for($portfolio)->create(['title' => 'First project', 'position' => 0]);
    $second = Project::factory()->for($portfolio)->create(['title' => 'Second project', 'position' => 1]);
    Project::factory()->for($portfolio)->create(['title' => 'Removed project'])->delete();

    $this->actingAs($portfolio->user)->get(route('userzone.preview.home', $portfolio))->assertOk()
        ->assertSeeInOrder(['First project', 'Second project'])->assertSee('Explore my CV')
        ->assertSee('Private draft preview')->assertDontSee('Removed project')->assertDontSee('sam@example.com')->assertDontSee('Example Studio');
    $this->get(route('userzone.preview.cv', $portfolio))->assertOk()->assertSee('Example Studio')->assertSee('sam@example.com');
    $this->get(route('userzone.preview.project', [$portfolio, $first]))->assertOk()->assertSee('First project')->assertDontSee('Gallery');
});

it('escapes all draft prose in owner previews', function () {
    $portfolio = Portfolio::factory()->create();
    $cv = $portfolio->cv_content;
    $cv['name'] = '<script>alert(1)</script>';
    $cv['summary'] = '<img src=x onerror=alert(1)>';
    $portfolio->update(['cv_content' => $cv, 'home_content' => ['introduction' => '<svg onload=alert(1)>']]);
    $project = Project::factory()->for($portfolio)->create(['title' => '<script>project</script>', 'overview' => '<img src=x onerror=alert(2)>']);

    $this->actingAs($portfolio->user)->get(route('userzone.preview.home', $portfolio))->assertOk()->assertSee($cv['name'])->assertDontSee($cv['name'], false)->assertSee('<svg onload=alert(1)>')->assertDontSee('<svg onload=alert(1)>', false);
    $this->get(route('userzone.preview.cv', $portfolio))->assertOk()->assertSee($cv['summary'])->assertDontSee($cv['summary'], false);
    $this->get(route('userzone.preview.project', [$portfolio, $project]))->assertOk()->assertSee($project->title)->assertDontSee($project->overview, false);
});

it('keeps all previews owner-only and rejects mismatched nested projects', function () {
    $project = Project::factory()->create();
    $portfolio = $project->portfolio;
    $this->get(route('userzone.preview.home', $portfolio))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('userzone.preview.home', $portfolio))->assertNotFound();
    $this->get(route('userzone.preview.cv', $portfolio))->assertNotFound();
    $this->get(route('userzone.preview.project', [$portfolio, $project]))->assertNotFound();
    $other = Portfolio::factory()->for($portfolio->user)->create();
    $this->actingAs($portfolio->user)->get(route('userzone.preview.project', [$other, $project]))->assertNotFound();
});
