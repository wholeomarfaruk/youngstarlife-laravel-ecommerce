<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SessionRecord;

class SessionRecordController extends Controller
{
    // Save session events
    public function store(Request $request)
    {
        

        SessionRecord::create([
            'session_id' => $request->session()->getId(),
            'page_url'   => $request->input('page_url'),
            'events'     => $request->input('events'),
        ]);

        return response()->json(['status' => 'ok']);
    }

    // Admin: list all sessions
    public function index()
    {
        $records = SessionRecord::latest()->paginate(10);
        return view('admin.session_replays.index', compact('records'));
    }

    // Admin: show replay
    public function show($id)
    {
        $record = SessionRecord::findOrFail($id);
        return view('admin.session_replays.show', compact('record'));
    }
}
