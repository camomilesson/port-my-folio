@php($cv = $page['cv_content'])
@php($editable = $page['editable'] ?? false)
<x-portfolio-frame :page="$page">
    @if(!($page['generation']['has_extraction'] ?? true))<section class="max-w-3xl py-12"><h1 class="text-4xl font-semibold">Your CV is on its way</h1><p class="mt-5 text-stone-500">Your experience, education and skills will appear here as your CV is read.</p><div class="mt-8 h-40 animate-pulse rounded-xl bg-stone-100 motion-reduce:animate-none" aria-label="CV content pending"></div></section>@endif
    <article class="portfolio-cv mx-auto max-w-3xl py-12">
        <x-inline-edit :page="$page" field="name" :value="$cv['name']" label="Name"><h1 class="break-words text-4xl font-semibold">{{ $cv['name'] }}</h1></x-inline-edit>
        <x-inline-edit :page="$page" field="headline" :value="$cv['headline']" label="Title"><p class="mt-3 break-words text-xl text-stone-600">{{ $cv['headline'] }}</p></x-inline-edit>
        <div class="mt-5 flex flex-wrap gap-x-5 gap-y-3 text-sm">
            @if($cv['contact']['location'] || $editable)<x-inline-edit :page="$page" section="contact" field="location" :value="$cv['contact']['location']" label="Location" />@endif
            @if($cv['contact']['email'] || $editable)<x-inline-edit :page="$page" section="contact" field="email" :value="$cv['contact']['email']" label="Email" type="email"><a href="mailto:{{ rawurlencode($cv['contact']['email'] ?? '') }}" class="break-all underline">{{ $cv['contact']['email'] ?: 'Add email' }}</a></x-inline-edit>@endif
            @if($cv['contact']['phone'] || $editable)<x-inline-edit :page="$page" section="contact" field="phone" :value="$cv['contact']['phone']" label="Phone" type="text"><a href="tel:{{ preg_replace('/[^+0-9]/', '', $cv['contact']['phone'] ?? '') }}" class="underline">{{ $cv['contact']['phone'] ?: 'Add phone' }}</a></x-inline-edit>@endif
            @foreach($cv['contact']['links'] as $link)
                <div class="space-y-2">
                    <x-inline-edit :page="$page" section="links" :entry-id="$link['id']" field="label" :value="$link['label']" label="Link label"><a href="{{ $link['url'] }}" class="break-all underline" target="_blank" rel="noopener noreferrer">{{ $link['label'] }}</a></x-inline-edit>
                    @if($editable)<x-inline-edit :page="$page" section="links" :entry-id="$link['id']" field="url" :value="$link['url']" label="Link URL" type="url" />@endif
                </div>
            @endforeach
        </div>
        @if($cv['summary'] || $editable)<x-inline-edit :page="$page" field="summary" :value="$cv['summary']" label="CV summary"><p class="mt-8 whitespace-pre-line break-words leading-relaxed text-stone-700">{{ $cv['summary'] ?: 'Add summary' }}</p></x-inline-edit>@endif
        @foreach(['experience' => 'Experience', 'education' => 'Education'] as $section => $label)
            @if(count($cv[$section]))
                <section class="portfolio-cv-section mt-10 border-t border-stone-200 pt-8">
                    <h2 class="text-2xl font-semibold">{{ $label }}</h2>
                    <div class="mt-6 space-y-8">
                        @foreach($cv[$section] as $entry)
                            @php($titleField = $section === 'experience' ? 'role' : 'qualification')
                            @php($organizationField = $section === 'experience' ? 'organization' : 'institution')
                            <section class="space-y-3">
                                <x-inline-edit :page="$page" :section="$section" :entry-id="$entry['id']" :field="$titleField" :value="$entry[$titleField]" :label="ucfirst($titleField)"><h3 class="break-words text-lg font-semibold">{{ $entry[$titleField] }}</h3></x-inline-edit>
                                <div class="flex flex-wrap items-start gap-3 text-stone-700">
                                    <x-inline-edit :page="$page" :section="$section" :entry-id="$entry['id']" :field="$organizationField" :value="$entry[$organizationField]" :label="ucfirst($organizationField)" />
                                    @if(($entry['location'] ?? null) || $editable)<x-inline-edit :page="$page" :section="$section" :entry-id="$entry['id']" field="location" :value="$entry['location'] ?? null" label="Entry location"><span class="break-words">{{ $entry['location'] ?? 'Add location' }}</span></x-inline-edit>@endif
                                </div>
                                <div class="flex flex-wrap items-start gap-3 text-sm text-stone-500">
                                    @if(($entry['start_date'] ?? null) || $editable)<x-inline-edit :page="$page" :section="$section" :entry-id="$entry['id']" field="start_date" :value="$entry['start_date'] ?? null" label="Start date" type="date" />@endif
                                    @if(($entry['end_date'] ?? null) || $editable)<x-inline-edit :page="$page" :section="$section" :entry-id="$entry['id']" field="end_date" :value="$entry['end_date'] ?? null" label="End date" type="date" />@endif
                                    @if(($entry['current'] ?? false) || $editable)<x-inline-edit :page="$page" :section="$section" :entry-id="$entry['id']" field="current" :value="$entry['current'] ?? false" label="Current position or study" type="checkbox"><span>{{ ($entry['current'] ?? false) ? 'Present' : 'Finished' }}</span></x-inline-edit>@endif
                                </div>
                                @if(($entry['description'] ?? null) || $editable)<x-inline-edit :page="$page" :section="$section" :entry-id="$entry['id']" field="description" :value="$entry['description'] ?? null" label="Description"><p class="whitespace-pre-line break-words leading-relaxed text-stone-600">{{ $entry['description'] ?? 'Add description' }}</p></x-inline-edit>@endif
                            </section>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
        @if(count($cv['skills']))
            <section class="portfolio-cv-section mt-10 border-t border-stone-200 pt-8"><h2 class="text-2xl font-semibold">Skills</h2><div class="mt-5 space-y-4">
                @foreach($cv['skills'] as $group)<div class="space-y-2">
                    <x-inline-edit :page="$page" section="skills" :entry-id="$group['id']" field="name" :value="$group['name']" label="Skill group"><strong class="break-words">{{ $group['name'] }}</strong></x-inline-edit>
                    <x-inline-edit :page="$page" section="skills" :entry-id="$group['id']" field="items" :value="implode(', ', $group['items'])" label="Skills (comma separated)" type="text" />
                </div>@endforeach
            </div></section>
        @endif
        @if(count($cv['languages']))
            <section class="portfolio-cv-section mt-10 border-t border-stone-200 pt-8"><h2 class="text-2xl font-semibold">Languages</h2><ul class="mt-5 space-y-3">
                @foreach($cv['languages'] as $language)<li class="space-y-2 break-words">
                    <x-inline-edit :page="$page" section="languages" :entry-id="$language['id']" field="name" :value="$language['name']" label="Language" />
                    @if(($language['proficiency'] ?? null) || $editable)<x-inline-edit :page="$page" section="languages" :entry-id="$language['id']" field="proficiency" :value="$language['proficiency'] ?? null" label="Proficiency" />@endif
                </li>@endforeach
            </ul></section>
        @endif
        @if(count($cv['certifications']))
            <section class="portfolio-cv-section mt-10 border-t border-stone-200 pt-8"><h2 class="text-2xl font-semibold">Certifications</h2><ul class="mt-5 space-y-4">
                @foreach($cv['certifications'] as $entry)<li class="space-y-2 break-words">
                    <x-inline-edit :page="$page" section="certifications" :entry-id="$entry['id']" field="name" :value="$entry['name']" label="Certification name"><strong>{{ $entry['name'] }}</strong></x-inline-edit>
                    @if(($entry['issuer'] ?? null) || $editable)<x-inline-edit :page="$page" section="certifications" :entry-id="$entry['id']" field="issuer" :value="$entry['issuer'] ?? null" label="Issuer" />@endif
                    @if(($entry['date'] ?? null) || $editable)<x-inline-edit :page="$page" section="certifications" :entry-id="$entry['id']" field="date" :value="$entry['date'] ?? null" label="Certification date" type="date" />@endif
                    @if(($entry['url'] ?? null) || $editable)<x-inline-edit :page="$page" section="certifications" :entry-id="$entry['id']" field="url" :value="$entry['url'] ?? null" label="Credential URL" type="url"><a class="underline" href="{{ $entry['url'] ?? '' }}" target="_blank" rel="noopener noreferrer">{{ ($entry['url'] ?? null) ? 'Credential ↗' : 'Add credential URL' }}</a></x-inline-edit>@endif
                </li>@endforeach
            </ul></section>
        @endif
        @if($page['links']['pdf'])<a class="mt-10 inline-block font-semibold underline" href="{{ $page['links']['pdf'] }}">Download original CV{{ $page['preview'] ? ' (owner preview)' : ' (PDF)' }} ↓</a>@endif
    </article>
</x-portfolio-frame>
