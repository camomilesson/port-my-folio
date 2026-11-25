<?php

namespace App\Actions;

use App\Jobs\DeleteUnusedProjectMedia;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PublishPortfolio
{
    public function __construct(private EditPortfolioDraft $editor, private BuildPortfolioSnapshot $builder) {}

    public function handle(Portfolio $portfolio): void
    {
        $this->editor->handle($portfolio, function (Portfolio $locked): void {
            $generations = $locked->generations()->where('user_id', $locked->user_id)->where('status', 'succeeded')->whereNotNull('completed_at');
            if (! app()->environment(['local', 'testing'])) {
                $generations->whereNotNull('provider')->where('provider', '!=', 'fixture');
            }
            if (! $generations->exists()) {
                throw ValidationException::withMessages(['publication' => 'Complete initial generation before publishing this portfolio.']);
            }
            $snapshot = $this->builder->build($locked);
            $locked->update(['published_snapshot' => $snapshot, 'published_at' => $snapshot['published_at']]);
            $owner = User::findOrFail($locked->user_id);
            if ($owner->default_portfolio_id === null) {
                $owner->forceFill(['default_portfolio_id' => $locked->id])->save();
            }
            foreach ($locked->projects()->withTrashed()->pluck('id') as $projectId) {
                DeleteUnusedProjectMedia::dispatch($projectId)->afterCommit();
            }
        });
    }
}
