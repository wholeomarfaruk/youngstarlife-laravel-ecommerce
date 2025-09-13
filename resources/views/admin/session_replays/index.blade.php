@extends('layouts.admin')

@section('content')
<div class="container mt-4">
    <h2>Session Replays</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Session</th>
                <th>Page</th>
                <th>Created</th>
                <th>Replay</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ $r->session_id }}</td>
                <td>{{ $r->page_url }}</td>
                <td>{{ $r->created_at }}</td>
                <td>
                    <a href="{{ route('admin.session.replays.show', $r->id) }}" class="btn btn-primary btn-sm">Replay</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $records->links() }}
</div>
@endsection
