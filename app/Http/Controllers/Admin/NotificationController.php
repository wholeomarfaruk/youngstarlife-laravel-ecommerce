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

        if (request()->wantsJson()) {
            return response()->json(['status' => 'success']);
        }

        return redirect()->back();
    }
    public function read($id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return redirect()->back();
    }

}
