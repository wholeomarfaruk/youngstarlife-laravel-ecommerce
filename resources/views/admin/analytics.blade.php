@extends('layouts.admin')

@section('content')
<div class="container-fluid mt-3">

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Visitors</h5>
                    <p class="card-text fs-4">{{ $totalVisitors }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Page Views</h5>
                    <p class="card-text fs-4">{{ $totalPageViews }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3">
                <div class="card-body">
                    <h5 class="card-title">Today’s Visitors</h5>
                    <p class="card-text fs-4">{{ $todayVisitors }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger mb-3">
                <div class="card-body">
                    <h5 class="card-title">Today’s Page Views</h5>
                    <p class="card-text fs-4">{{ $todayPageViews }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Device Stats</div>
                <div class="card-body">
                    <canvas id="deviceChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header">Top Referrers</div>
                <div class="card-body">
                    <canvas id="referrerChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Pages Table -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-header">Top Pages</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Page URL</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pages as $page)
                            <tr>
                                <td>{{ $page->page_url }}</td>
                                <td>{{ $page->total }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Locations Table -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-header">Visitor Locations</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>Visitors</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($locations as $loc)
                            <tr>
                                <td>{{ $loc->country ?? 'Unknown' }}</td>
                                <td>{{ $loc->total }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Locations Table -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-header">Visitor Cities</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>City</th>
                                <th>Visitors</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cities as $city)
                            <tr>
                                <td>{{ $city->city ?? 'Unknown' }}</td>
                                <td>{{ $city->total }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Pages Table -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-header">Today Pages</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Page URL</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($todaypages as $pageopen)
                            <tr>
                                <td>{{ $pageopen->page_url }}</td>
                                <td>{{ $pageopen->total }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Device Chart
    const deviceCtx = document.getElementById('deviceChart').getContext('2d');
    const deviceChart = new Chart(deviceCtx, {
        type: 'pie',
        data: {
            labels: {!! json_encode($devices->pluck('device')) !!},
            datasets: [{
                data: {!! json_encode($devices->pluck('total')) !!},
                backgroundColor: ['#007bff','#28a745','#ffc107','#dc3545','#6c757d'],
            }]
        }
    });

    // Referrer Chart
    const referrerCtx = document.getElementById('referrerChart').getContext('2d');
    const referrerChart = new Chart(referrerCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($referrers->pluck('referrer')->map(fn($r) => $r ?? 'Direct')) !!},
            datasets: [{
                label: 'Referrer Visits',
                data: {!! json_encode($referrers->pluck('total')) !!},
                backgroundColor: '#17a2b8'
            }]
        },
        options: {
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
</script>
@endsection
