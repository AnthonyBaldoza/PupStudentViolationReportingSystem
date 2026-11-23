@props(['name', 'label', 'required' => false, 'help' => null])
@php $fid = $attributes->get('id', $name); @endphp
<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    <label for="{{ $fid }}" class="form-label">{{ $label }}@if($required) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
    <select id="{{ $fid }}" name="{{ $name }}"
            @required($required)
            @if($help) aria-describedby="{{ $fid }}-help" @endif
            {{ $attributes->except(['class', 'id']) }}
            @class(['form-select', 'is-invalid' => $errors->has($name)])>
        {{ $slot }}
    </select>
    @if($help)<div id="{{ $fid }}-help" class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
