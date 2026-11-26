@props(['page'])
<details class="appearance-menu mx-auto mt-3 max-w-3xl text-left" data-appearance>
    <summary class="mx-auto w-fit cursor-pointer rounded-full border border-stone-300 bg-white px-5 py-2 text-sm font-semibold">Appearance</summary>
    <form action="{{ $page['appearance_url'] }}" method="POST" data-appearance-form class="appearance-panel mt-4 rounded-2xl border border-stone-200 bg-white p-5 text-stone-900" data-saved-preset="{{ $page['preset'] }}" data-saved-accent="{{ $page['accent_color'] }}">
        @csrf @method('PATCH')
        <fieldset>
            <legend class="text-sm font-semibold">Choose a preset</legend>
            <div class="mt-3 grid grid-cols-3 gap-3">
                @foreach(config('portmyfolio.presets') as $key => $preset)
                    <label class="appearance-preset relative cursor-pointer rounded-xl border border-stone-200 p-3">
                        <input type="radio" name="preset" value="{{ $key }}" @checked($page['preset'] === $key) class="absolute right-3 top-3 size-4" data-appearance-preset>
                        <span class="preset-sample preset-sample-{{ $key }} mb-3 block rounded-lg p-3" aria-hidden="true">Aa<span class="mt-1 block h-1 w-2/3 rounded bg-current opacity-20"></span></span>
                        <span class="block text-sm font-semibold">{{ $preset['label'] }}</span>
                        <span class="mt-1 hidden text-xs leading-relaxed text-stone-500 sm:block">{{ $preset['description'] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        <fieldset class="mt-5 border-t border-stone-200 pt-5">
            <legend class="text-sm font-semibold">Accent color</legend>
            <div class="mt-3 flex flex-wrap gap-3" aria-label="Suggested accent colors">
                @foreach(config('portmyfolio.accent_swatches') as $name => $color)
                    <button type="button" data-appearance-swatch="{{ $color }}" class="accent-swatch size-9 rounded-full border-2 border-white shadow-sm" style="background-color: {{ $color }}" aria-label="Use {{ $name }} accent" aria-pressed="{{ strtolower($page['accent_color']) === $color ? 'true' : 'false' }}"></button>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <label class="block text-xs font-semibold" for="appearance-picker">Color picker<input id="appearance-picker" type="color" value="{{ $page['accent_color'] }}" data-appearance-color class="mt-2 block h-11 w-16 cursor-pointer rounded-lg border border-stone-300 bg-white p-1"></label>
                <label class="block text-xs font-semibold" for="appearance-hex">Hex color<input id="appearance-hex" type="text" value="{{ $page['accent_color'] }}" data-appearance-hex maxlength="7" pattern="#[a-fA-F0-9]{6}" autocomplete="off" spellcheck="false" aria-describedby="appearance-color-error" class="inline-input w-36 font-mono" placeholder="#2563eb"></label>
            </div>
            <p id="appearance-color-error" data-appearance-color-error class="mt-2 text-sm text-red-700" role="alert"></p>
        </fieldset>
        <p class="mt-4 text-xs text-stone-500">Fonts and backgrounds belong to each preset. Changes save to your private draft.</p>
        <p data-appearance-status role="status" class="mt-2 text-sm text-stone-600">Choose a style to preview it here.</p>
        <button type="button" data-appearance-retry hidden class="mt-3 rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold">Retry saving</button>
    </form>
</details>
