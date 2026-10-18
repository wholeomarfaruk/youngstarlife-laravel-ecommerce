{{-- Rich text editor (Omar Text Editor), Livewire-safe: <x-ops.rich-text wire:model="form.description" :value="$form['description']" />
     wire:ignore keeps Livewire's morph away from the editor's own DOM; the hidden textarea carries wire:model and the editor keeps
     it in sync with real input events. :value = the current HTML the editor starts from (Blade can't read the Livewire property).
     Uploaded / pasted images go to storage (ops.editor.image), never into the HTML as base64. --}}
@props(['value' => '', 'toolbar' => null, 'height' => 260])
<div wire:ignore class="ops-rte" style="--rte-min: {{ (int) $height }}px" x-data="richText({ toolbar: @js($toolbar), upload: @js(route('ops.editor.image')) })">
    <textarea x-ref="input" {{ $attributes->whereStartsWith('wire:model') }} style="display:none">{{ $value }}</textarea>
</div>
