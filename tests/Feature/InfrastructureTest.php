<?php

use App\Jobs\InfrastructureProbe;
use Illuminate\Filesystem\FilesystemAdapter;
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

it('storage probe uses a PDF accepted by a PDF-only bucket', function () {
    $fixture = file_get_contents(resource_path('fixtures/infrastructure-probe.pdf'));
    $path = null;
    $disk = Mockery::mock(FilesystemAdapter::class);
    Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
    $disk->shouldReceive('put')->once()->withArgs(function (string $key, string $contents, array $options) use (&$path): bool {
        $path = $key;

        return str_ends_with($key, '.pdf')
            && (new finfo(FILEINFO_MIME_TYPE))->buffer($contents) === 'application/pdf'
            && $options['ContentType'] === 'application/pdf';
    })->andReturnTrue();
    $disk->shouldReceive('get')->once()->withArgs(function (string $key) use (&$path): bool {
        return $key === $path;
    })->andReturn($fixture);
    $disk->shouldReceive('delete')->once()->withArgs(function (string $key) use (&$path): bool {
        return $key === $path;
    })->andReturnTrue();

    $this->artisan('infrastructure:check', ['--disk' => 's3'])->assertSuccessful();
});
