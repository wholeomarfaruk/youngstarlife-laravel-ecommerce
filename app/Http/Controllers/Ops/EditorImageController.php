<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Images dropped / pasted / uploaded into the rich text editor (<x-ops.rich-text>) — stored, the editor gets the URL back */
class EditorImageController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:4096'], ['image.*' => 'Use a JPG, PNG, WebP or GIF under 4 MB.']);
        $path = $request->file('image')->store('editor', 'public');
        return response()->json(['url' => '/storage/'.$path]);   // relative: works on every host the site is served from
    }
}
