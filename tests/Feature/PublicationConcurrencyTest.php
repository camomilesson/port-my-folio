<?php

use App\Actions\PublishPortfolio;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

it('preserves publication and default invariants under simultaneous requests', function (string $case) {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Publication locking is verified with the PostgreSQL test profile.');
    }
    Storage::fake('local');
    Storage::fake('images_local');
    $owner = User::factory()->create();
    $first = Portfolio::factory()->for($owner)->generated()->create();
    $second = Portfolio::factory()->for($owner)->generated()->create();
    $this->actingAs($owner);
    if ($case === 'default and unpublish') {
        app(PublishPortfolio::class)->handle($first);
        app(PublishPortfolio::class)->handle($second);
        $owner->forceFill(['default_portfolio_id' => $second->id])->save();
    }
    $script = <<<'PHP'
    require 'vendor/autoload.php';
    $settings = json_decode(stream_get_contents(STDIN), true);
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(['database.default' => 'pgsql', 'database.connections.pgsql' => $settings['connection'], 'queue.default' => 'sync', 'filesystems.disks.local' => ['driver' => 'local', 'root' => $settings['cv_root'], 'throw' => true]]);
    Illuminate\Support\Facades\DB::purge('pgsql');
    Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail($settings['owner']));
    $portfolio = App\Models\Portfolio::findOrFail($settings['portfolio']);
    echo "ready\n";
    fflush(STDOUT);
    try {
        $action = match ($settings['action']) {
            'publish' => App\Actions\PublishPortfolio::class,
            'default' => App\Actions\SetDefaultPortfolio::class,
            'unpublish' => App\Actions\UnpublishPortfolio::class,
        };
        app($action)->handle($portfolio);
        echo 'ok';
    } catch (Illuminate\Validation\ValidationException $exception) {
        echo 'refused';
    }
    PHP;
    $tasks = $case === 'first publication' ? [[$first, 'publish'], [$second, 'publish']] : [[$first, 'default'], [$first, 'unpublish']];
    $workers = [];
    try {
        DB::beginTransaction();
        User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
        foreach ($tasks as [$portfolio, $action]) {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), ['APP_ENV' => 'testing']);
            $process->setInput(json_encode(['connection' => config('database.connections.pgsql'), 'owner' => $owner->id, 'portfolio' => $portfolio->id, 'action' => $action, 'cv_root' => Storage::disk('local')->path('')], JSON_THROW_ON_ERROR));
            $process->setTimeout(20);
            $process->start();
            $workers[] = $process;
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
        if ($case === 'first publication') {
            expect($first->fresh()->isPublished())->toBeTrue();
            expect($second->fresh()->isPublished())->toBeTrue();
            expect($owner->fresh()->default_portfolio_id)->toBeIn([$first->id, $second->id]);
        } else {
            expect($first->fresh()->isPublished())->toBeFalse();
            expect($owner->fresh()->default_portfolio_id)->toBeIn([null, $second->id]);
        }
        $default = $owner->fresh()->defaultPortfolio;
        if ($default) {
            expect($default->user_id)->toBe($owner->id);
            expect($default->isPublished())->toBeTrue();
        }
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        foreach ([$first, $second] as $portfolio) {
            Storage::disk('local')->delete($portfolio->cvDocument->storage_path);
        }
        $owner->delete();
    }
})->with(['first publication', 'default and unpublish']);
