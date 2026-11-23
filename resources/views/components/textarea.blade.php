@props(['name', 'label', 'value' => null, 'required' => false, 'rows' => 4, 'help' => null])
@php $fid = $attributes->get('id', $name); @endphp
<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    <label for="{{ $fid }}" class="form-label">{{ $label }}@if($required) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
    <textarea id="{{ $fid }}" name="{{ $name }}" rows="{{ $rows }}"
              @required($required)
              @if($help) aria-describedby="{{ $fid }}-help" @endif
              {{ $attributes->except(['class', 'id']) }}
              @class(['form-control', 'is-invalid' => $errors->has($name)])>{{ old($name, $value) }}</textarea>
    @if($help)<div id="{{ $fid }}-help" class="form-text">{{ $help }}</div>@endif
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
