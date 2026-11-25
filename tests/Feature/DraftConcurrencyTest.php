<?php

use App\Models\Portfolio;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

it('serializes simultaneous additions at the project and gallery limits', function (string $kind) {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Row-lock concurrency is verified with the PostgreSQL test profile.');
    }
    $owner = User::factory()->create();
    $portfolio = Portfolio::factory()->for($owner)->create();
    Storage::fake('images_local');
    $project = null;
    if ($kind === 'projects') {
        Project::factory()->for($portfolio)->count(4)->create();
    } else {
        $project = Project::factory()->for($portfolio)->create();
        for ($index = 0; $index < 9; $index++) {
            $project->addMedia(UploadedFile::fake()->image('initial.png'))->toMediaCollection('gallery');
        }
    }
    $script = <<<'PHP'
    require 'vendor/autoload.php';
    $settings = json_decode(stream_get_contents(STDIN), true);
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(['database.default' => 'pgsql', 'database.connections.pgsql' => $settings['connection']]);
    Illuminate\Support\Facades\DB::purge('pgsql');
    Illuminate\Support\Facades\Auth::setUser(App\Models\User::findOrFail($settings['owner']));
    $portfolio = App\Models\Portfolio::findOrFail($settings['portfolio']);
    config(['portmyfolio.image_disk' => 'images_local', 'filesystems.disks.images_local' => ['driver' => 'local', 'root' => $settings['image_root'], 'visibility' => 'private', 'throw' => true]]);
    echo "ready\n";
    fflush(STDOUT);
    try {
        if ($settings['kind'] === 'gallery') {
            $project = App\Models\Project::findOrFail($settings['project']);
            $file = new Illuminate\Http\UploadedFile(resource_path('fixtures/demo-project.png'), 'demo.png', 'image/png', null, true);
            app(App\Actions\ManageProjectGallery::class)->store($portfolio, $project, $file, ['decorative' => true]);
        } else {
        app(App\Actions\ManagePortfolioProjects::class)->save($portfolio, null, ['repository_url' => 'https://github.com/example/'.$settings['repository'], 'title' => 'Concurrent project', 'author_role' => 'Me']);
        }
        echo 'created';
    } catch (Illuminate\Validation\ValidationException $e) {
        echo 'limit';
    }
    PHP;
    $connection = config('database.connections.pgsql');
    $workers = [];
    try {
        DB::beginTransaction();
        User::whereKey($owner->id)->lockForUpdate()->firstOrFail();
        foreach (['concurrent-one', 'concurrent-two'] as $repository) {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), ['APP_ENV' => 'testing']);
            $process->setInput(json_encode(['connection' => $connection, 'owner' => $owner->id, 'portfolio' => $portfolio->id, 'repository' => $repository, 'kind' => $kind, 'project' => $project?->id, 'image_root' => Storage::disk('images_local')->path('')], JSON_THROW_ON_ERROR));
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
        $results = [];
        foreach ($workers as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue();
            $results[] = trim(str_replace("ready\n", '', $process->getOutput()));
        }
        sort($results);
        expect($results)->toBe(['created', 'limit']);
        expect($kind === 'projects' ? $portfolio->projects()->count() : $project->fresh()->activeGallery()->count())->toBe($kind === 'projects' ? 5 : 10);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        foreach ($portfolio->projects()->withTrashed()->get() as $item) {
            $item->forceDelete();
        }
        $owner->delete();
    }
})->with(['projects', 'gallery']);
