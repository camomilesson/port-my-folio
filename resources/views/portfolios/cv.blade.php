@php($cv = $page['cv_content'])
<x-portfolio-frame :page="$page">
    @if(!($page['generation']['has_extraction'] ?? true))<section class="max-w-3xl py-12"><h1 class="text-4xl font-semibold">Your CV is on its way</h1><p class="mt-5 text-stone-500">Your experience, education and skills will appear here as your CV is read.</p><div class="mt-8 h-40 animate-pulse rounded-xl bg-stone-100 motion-reduce:animate-none" aria-label="CV content pending"></div></section>@endif
    <article class="mx-auto max-w-3xl py-12">
        <h1 class="break-words text-4xl font-semibold">{{ $cv['name'] }}</h1>
        <p class="mt-3 break-words text-xl text-stone-600">{{ $cv['headline'] }}</p>
        <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-sm">
            @if($cv['contact']['location'])<span>{{ $cv['contact']['location'] }}</span>@endif
            @if($cv['contact']['email'])<a href="mailto:{{ rawurlencode($cv['contact']['email']) }}" class="break-all underline">{{ $cv['contact']['email'] }}</a>@endif
            @if($cv['contact']['phone'])<a href="tel:{{ preg_replace('/[^+0-9]/', '', $cv['contact']['phone']) }}" class="underline">{{ $cv['contact']['phone'] }}</a>@endif
            @foreach($cv['contact']['links'] as $link)
                <a href="{{ $link['url'] }}" class="break-all underline" target="_blank" rel="noopener noreferrer">{{ $link['label'] }}</a>
            @endforeach
        </div>
        @if($cv['summary'])<p class="mt-8 whitespace-pre-line break-words leading-relaxed text-stone-700">{{ $cv['summary'] }}</p>@endif
        @foreach(['experience' => 'Experience', 'education' => 'Education'] as $section => $label)
            @if(count($cv[$section]))
                <section class="mt-10 border-t border-stone-200 pt-8">
                    <h2 class="text-2xl font-semibold">{{ $label }}</h2>
                    <div class="mt-6 space-y-8">
                        @foreach($cv[$section] as $entry)
                            <section>
                                <h3 class="break-words text-lg font-semibold">{{ $entry[$section === 'experience' ? 'role' : 'qualification'] }}</h3>
                                <p class="mt-1 break-words text-stone-700">{{ $entry[$section === 'experience' ? 'organization' : 'institution'] }}@if($entry['location'] ?? null) · {{ $entry['location'] }}@endif</p>
                                <p class="mt-2 text-sm text-stone-500">{{ $entry['start_date'] ?? '' }}@if(($entry['current'] ?? false) || ($entry['end_date'] ?? null)){{ ($entry['start_date'] ?? null) ? ' – ' : '' }}{{ ($entry['current'] ?? false) ? 'Present' : $entry['end_date'] }}@endif</p>
                                @if($entry['description'] ?? null)<p class="mt-3 whitespace-pre-line break-words leading-relaxed text-stone-600">{{ $entry['description'] }}</p>@endif
                            </section>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
        @if(count($cv['skills']))
            <section class="mt-10 border-t border-stone-200 pt-8"><h2 class="text-2xl font-semibold">Skills</h2><div class="mt-5 space-y-4">@foreach($cv['skills'] as $group)<p class="break-words"><strong>{{ $group['name'] }}</strong> · {{ implode(', ', $group['items']) }}</p>@endforeach</div></section>
        @endif
        @if(count($cv['languages']))
            <section class="mt-10 border-t border-stone-200 pt-8"><h2 class="text-2xl font-semibold">Languages</h2><ul class="mt-5 space-y-3">@foreach($cv['languages'] as $language)<li class="break-words">{{ $language['name'] }}@if($language['proficiency'] ?? null) · {{ $language['proficiency'] }}@endif</li>@endforeach</ul></section>
        @endif
        @if(count($cv['certifications']))
            <section class="mt-10 border-t border-stone-200 pt-8"><h2 class="text-2xl font-semibold">Certifications</h2><ul class="mt-5 space-y-4">@foreach($cv['certifications'] as $entry)<li class="break-words"><strong>{{ $entry['name'] }}</strong>@if($entry['issuer'] ?? null) · {{ $entry['issuer'] }}@endif @if($entry['date'] ?? null) · {{ $entry['date'] }}@endif @if($entry['url'] ?? null)<a class="underline" href="{{ $entry['url'] }}" target="_blank" rel="noopener noreferrer">Credential ↗</a>@endif</li>@endforeach</ul></section>
        @endif
        @if($page['links']['pdf'])
            <a class="mt-10 inline-block font-semibold underline" href="{{ $page['links']['pdf'] }}">Download original CV{{ $page['preview'] ? ' (owner preview)' : ' (PDF)' }} ↓</a>
        @endif
    </article>
</x-portfolio-frame>
