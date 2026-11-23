<?php

namespace Tests\Feature;

use App\Jobs\InfrastructureProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_probe_cleans_up_its_fixture(): void
    {
        Storage::fake('local');
        $this->artisan('infrastructure:check', ['--disk' => 'local'])->assertSuccessful();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_database_queue_probe_is_processed_by_a_worker(): void
    {
        config(['queue.default' => 'database']);
        InfrastructureProbe::dispatch('test-probe');
        $this->assertDatabaseCount('jobs', 1);
        $this->assertNull(Cache::get('infrastructure-probe:test-probe'));
        $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->assertSuccessful();
        $this->artisan('infrastructure:queue', ['token' => 'test-probe'])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
