<?php

use App\Jobs\InfrastructureProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('storage probe cleans up its fixture', function () {
    Storage::fake('local');
    $this->artisan('infrastructure:check', ['--disk' => 'local'])->assertSuccessful();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('database queue probe is processed by a worker', function () {
    config(['queue.default' => 'database']);
    InfrastructureProbe::dispatch('test-probe');
    $this->assertDatabaseCount('jobs', 1);
    expect(Cache::get('infrastructure-probe:test-probe'))->toBeNull();
    $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->assertSuccessful();
    $this->artisan('infrastructure:queue', ['token' => 'test-probe'])->assertSuccessful();
    $this->assertDatabaseCount('jobs', 0);
});

it('health endpoint responds', function () {
    $this->get('/up')->assertOk();
});
