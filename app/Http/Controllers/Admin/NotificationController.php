<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function clearAll()
    {
        auth()->user()->notifications()->delete();
        return redirect()->back();
    }
    public function clear($id)
    {
        auth()->user()->notifications()->where('id', $id)->delete();
        return response()->json(['status' => 'success']);
    }
    public function read($id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();
        $notification->markAsRead();
        return redirect()->back();
    }

}
