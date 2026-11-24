@props(['name', 'label', 'value' => ''])
<div class="space-y-2">
    <label for="{{ $name }}" class="block text-sm font-semibold">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-stone-900 focus:border-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-100']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>{{ old($name, $value) }}</textarea>
    @error($name)<p id="{{ $name }}-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
</div>
