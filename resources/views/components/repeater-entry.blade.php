@props(['section', 'index', 'fields', 'entry' => []])
<fieldset data-entry class="space-y-4 rounded-xl border border-stone-200 bg-white p-5">
    <legend class="px-2 text-sm font-semibold">{{ $section === 'contact[links]' ? 'Contact link' : ucfirst($section) . ' entry' }}</legend>
    <input type="hidden" name="{{ $section }}[{{ $index }}][id]" value="{{ $entry['id'] ?? '' }}">
    @foreach($fields as $key => $label)
        @php($value = $key === 'items_text' ? implode("\n", $entry['items'] ?? []) : ($entry[$key] ?? ''))
        @if(in_array($key, ['description', 'items_text']))
            <x-form-textarea :name="$section.'['.$index.']['.$key.']'" :label="$label" :value="$value" rows="4" :maxlength="$key === 'description' ? 5000 : 4000" />
        @else
            <x-form-input :name="$section.'['.$index.']['.$key.']'" :label="$label" :value="$value" :maxlength="in_array($key, ['url']) ? 2048 : 160" :placeholder="str_contains($key, 'date') ? 'YYYY or YYYY-MM; leave blank if unknown' : ''" />
        @endif
    @endforeach
    @if(in_array($section, ['experience', 'education']))
        <input type="hidden" name="{{ $section }}[{{ $index }}][current]" value="0">
        <label class="flex items-center gap-3 text-sm"><input data-current type="checkbox" name="{{ $section }}[{{ $index }}][current]" value="1" @checked($entry['current'] ?? false)>Currently here (no end date)</label>
    @endif
    <div class="flex flex-wrap gap-5 border-t border-stone-100 pt-4 text-sm font-semibold">
        <button type="button" data-entry-up class="underline underline-offset-4">Move up</button>
        <button type="button" data-entry-down class="underline underline-offset-4">Move down</button>
        <button type="button" data-entry-remove class="text-red-700 underline underline-offset-4">Remove entry</button>
    </div>
</fieldset>
