@extends('layouts.admin')

@section('content')
    <!-- content area start -->
    @livewire('admin.products')
    <!-- content area end -->
@endsection
@push('scripts')
<script>
    $('.delete').click(function (e) {
        e.preventDefault();
        var form = $(this).closest('form');
        var name = $(this).closest('tr').find('.pname').text();
        if (confirm("Are you sure? You want to delete " + name)) {
            form.submit();
        }
    })
</script>
@endpush
