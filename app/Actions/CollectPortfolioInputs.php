<?php

namespace App\Actions;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CollectPortfolioInputs
{
    public function __construct(private FetchGithubReadme $readmes, private StorePortfolioCv $storeCv, private ValidateCvPdf $pdfs) {}

    /** @param array{title: string, slug: string, focus?: ?string, repositories: array<int, array{repository_url: string}>} $data */
    public function handle(User $owner, array $data, UploadedFile $pdf, bool $generate = false): Portfolio
    {
        $this->pdfs->handle(file_get_contents($pdf->getRealPath()));
        $evidence = [];
        $seen = [];
        foreach ($data['repositories'] as $index => $repository) {
            try {
                $identity = $this->readmes->identity($repository['repository_url']);
                $key = $identity['owner'].'/'.$identity['name'];
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(["repositories.$index.repository_url" => 'Choose each repository only once.']);
                }
                $seen[$key] = true;
                $collected = $generate ? $identity + ['content' => null, 'fetched_at' => null] : $this->readmes->handle($repository['repository_url']);
                $evidence[] = $collected;
            } catch (ProviderFailure $failure) {
                throw ValidationException::withMessages(["repositories.$index.repository_url" => $failure->getMessage()]);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(["repositories.$index.repository_url" => collect($exception->errors())->flatten()->first()]);
            }
        }
        $portfolio = null;
        $document = null;
        try {
            return DB::transaction(function () use ($owner, $data, $pdf, $evidence, $generate, &$portfolio, &$document): Portfolio {
                $portfolio = $owner->portfolios()->create(collect($data)->only(['title', 'slug', 'focus'])->all());
                foreach ($evidence as $position => $repository) {
                    $portfolio->projects()->create([
                        'repository_owner' => $repository['owner'], 'repository_name' => $repository['name'],
                        'slug' => 'project-'.($position + 1),
                        'readme_content' => $repository['content'], 'readme_fetched_at' => $repository['fetched_at'],
                        'title' => '', 'summary' => '', 'overview' => '', 'technologies' => [], 'position' => $position,
                    ]);
                }
                $this->storeCv->handle($portfolio, $pdf);
                $document = $portfolio->cvDocument()->firstOrFail();

                if ($generate) {
                    app(StartPortfolioGeneration::class)->handle($portfolio);
                }

                return $portfolio;
            });
        } catch (\Throwable $exception) {
            if ($document && ! Portfolio::whereKey($portfolio->id)->exists()) {
                Storage::disk($document->storageDisk())->delete($document->storage_path);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['slug' => 'You already have a portfolio with this address.']);
            }
            throw $exception;
        }
    }
}
