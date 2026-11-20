@props(['name', 'label', 'type' => 'text', 'required' => false, 'value' => null, 'autocomplete' => null, 'hint' => null])
@php($id = 'field-'.$name)
<div>
    <label for="{{ $id }}" class="block text-sm font-semibold text-ink-900">
        {{ $label }} @if ($required)<span class="text-red-700" aria-hidden="true">*</span>@endif
    </label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="5" @required($required)
            @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
            class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-2.5 focus:border-lodge-700 focus:ring-2 focus:ring-lodge-700/30 focus:outline-none">{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required)
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
            class="mt-1 w-full rounded-lg border border-sand-300 px-3 py-2.5 focus:border-lodge-700 focus:ring-2 focus:ring-lodge-700/30 focus:outline-none">
    @endif
    @if ($hint)
        <p class="mt-1 text-xs text-ink-900/60">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="mt-1 text-sm font-medium text-red-700">{{ $message }}</p>
    @enderror
</div>
