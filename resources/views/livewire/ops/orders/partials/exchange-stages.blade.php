{{-- second track on the order: the part coming back of a partial delivery, and its exchanges (part of the order, not orders of their own) --}}
@if ($pr = $o->partialReturnStage())<div class="mt-1"><x-ops.badge :tone="$pr[1]">{{ $pr[0] }}</x-ops.badge></div>@endif
@foreach ($o->exchanges->filter->isOpenExchange()->sortBy('id') as $x)
    @php([$label, $tone] = $x->exchangeStage())
    <div class="mt-1"><x-ops.badge :tone="$tone" title="{{ $x->number }}">{{ $label }}@if ($o->exchanges->count() > 1) · {{ \Illuminate\Support\Str::afterLast($x->number, '-') }}@endif</x-ops.badge></div>
@endforeach
