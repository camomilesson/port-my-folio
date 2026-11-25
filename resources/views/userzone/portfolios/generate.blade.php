<x-layout :title="'Generate · '.$portfolio->title">
    <section class="mx-auto max-w-2xl px-6 py-16">
        <a class="text-sm underline" href="{{ route('userzone.portfolios.show', $portfolio) }}">← Portfolio</a>
        <h1 class="mt-8 text-4xl font-semibold">Generate your portfolio</h1>
        <x-form-errors />
        <div class="mt-6 rounded-xl border border-stone-200 p-5" data-generation-progress data-url="{{ route('userzone.generation.status', $portfolio) }}" data-status="{{ $generation?->status }}">
            <p aria-live="polite" data-generation-label>{{ $generation?->status === 'succeeded' ? 'Ready' : ($generation?->status === 'failed' ? 'Generation stopped. Completed steps are saved.' : ($generation ? 'Checking saved progress…' : 'Not started')) }}</p>
            <p data-generation-retry class="mt-2 text-sm text-stone-500"></p>
        </div>
        <dl class="mt-8 space-y-4">
            <div><dt class="font-semibold">Source PDF</dt><dd>{{ $portfolio->cvDocument?->original_filename ?? 'Missing PDF' }}</dd></div>
            <div><dt class="font-semibold">Focus</dt><dd class="whitespace-pre-line break-words">{{ $generation?->input_snapshot['focus'] ?? $portfolio->focus ?: 'General professional portfolio' }}</dd></div>
        </dl>
        <h2 class="mt-6 text-lg font-semibold">Repositories and your roles</h2>
        <ul class="mt-3 space-y-3">
            @foreach($generation?->input_snapshot['projects'] ?? $portfolio->projects->toArray() as $project)
                <li class="break-words">{{ $project['repository_owner'] }}/{{ $project['repository_name'] }} — {{ $project['author_role'] }}</li>
            @endforeach
        </ul>
        <p class="mt-6 text-sm text-stone-600">{{ $quota['available'] }} of 3 generation slots available. Successful generations count for seven days. Failed attempts release their reservation. Deletion does not refund successful usage.</p>
        @if($quota['next_slot'])<p class="mt-2 text-sm text-stone-500">Next successful-generation slot opens {{ $quota['next_slot'] }} UTC.</p>@endif
        @if(!$generation || $generation->status === 'failed')
            <form method="POST" action="{{ route('userzone.generation.store', $portfolio) }}" class="mt-8 space-y-5">
                @csrf
                <label class="flex items-start gap-3"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>Generate from these inputs. My PDF and project evidence will be processed by the AI provider. Inputs stay fixed for this attempt, and a completed portfolio cannot be regenerated.</span></label>
                <button class="rounded-full bg-stone-900 px-6 py-3 font-medium text-white" @disabled($quota['available'] === 0)>{{ $generation ? 'Retry failed generation' : 'Generate portfolio' }}</button>
            </form>
        @elseif($generation->status === 'succeeded')
            <a class="mt-6 inline-block font-semibold underline" href="{{ route('userzone.preview.home', $portfolio) }}">Review generated draft →</a>
        @else
            <p class="mt-6 text-sm text-stone-600">You can leave and return later. Editing is locked while generation runs.</p>
        @endif
    </section>
</x-layout>
