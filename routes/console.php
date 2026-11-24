<?php

use App\Jobs\InfrastructureProbe;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('infrastructure:check {--disk= : Also test a configured storage disk}', function () {
    DB::select('select 1');
    $this->info('Database connection: OK');

    if ($diskName = $this->option('disk')) {
        $disk = Storage::disk($diskName);
        $path = 'infrastructure-probes/'.Str::uuid().'.pdf';
        $fixture = file_get_contents(resource_path('fixtures/infrastructure-probe.pdf'));
        try {
            if (! $disk->put($path, $fixture, ['ContentType' => 'application/pdf']) || $disk->get($path) !== $fixture) {
                throw new RuntimeException('Storage round-trip failed.');
            }
            $this->info('Storage write/read: OK');
        } finally {
            if (! $disk->delete($path)) {
                throw new RuntimeException('Probe file cleanup failed.');
            }
        }
        $this->info('Storage cleanup: OK');
    }
})->purpose('Check database and optionally storage using a disposable PDF fixture');

Artisan::command('infrastructure:queue {token?}', function () {
    if ($token = $this->argument('token')) {
        if (Cache::get('infrastructure-probe:'.$token) !== 'completed') {
            $this->error('Probe has not completed. Check the worker and retry this status command.');

            return 1;
        }
        $this->info('Queue probe completed.');

        return 0;
    }

    $token = (string) Str::uuid();
    InfrastructureProbe::dispatch($token);
    $this->info('Probe dispatched. Check completion with: php artisan infrastructure:queue '.$token);

    return 0;
})->purpose('Dispatch a harmless queue probe or check its completion');
