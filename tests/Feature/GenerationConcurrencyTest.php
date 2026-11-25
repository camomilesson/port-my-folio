<?php

use App\Models\CvDocument;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

it('accepts only one of two simultaneous starts for the same portfolio', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Generation locks require the PostgreSQL test profile.');
    }
    $owner = User::factory()->create();
    $portfolios = Portfolio::factory()->for($owner)->count(1)->create();
    foreach ($portfolios as $portfolio) {
        CvDocument::factory()->for($portfolio)->create();
        Project::factory()->for($portfolio)->create();
    }
    $script = <<<'PHP'
    require 'vendor/autoload.php';
    $settings = json_decode(stream_get_contents(STDIN), true);
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(['database.default' => 'pgsql', 'database.connections.pgsql' => $settings['connection'], 'queue.default' => 'database']);
    Illuminate\Support\Facades\DB::purge('pgsql');
    Illuminate\Support\Facades\Queue::fake([App\Jobs\GeneratePortfolioStep::class]);
    Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail($settings['owner']));
    echo "ready\n";
    fflush(STDOUT);
    try {
        app(App\Actions\StartPortfolioGeneration::class)->handle(App\Models\Portfolio::findOrFail($settings['portfolio']));
        echo 'started';
    } catch (Illuminate\Validation\ValidationException) {
        echo 'refused';
    }
    PHP;
    $workers = [];
    try {
        DB::beginTransaction();
        User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
        foreach ([$portfolios->first(), $portfolios->first()] as $portfolio) {
            $worker = new Process([PHP_BINARY, '-r', $script], base_path(), ['APP_ENV' => 'testing']);
            $worker->setInput(json_encode(['connection' => config('database.connections.pgsql'), 'owner' => $owner->id, 'portfolio' => $portfolio->id], JSON_THROW_ON_ERROR));
            $worker->setTimeout(20);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 10;
        while (count(array_filter($workers, fn (Process $worker): bool => str_contains($worker->getOutput(), 'ready'))) < 2 && microtime(true) < $deadline) {
            usleep(10000);
        }
        expect(count(array_filter($workers, fn (Process $worker): bool => str_contains($worker->getOutput(), 'ready'))))->toBe(2);
        DB::commit();
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue();
        }
        expect($owner->generations()->whereIn('status', ['queued', 'running'])->count())->toBe(1);
        expect(count(array_filter($workers, fn (Process $worker): bool => str_contains($worker->getOutput(), 'started'))))->toBe(1);
        expect(count(array_filter($workers, fn (Process $worker): bool => str_contains($worker->getOutput(), 'refused'))))->toBe(1);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        $owner->delete();
    }
});
