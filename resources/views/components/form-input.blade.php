@props(['name', 'label', 'value' => '', 'required' => false])
@php($errorKey = trim(str_replace(['[', ']'], ['.', ''], $name), '.'))
<div class="space-y-2">
    <label for="{{ $name }}" class="block text-sm font-semibold">{{ $label }}@if($required)<span aria-hidden="true"> *</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" value="{{ old($errorKey, $value) }}" @required($required)
        {{ $attributes->class(['block w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-stone-900 focus:border-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-100']) }}
        @error($errorKey) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @error($errorKey)<p id="{{ $name }}-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
</div>
