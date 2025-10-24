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
}
