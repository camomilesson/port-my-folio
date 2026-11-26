@props(['page', 'field', 'value' => null, 'label', 'scope' => 'cv', 'section' => null, 'entryId' => null, 'url' => null, 'type' => 'textarea'])
@if($page['editable'] ?? false)
    @php
        $action = $url ?? $page['inline'][$scope];
        $key = hash('sha256', $action.'|'.$section.'|'.$entryId.'|'.$field);
    @endphp
    <div class="inline-field min-w-0" data-inline-key="{{ $key }}">
        <div data-inline-display>{{ $slot->isEmpty() ? ($value ?: 'Add '.strtolower($label)) : $slot }}</div>
        <details data-inline-editor>
            <summary class="inline-pencil" aria-label="Edit {{ $label }}" title="Edit {{ $label }}">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l4-1L20 7a2.83 2.83 0 0 0-4-4L4 15l-1 6 5-2" /></svg>
            </summary>
            <form method="POST" action="{{ $action }}" class="inline-form" data-inline-form>
                @csrf @method('PATCH')
                <input type="hidden" name="field" value="{{ $field }}">
                @if($section)<input type="hidden" name="section" value="{{ $section }}">@endif
                @if($entryId)<input type="hidden" name="entry_id" value="{{ $entryId }}">@endif
                <label class="block text-sm font-semibold" for="inline-{{ $key }}">{{ $label }}</label>
                @if($type === 'checkbox')
                    <input type="hidden" name="value" value="0">
                    <input id="inline-{{ $key }}" type="checkbox" name="value" value="1" @checked($value) class="mt-3 size-5">
                @elseif($type === 'textarea')
                    <textarea id="inline-{{ $key }}" name="value" rows="4" class="inline-input">{{ $value }}</textarea>
                @else
                    <input id="inline-{{ $key }}" name="value" type="{{ in_array($type, ['email', 'url'], true) ? $type : 'text' }}" value="{{ $value }}" @if($type === 'date') placeholder="YYYY or YYYY-MM" @endif class="inline-input">
                @endif
                <p data-inline-error class="mt-2 text-sm text-red-700" role="alert"></p>
                <div class="mt-3 flex gap-3 text-sm"><button type="submit" class="rounded-full bg-stone-900 px-5 py-2 font-semibold text-white">Save</button><button type="button" data-inline-cancel class="rounded-full border border-stone-300 px-5 py-2">Cancel</button></div>
            </form>
        </details>
    </div>
@else
    {{ $slot->isEmpty() ? $value : $slot }}
@endif
