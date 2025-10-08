<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;




class UserController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return view('admin.user.index', compact('user'));
    }
    public function update(Request $request)
    {
        if(isset($request->old_password) &&isset($request->new_password) && isset($request->new_password_confirmation)){
            $this->validate($request, [
                'old_password' => 'required',
                'new_password' => 'required|confirmed',
            ]);
        }
        $user = User::find($request->id);

        $user->name = $request->name;
        $user->email = $request->email;
        if(isset($request->old_password) && isset($request->new_password) && isset($request->new_password_confirmation)){
            if (Hash::check($request->old_password, $user->password)) {
                $user->password = Hash::make($request->new_password);
            }
        }
        $user->save();
        return redirect()->back()->with('status', 'Admin Settings Updated Successfully');
    }
}
