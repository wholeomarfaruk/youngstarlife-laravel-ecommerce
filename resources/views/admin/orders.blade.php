@extends('layouts.admin')
@push('styles')
    <style>
        .table th:last-child,
        .table td:last-child {

            width: 204px !important;
            padding: 0 10px;
        }
    </style>
@endpush
@section('content')
    <!-- content area start -->
    @livewire('admin.orders')
    <!-- content area end -->
@endsection
@push('scripts')
    <script>
        $('.delete').click(function(e) {
            e.preventDefault();
            var form = $(this).closest('form');
            var name = $(this).closest('tr').find('.pname').text();
            if (confirm("Are you sure? You want to delete " + name)) {
                form.submit();
            }
        });
        $('.send_courier').click(function(e) {
            e.preventDefault();
            var id = $(this).attr('data-id');
            var href = $(this).attr('href');
            swal.fire({
                title: 'Are you sure?',
                text: "You want to add parcel to this order! Order ID: " + id,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Add Parcel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: "GET",
                        url: href,
                        dataType: "json",
                        success: function(response) {
                            if (response.status === 'success') {
                                swal.fire(
                                    'Success',
                                    response.message,
                                    'success'
                                )
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);

                            } else {
                                swal.fire(
                                    'Error',
                                    response.message,
                                    'error'
                                )
                            }
                            console.log(response);
                        }
                    });
                }
            });
        });
    </script>
    <script>
        $("#bulk-select-button").click(function() {
            $(".select-item").toggle();

            $(".select-item").prop('checked', false);

        })

        document.getElementById('bulk-action-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const status = document.getElementById('bulk-action-status').value;
            console.log(status);
            if (status == '' || status == 'Select Status' || status == null) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No action status selected',
                    text: 'Please select a valid action status to perform.'
                });
                return;
            }
            var selected = document.querySelectorAll('input.select-item:checked');
            const ids = selected ? [...selected].map(el => el.value) : [];


            if (ids.length > 0) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Update'
                }).then((result) => {
                    if (result.isConfirmed) {

                        fetch("{{ route('admin.orders.status.update.bulk') }}", {
                                method: 'put',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                        .content,
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    ids: ids,
                                    status: status,
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Order Status Updated Successfully',
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                    setTimeout(() => location.reload(), 1500);
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: data.message || 'Something went wrong'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error("Error:", error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'An error occurred while updating order statuses'
                                });
                            });
                    }
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No selection',
                    text: 'Please select at least one enquiry to perform this action.'
                });
            }
        });

        document.getElementById('bulk-sticker-print').addEventListener('click', () => {
            console.log('clicked');
            const form = document.getElementById('sticker-print-form');
            const input = form.querySelector('input[name="ids"]');

            var selected = document.querySelectorAll('input.select-item:checked');
            const ids = selected ? [...selected].map(el => el.value) : [];

            if (ids.length > 0) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, generate sticker!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        input.value = ids;
                        form.submit();
                    }


                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No selection',
                    text: 'Please select at least one enquiry to generate sticker.'
                });
            }
        });
        document.getElementById('bulk-action-button').addEventListener('click', () => {
            console.log('clicked');

            var selected = document.querySelectorAll('input.select-item:checked');
            const ids = selected ? [...selected].map(el => el.value) : [];

            if (ids.length > 0) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete them!'
                }).then((result) => {
                    if (result.isConfirmed) {

                    }
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'No selection',
                    text: 'Please select at least one enquiry to delete.'
                });
            }
        });
    </script>
    <script>
        function blockcustomer(id) {


            if (id) {
                // 1. Show confirmation dialog using SweetAlert (Swal)
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Block!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // 2. Execute fetch request to the backend
                        fetch(`/admin/orders/${id}/customer/block`, {
                                // NOTE: For blocking/mutating data, a POST method is generally recommended,
                                // but sticking to your original GET method here.
                                method: 'GET',
                                headers: {
                                    // Assuming you are using jQuery for CSRF token retrieval
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            })
                            // 3. First .then(): Get the Response object and check status
                            .then(response => {
                                if (!response.ok) {
                                    // Throw an error if the HTTP status code is 4xx or 5xx
                                    throw new Error(`HTTP error! Status: ${response.status}`);
                                }
                                // IMPORTANT FIX: Return the promise from response.json()
                                return response.json();
                            })
                            // 4. Second .then(): Process the parsed JSON data (responseJson)
                            .then(responseJson => {
                                console.log(responseJson);
                                if (responseJson.success) {
                                    // Successfully blocked, reload the page
                                    // location.reload();
                                    Swal.fire('Success!', responseJson.message ||
                                        'Customer blocked successfully.', 'success');
                                } else {
                                    // Block was unsuccessful according to the server's logic/message
                                    console.error("Block failed:", responseJson.message);
                                    Swal.fire('Failed!', responseJson.message ||
                                        'Customer block action failed.', 'error');
                                }
                            })
                            // 5. .catch(): Handle any errors (network, HTTP status, or JSON parsing)
                            .catch(error => {
                                console.error('Fetch operation error:', error);
                                Swal.fire('Error!', 'An unexpected error occurred during the request.',
                                    'error');
                            });
                    }
                });
            } else {
                // Show warning if ID is missing
                Swal.fire({
                    icon: 'warning',
                    title: 'No ID provided',
                    text: 'Cannot block customer without an ID.'
                });
            }
        }


        function unblockCustomer(id) {
            console.log(id);

            if (id) {
                // 1. Show confirmation dialog using SweetAlert (Swal)
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Unblock!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // 2. Execute fetch request to the backend
                        fetch(`/admin/orders/${id}/customer/unblock`, {
                                // NOTE: For blocking/mutating data, a POST method is generally recommended,
                                // but sticking to your original GET method here.
                                method: 'GET',
                                headers: {
                                    // Assuming you are using jQuery for CSRF token retrieval
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            })
                            // 3. First .then(): Get the Response object and check status
                            .then(response => {
                                if (!response.ok) {
                                    // Throw an error if the HTTP status code is 4xx or 5xx
                                    throw new Error(`HTTP error! Status: ${response.status}`);
                                }
                                // IMPORTANT FIX: Return the promise from response.json()
                                return response.json();
                            })
                            // 4. Second .then(): Process the parsed JSON data (responseJson)
                            .then(responseJson => {
                                console.log(responseJson);
                                if (responseJson.success) {
                                    // Successfully blocked, reload the page
                                    // location.reload();
                                    Swal.fire('Success!', responseJson.message ||
                                        'Customer unblocked successfully.', 'success');
                                } else {
                                    // Block was unsuccessful according to the server's logic/message
                                    console.error("unBlock failed:", responseJson.message);
                                    Swal.fire('Failed!', responseJson.message ||
                                        'Customer unblock action failed.', 'error');
                                }
                            })
                            // 5. .catch(): Handle any errors (network, HTTP status, or JSON parsing)
                            .catch(error => {
                                console.error('Fetch operation error:', error);
                                Swal.fire('Error!', 'An unexpected error occurred during the request.',
                                    'error');
                            });
                    }
                });
            } else {
                // Show warning if ID is missing
                Swal.fire({
                    icon: 'warning',
                    title: 'No ID provided',
                    text: 'Cannot unblock customer without an ID.'
                });
            }
        }
    </script>
    <script>
  
        // commonjs
        document.addEventListener("DOMContentLoaded", function() {

            flatpickr(".rangedatepicker", {
                mode: "range",
                enableTime: true,
                dateFormat: 'Y-m-d H:i',

            });
        })
    </script>
@endpush
