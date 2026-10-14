{{-- exchanges of this order as stages on it (an exchange is part of the order, not an order of its own) --}}
@foreach ($o->exchanges->filter->isOpenExchange()->sortBy('id') as $x)
    @php([$label, $tone] = $x->exchangeStage())
    <div class="mt-1"><x-ops.badge :tone="$tone" title="{{ $x->number }}">{{ $label }}@if ($o->exchanges->count() > 1) · {{ \Illuminate\Support\Str::afterLast($x->number, '-') }}@endif</x-ops.badge></div>
@endforeach
